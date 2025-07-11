<?php
/**
 * This code is licensed under the BSD 3-Clause License.
 *
 * Copyright (c) 2017, Maks Rafalko
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * * Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace Infection\Tests\Console;

use function array_merge;
use function basename;
use Composer\Autoload\ClassLoader;
use const DIRECTORY_SEPARATOR;
use function extension_loaded;
use function file_exists;
use function function_exists;
use function getenv;
use function implode;
use Infection\Command\ConfigureCommand;
use Infection\Console\Application;
use Infection\Console\E2E;
use Infection\FileSystem\Finder\ConcreteComposerExecutableFinder;
use Infection\FileSystem\Finder\Exception\FinderException;
use Infection\Testing\SingletonContainer;
use function is_readable;
use const PHP_EOL;
use const PHP_OS;
use const PHP_SAPI;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use function Safe\chdir;
use function Safe\copy;
use function Safe\file_get_contents;
use function Safe\getcwd;
use function Safe\ini_get;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

#[Group('e2e')]
#[Group('integration')]
#[Large]
#[CoversNothing]
final class E2ETest extends TestCase
{
    /**
     * This group must be excluded from E2E testing to avoid endless recursive testing loop situation.
     */
    private const EXCLUDED_GROUP = 'integration';

    private const MAX_FAILING_COMPOSER_INSTALL = 5;

    private const EXPECT_ERROR = 1;

    private const EXPECT_SUCCESS = 0;

    /**
     * @var string
     */
    private $cwd;

    /**
     * @var ClassLoader|null
     */
    private $previousLoader;

    private static $countFailingComposerInstall = 0;

    protected function setUp(): void
    {
        if (PHP_SAPI === 'phpdbg') {
            $this->markTestSkipped('Running this test on PHPDBG causes failures on Travis, see https://github.com/infection/infection/pull/622.');
        }

        if (getenv('DEPS') === 'LOW') {
            $this->markTestSkipped('Running tests with different lowest versions of dependencies between Infection and underlying e2e tests causes failures, see https://github.com/infection/infection/pull/741.');
        }

        // Without overcommit this test fails with `proc_open(): fork failed - Cannot allocate memory`
        if (str_starts_with(PHP_OS, 'Linux')
            && is_readable('/proc/sys/vm/overcommit_memory')
            && (int) file_get_contents('/proc/sys/vm/overcommit_memory') === 2
        ) {
            $this->markTestSkipped('This test needs copious amounts of virtual memory. It will fail unless it is allowed to overcommit memory.');
        }

        // E2E tests usually require to chdir to their location
        // Hence we would need to go back to this dir
        $this->cwd = getcwd();
    }

    protected function tearDown(): void
    {
        if ($this->previousLoader instanceof ClassLoader) {
            $this->previousLoader->unregister();
        }

        chdir($this->cwd);
    }

    /**
     * Longest test: runs under about 160-200 sec
     *
     * To be run with:
     *
     * php -dmemory_limit=128M vendor/bin/phpunit --group=large
     */
    public function test_it_runs_on_itself(): void
    {
        if (ini_get('memory_limit') === '-1') {
            $this->markTestSkipped(implode("\n", [
                'Refusing to run Infection on itself with no memory limit set: it is dangerous.',
                'To run this test with a memory limit set please use:',
                'php -dmemory_limit=128M vendor/bin/phpunit --group=large',
            ]));
        }

        $output = $this->runInfection(self::EXPECT_SUCCESS, [
            '--test-framework-options=--exclude-group=' . self::EXCLUDED_GROUP,
        ]);

        $this->assertMatchesRegularExpression('/\d+ mutations were generated/', $output);
        $this->assertMatchesRegularExpression('/\d{2,} mutants were killed/', $output);
    }

    public function test_it_runs_configure_command_if_no_configuration(): void
    {
        chdir('tests/e2e/Unconfigured/');

        $output = $this->runInfection(self::EXPECT_ERROR);

        $this->assertStringContainsString(ConfigureCommand::NONINTERACTIVE_MODE_ERROR, $output);
    }

    #[DataProvider('e2eTestSuiteDataProvider')]
    #[RunInSeparateProcess]
    public function test_it_runs_an_e2e_test_with_success(string $fullPath): void
    {
        $this->runOnE2EFixture($fullPath);
    }

    public static function e2eTestSuiteDataProvider(): iterable
    {
        $directories = Finder::create()
            ->depth('== 0')
            ->in(__DIR__ . '/../../e2e/')
            ->directories();

        foreach ($directories as $dirName) {
            if (file_exists($dirName . '/run_tests.bash')) {
                // skipping non-standard tests
                // specifically Memory_Limit - it is very slow to fail
                continue;
            }

            yield basename((string) $dirName) => [(string) $dirName];
        }
    }

    private function runOnE2EFixture($path): string
    {
        $this->assertDirectoryExists($path);
        chdir($path);

        $this->installComposerDeps();
        $output = $this->runInfection(self::EXPECT_SUCCESS);

        $this->assertMatchesRegularExpression('/You are running Infection with \w+ enabled./', $output);
        $this->assertMatchesRegularExpression('/\d+ mutations were generated/', $output);
        $this->assertMatchesRegularExpression('/\d+ mutants were killed/', $output);

        if (isset($_SERVER['GOLDEN'])) {
            copy('infection.log', 'expected-output.txt');
            $this->markTestSkipped('Saved golden output');
        }

        $expected = file_get_contents('expected-output.txt');
        $expected = str_replace("\n", PHP_EOL, $expected);

        $this->assertStringEqualsFile('infection.log', $expected, sprintf('%s/expected-output.txt is not same as infection.log (if that is OK, run GOLDEN=1 vendor/bin/phpunit)', getcwd()));

        return $output;
    }

    private function installComposerDeps(): void
    {
        if (!file_exists('composer.json')) {
            // Tests may have no deps to install
            return;
        }

        if (!file_exists('vendor/autoload.php')) {
            // Install deps only if there's none
            $this->assertNotEmpty(getenv('PATH') ?: getenv('Path'), 'E2E tests need a system composer installed, but it could not be found without a PATH set');

            try {
                $process = new Process([
                    (new ConcreteComposerExecutableFinder())->find(),
                    'install',
                ]);
                $process->setTimeout(300);
                $process->mustRun();
            } catch (ProcessTimedOutException $e) {
                /*
                 * Packagist is a free service and is not 100% reliable as one may guess, but
                 * since we run multiple same tests at once in different environments, if we
                 * occasionally skip a test for reasons we can't control, it should do no harm.
                 */
                if (self::$countFailingComposerInstall >= self::MAX_FAILING_COMPOSER_INSTALL) {
                    throw $e;
                }

                ++self::$countFailingComposerInstall;
                $this->markTestSkipped($e->getMessage());
            } catch (FinderException $e) {
                if (DIRECTORY_SEPARATOR !== '\\') {
                    throw $e;
                }

                // It is not our call to work around ComposerExecutableFinder's misbehavior on Windows
                $this->markTestIncomplete($e->getMessage());
            }
        }

        /*
         * Now we need to handle autoloading. This is important because Infection uses
         * reflection, and reflection should know how to autoload files. But best if
         * we could avoid autoloading non-essential to Infection files, especially
         * those from vendor folder, because they're likely to interfere by breaking
         * other tests and causing a debugging hell. If it breaks, it better be here.
         */

        /*
         * E2E tests are expected to follow PSR-0 or PSR-4.
         *
         * We exploit this to autoload only classes belonging to the test,
         * but not to vendored deps (so we don't need them here, but to run
         * a testing tool from an E2E test we need them all the same).
         */

        $loader = new ClassLoader();

        $map = require 'vendor/composer/autoload_psr4.php';

        // $vendorDir is normally defined inside autoload_psr4.php, but PHPStan
        // can't see there, so have to both tell it so, and verify that too
        $vendorDir ??= null;
        $this->assertNotEmpty($vendorDir, 'Unexpected autoload_psr4.php found: please confirm that all dependencies are installed correctly for this fixture.');

        foreach ($map as $namespace => $paths) {
            foreach ($paths as $path) {
                if (str_contains((string) $path, (string) $vendorDir)) {
                    // Skip known dependency from autoloading
                    continue 2;
                }
            }

            $loader->setPsr4($namespace, $paths);
        }

        $mapPsr0 = require 'vendor/composer/autoload_namespaces.php';

        foreach ($mapPsr0 as $namespace => $paths) {
            foreach ($paths as $path) {
                if (str_contains((string) $path, (string) $vendorDir)) {
                    // Skip known dependency from autoloading
                    continue 2;
                }
            }

            $loader->set($namespace, $paths);
        }

        $loader->register($prepend = false); // Note: not prepending, but appending to our autoloader

        $this->previousLoader = $loader;

        /*
         * Another way to handle this is to append a new autoloader to the stock autoloader.
         *
         * By default this autoloader gets registered prepended to previously defined
         * autoloaders, but we can make our old autoloader to have preference:
         *
         * $this->previousLoader = include 'vendor/autoload.php';
         * $this->previousLoader->unregister();
         * $this->previousLoader->register(false);
         *
         * But this way vendored deps can still stick through, breaking other tests.
         * That could be a major debugging headache. If this test gets broked because
         * of missing deps, it better be this test, not something else.
         *
         * Yet another way is to declare E2E test classes in the autoload-dev in the
         * main composer.json, but that's not only ugly but will also require maintenance.
         */
    }

    private function runInfection(int $expectedExitCode, array $argvExtra = []): string
    {
        if (!extension_loaded('xdebug') && PHP_SAPI !== 'phpdbg') {
            $this->markTestSkipped("Infection from within PHPUnit won't run without Xdebug or PHPDBG");
        }

        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test can be unstable on Windows');
        }

        /*
         * @see https://github.com/sebastianbergmann/php-code-coverage/blob/7743bbcfff2a907e9ee4a25be13d0f8ec5e73800/src/Driver/PHPDBG.php#L24
         */
        if (PHP_SAPI === 'phpdbg' && !function_exists('phpdbg_start_oplog')) {
            $this->markTestIncomplete('This build of PHPDBG does not support code coverage');
        }

        $container = SingletonContainer::getContainer();
        $input = new ArgvInput(array_merge([
            'bin/infection',
            'run',
            '--verbose',
            '--no-interaction',
            '--logger-github=false',
        ], $argvExtra));

        $output = new BufferedOutput();

        $application = new Application($container);
        $application->setAutoExit(false);
        $exitCode = $application->run($input, $output);

        $outputText = $output->fetch();

        // Leaving window open to negative tests (e.g. where Infection is expected to fail)
        $this->assertSame(
            $expectedExitCode,
            $exitCode,
            <<<EOF
                Unexpected exit code. Command output was:
                ---
                $outputText
                --- end of output

                EOF,
        );

        return $outputText;
    }
}
