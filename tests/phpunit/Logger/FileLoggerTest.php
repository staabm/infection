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

namespace Infection\Tests\Logger;

use Infection\Logger\FileLogger;
use Infection\Tests\FileSystem\FileSystemTestCase;
use Infection\Tests\Fixtures\Logger\DummyLineMutationTestingResultsLogger;
use const PHP_EOL;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LogLevel;
use function str_replace;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class FileLoggerTest extends FileSystemTestCase
{
    private const LOG_FILE_PATH = '/path/to/text.log';

    /**
     * @var Filesystem|MockObject
     */
    private $fileSystemMock;

    /**
     * @var DummyLogger
     */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystemMock = $this->createMock(Filesystem::class);
        $this->logger = new DummyLogger();
    }

    public function test_it_logs_the_correct_lines_with_no_mutations(): void
    {
        $expectedContent = <<<'TXT'
            foo
            bar
            TXT;

        $expectedContent = str_replace("\n", PHP_EOL, $expectedContent);

        $this->fileSystemMock
            ->expects($this->once())
            ->method('dumpFile')
            ->with(self::LOG_FILE_PATH, $expectedContent)
        ;

        $debugFileLogger = new FileLogger(
            self::LOG_FILE_PATH,
            $this->fileSystemMock,
            new DummyLineMutationTestingResultsLogger(['foo', 'bar']),
            $this->logger,
        );

        $debugFileLogger->log();

        $this->assertSame([], $this->logger->getLogs());
    }

    public function test_it_can_log_on_valid_streams(): void
    {
        $debugFileLogger = new FileLogger(
            'php://stdout',
            $this->fileSystemMock,
            new DummyLineMutationTestingResultsLogger([]),
            $this->logger,
        );

        $debugFileLogger->log();

        $this->assertSame([], $this->logger->getLogs());
    }

    public function test_it_cannot_log_on_invalid_streams(): void
    {
        $debugFileLogger = new FileLogger(
            'php://memory',
            $this->fileSystemMock,
            new DummyLineMutationTestingResultsLogger(['foo', 'bar']),
            $this->logger,
        );

        $debugFileLogger->log();

        $this->assertSame(
            [
                [
                    LogLevel::ERROR,
                    '<error>The only streams supported are "php://stdout" and "php://stderr". Got "php://memory"</error>',
                    [],
                ],
            ],
            $this->logger->getLogs(),
        );
    }

    public function test_it_fails_if_cannot_write_file(): void
    {
        $this->fileSystemMock
            ->expects($this->once())
            ->method('dumpFile')
            ->with(self::LOG_FILE_PATH, $this->anything())
            ->willThrowException(new IOException('Cannot write in directory X'));

        $debugFileLogger = new FileLogger(
            self::LOG_FILE_PATH,
            $this->fileSystemMock,
            new DummyLineMutationTestingResultsLogger([]),
            $this->logger,
        );

        $debugFileLogger->log();

        $this->assertSame(
            [
                [
                    LogLevel::ERROR,
                    '<error>Cannot write in directory X</error>',
                    [],
                ],
            ],
            $this->logger->getLogs(),
        );
    }
}
