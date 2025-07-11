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

namespace Infection\Metrics;

use function array_is_list;
use Infection\Mutant\MutantExecutionResult;
use LogicException;
use function usort;

/**
 * @internal
 */
final class SortableMutantExecutionResults
{
    /**
     * @var MutantExecutionResult[]
     */
    private array $executionResults = [];

    private bool $sorted = false;

    public function add(MutantExecutionResult $executionResult): void
    {
        $this->executionResults[] = $executionResult;
        $this->sorted = false;
    }

    /**
     * @return list<MutantExecutionResult>
     */
    public function getSortedExecutionResults(): array
    {
        if (!$this->sorted) {
            self::sortResults($this->executionResults);
            $this->sorted = true;
        }

        if (!array_is_list($this->executionResults)) {
            throw new LogicException('Execution results are not sorted');
        }

        return $this->executionResults;
    }

    /**
     * @param MutantExecutionResult[] $executionResults
     * @param-out list<MutantExecutionResult> $executionResults
     */
    private static function sortResults(array &$executionResults): void
    {
        usort(
            $executionResults,
            static function (MutantExecutionResult $a, MutantExecutionResult $b): int {
                if ($a->getOriginalFilePath() === $b->getOriginalFilePath()) {
                    return $a->getOriginalStartingLine() <=> $b->getOriginalStartingLine();
                }

                return $a->getOriginalFilePath() <=> $b->getOriginalFilePath();
            },
        );
    }
}
