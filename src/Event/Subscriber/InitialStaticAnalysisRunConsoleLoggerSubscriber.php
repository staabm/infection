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

namespace Infection\Event\Subscriber;

use Infection\Event\InitialStaticAnalysisRunWasFinished;
use Infection\Event\InitialStaticAnalysisRunWasStarted;
use Infection\Event\InitialStaticAnalysisSubStepWasCompleted;
use Infection\StaticAnalysis\StaticAnalysisToolAdapter;
use InvalidArgumentException;
use const PHP_EOL;
use function sprintf;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final readonly class InitialStaticAnalysisRunConsoleLoggerSubscriber implements EventSubscriber
{
    private ProgressBar $progressBar;

    public function __construct(
        private StaticAnalysisToolAdapter $staticAnalysisToolAdapter,
        private OutputInterface $output,
        private bool $debug,
    ) {
        $this->progressBar = new ProgressBar($this->output);
        $this->progressBar->setFormat('verbose');
    }

    public function onInitialStaticAnalysisRunWasStarted(InitialStaticAnalysisRunWasStarted $event): void
    {
        try {
            $version = $this->staticAnalysisToolAdapter->getVersion();
        } catch (InvalidArgumentException) {
            $version = 'unknown';
        }

        $this->output->writeln([
            '',
            '',
            'Running initial Static Analysis...',
            '',
            sprintf(
                '%s version: %s',
                $this->staticAnalysisToolAdapter->getName(),
                $version,
            ),
            '',
        ]);
        $this->progressBar->start();
    }

    public function onInitialStaticAnalysisRunWasFinished(InitialStaticAnalysisRunWasFinished $event): void
    {
        $this->progressBar->finish();

        if ($this->debug) {
            $this->output->writeln(PHP_EOL . $event->getOutputText());
        }
    }

    public function onInitialStaticAnalysisSubStepWasCompleted(InitialStaticAnalysisSubStepWasCompleted $event): void
    {
        $this->progressBar->advance();
    }
}
