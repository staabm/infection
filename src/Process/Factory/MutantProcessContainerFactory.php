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

namespace Infection\Process\Factory;

use Infection\AbstractTestFramework\TestFrameworkAdapter;
use Infection\Mutant\Mutant;
use Infection\Mutant\TestFrameworkMutantExecutionResultFactory;
use Infection\Process\MutantProcess;
use Infection\Process\MutantProcessContainer;
use function min;
use Symfony\Component\Process\Process;

/**
 * @internal
 * @final
 */
class MutantProcessContainerFactory
{
    private const TIMEOUT_FACTOR = 5;

    private const TEST_FRAMEWORK_BOOTSTRAP_THRESHOLD = 5;

    public function __construct(
        private readonly TestFrameworkAdapter $testFrameworkAdapter,
        private readonly float $timeout,
        private readonly TestFrameworkMutantExecutionResultFactory $mutantExecutionResultFactory,
        /**
         * @var list<LazyMutantProcessFactory>
         */
        private readonly array $lazyMutantProcessCreators,
    ) {
    }

    public function create(Mutant $mutant, string $testFrameworkExtraOptions = ''): MutantProcessContainer
    {
        // getNominalTestExecutionTime() returns the time the test-suite requires to run the test, excluding process creation and test-framework bootstrapping.
        $timeout = min(self::TEST_FRAMEWORK_BOOTSTRAP_THRESHOLD + (self::TIMEOUT_FACTOR * $mutant->getMutation()->getNominalTestExecutionTime()), $this->timeout);

        $process = new Process(
            command: $this->testFrameworkAdapter->getMutantCommandLine(
                $mutant->getTests(),
                $mutant->getFilePath(),
                $mutant->getMutation()->getHash(),
                $mutant->getMutation()->getOriginalFilePath(),
                $testFrameworkExtraOptions,
            ),
            timeout: $timeout,
        );

        return new MutantProcessContainer(
            new MutantProcess(
                $process,
                $mutant,
                $this->mutantExecutionResultFactory,
            ),
            $this->lazyMutantProcessCreators,
        );
    }
}
