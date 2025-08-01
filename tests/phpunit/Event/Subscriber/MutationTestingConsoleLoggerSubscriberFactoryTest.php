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

use Infection\Differ\DiffColorizer;
use Infection\Event\Subscriber\MutationTestingConsoleLoggerSubscriber;
use Infection\Event\Subscriber\MutationTestingConsoleLoggerSubscriberFactory;
use Infection\Logger\FederatedLogger;
use Infection\Metrics\MetricsCalculator;
use Infection\Metrics\ResultsCollector;
use Infection\Tests\Fixtures\Console\FakeOutputFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(MutationTestingConsoleLoggerSubscriberFactory::class)]
final class MutationTestingConsoleLoggerSubscriberFactoryTest extends TestCase
{
    /**
     * @var MetricsCalculator|MockObject
     */
    private $metricsCalculatorMock;

    /**
     * @var ResultsCollector|MockObject
     */
    private $resultsCollectorMock;

    /**
     * @var DiffColorizer|MockObject
     */
    private $diffColorizerMock;

    protected function setUp(): void
    {
        $this->metricsCalculatorMock = $this->createMock(MetricsCalculator::class);
        $this->metricsCalculatorMock
            ->expects($this->never())
            ->method($this->anything())
        ;

        $this->resultsCollectorMock = $this->createMock(ResultsCollector::class);
        $this->resultsCollectorMock
            ->expects($this->never())
            ->method($this->anything())
        ;

        $this->diffColorizerMock = $this->createMock(DiffColorizer::class);
        $this->diffColorizerMock
            ->expects($this->never())
            ->method($this->anything())
        ;
    }

    #[DataProvider('showMutationsProvider')]
    public function test_it_creates_a_subscriber(?int $numberOfShownMutations): void
    {
        $factory = new MutationTestingConsoleLoggerSubscriberFactory(
            $this->metricsCalculatorMock,
            $this->resultsCollectorMock,
            $this->diffColorizerMock,
            new FederatedLogger(),
            $numberOfShownMutations,
            new FakeOutputFormatter(),
        );

        $outputMock = $this->createMock(OutputInterface::class);
        $outputMock
            ->method('isDecorated')
            ->willReturn(false)
        ;

        $subscriber = $factory->create($outputMock);

        $this->assertInstanceOf(MutationTestingConsoleLoggerSubscriber::class, $subscriber);
    }

    public static function showMutationsProvider(): iterable
    {
        foreach ([20, 0, null] as $showMutations) {
            yield [$showMutations];
        }
    }
}
