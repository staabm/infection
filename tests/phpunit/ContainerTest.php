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

namespace Infection\Tests;

use Infection\Container;
use Infection\FileSystem\Locator\FileNotFound;
use Infection\Testing\SingletonContainer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Output\NullOutput;

#[Group('integration')]
#[CoversClass(Container::class)]
final class ContainerTest extends TestCase
{
    public function test_it_can_be_instantiated_without_any_services(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown service "Infection\FileSystem\SourceFileFilter"');

        $container = new Container([]);

        $container->getSourceFileFilter();
    }

    public function test_it_can_build_simple_services_without_configuration(): void
    {
        $container = new Container([]);

        $container->getFileSystem();

        $this->addToAssertionCount(1);
    }

    public function test_it_can_resolve_some_dependencies_without_configuration(): void
    {
        $container = new Container([]);

        $container->getAdapterInstallationDecider();

        $this->addToAssertionCount(1);
    }

    public function test_it_can_resolve_all_dependencies_with_configuration(): void
    {
        $container = SingletonContainer::getContainer();

        $container->getSubscriberRegisterer();
        $container->getTestFrameworkFinder();

        $this->addToAssertionCount(1);
    }

    public function test_it_can_be_instantiated_with_the_project_services(): void
    {
        $container = SingletonContainer::getContainer();

        $container->getFileSystem();

        $this->addToAssertionCount(1);
    }

    public function test_it_can_build_lazy_source_file_data_factory_that_fails_on_use(): void
    {
        $newContainer = SingletonContainer::getContainer()->withValues(
            new NullLogger(),
            new NullOutput(),
            existingCoveragePath: '/path/to/coverage',
        );

        $traces = $newContainer->getUnionTraceProvider()->provideTraces();

        $this->expectException(FileNotFound::class);
        $this->expectExceptionMessage('Could not find any "index.xml" file in "/path/to/coverage"');

        foreach ($traces as $trace) {
            $this->fail();
        }
    }

    public function test_it_provides_a_friendly_error_when_attempting_to_configure_it_with_both_no_progress_and_force_progress(): void
    {
        $container = SingletonContainer::getContainer();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot force progress and set no progress at the same time');

        $container->withValues(
            new NullLogger(),
            new NullOutput(),
            noProgress: true,
            forceProgress: true,
        );
    }
}
