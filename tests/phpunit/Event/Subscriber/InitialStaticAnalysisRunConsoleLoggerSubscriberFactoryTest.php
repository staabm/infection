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

namespace Infection\Tests\Event\Subscriber;

use Infection\Event\Subscriber\CiInitialStaticAnalysisRunConsoleLoggerSubscriber;
use Infection\Event\Subscriber\InitialStaticAnalysisRunConsoleLoggerSubscriber;
use Infection\Event\Subscriber\InitialStaticAnalysisRunConsoleLoggerSubscriberFactory;
use Infection\StaticAnalysis\StaticAnalysisToolAdapter;
use Infection\Tests\Fixtures\Console\FakeOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(InitialStaticAnalysisRunConsoleLoggerSubscriberFactory::class)]
final class InitialStaticAnalysisRunConsoleLoggerSubscriberFactoryTest extends TestCase
{
    private StaticAnalysisToolAdapter&MockObject $staticAnalysisToolAdapter;

    protected function setUp(): void
    {
        $this->staticAnalysisToolAdapter = $this->createMock(StaticAnalysisToolAdapter::class);
        $this->staticAnalysisToolAdapter
            ->expects($this->never())
            ->method($this->anything())
        ;
    }

    #[DataProvider('debugProvider')]
    public function test_it_creates_a_ci_subscriber_if_skips_the_progress_bar(bool $debug): void
    {
        $factory = new InitialStaticAnalysisRunConsoleLoggerSubscriberFactory(
            true,
            $debug,
            $this->staticAnalysisToolAdapter,
        );

        $subscriber = $factory->create(new FakeOutput());

        $this->assertInstanceOf(CiInitialStaticAnalysisRunConsoleLoggerSubscriber::class, $subscriber);
    }

    #[DataProvider('debugProvider')]
    public function test_it_creates_a_regular_subscriber_if_does_not_skip_the_progress_bar(bool $debug): void
    {
        $factory = new InitialStaticAnalysisRunConsoleLoggerSubscriberFactory(
            false,
            $debug,
            $this->staticAnalysisToolAdapter,
        );

        $outputMock = $this->createMock(OutputInterface::class);
        $outputMock
            ->method('isDecorated')
            ->willReturn(false)
        ;

        $subscriber = $factory->create($outputMock);

        $this->assertInstanceOf(InitialStaticAnalysisRunConsoleLoggerSubscriber::class, $subscriber);
    }

    public static function debugProvider(): iterable
    {
        yield 'debug enabled' => [true];

        yield 'debug disabled' => [false];
    }
}
