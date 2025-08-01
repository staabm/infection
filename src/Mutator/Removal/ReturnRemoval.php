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

namespace Infection\Mutator\Removal;

use Infection\Mutator\Definition;
use Infection\Mutator\GetMutatorName;
use Infection\Mutator\Mutator;
use Infection\Mutator\MutatorCategory;
use Infection\Mutator\Util\ReturnTypeHelper;
use PhpParser\Node;

/**
 * @internal
 *
 * @implements Mutator<Node\Stmt\Return_>
 */
final class ReturnRemoval implements Mutator
{
    use GetMutatorName;

    public static function getDefinition(): Definition
    {
        return new Definition(
            'Removes a return statement.',
            MutatorCategory::SEMANTIC_REDUCTION,
            null,
            <<<'DIFF'
                - return $foo;
                DIFF,
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @return iterable<Node\Stmt\Nop>
     */
    public function mutate(Node $node): iterable
    {
        yield new Node\Stmt\Nop();
    }

    public function canMutate(Node $node): bool
    {
        if (!$node instanceof Node\Stmt\Return_) {
            return false;
        }

        $returnHelper = new ReturnTypeHelper($node);

        // In void functions, any return statement can be removed
        if ($returnHelper->hasVoidReturnType()) {
            return true;
        }

        // Check if there's a non-void return type defined
        if ($returnHelper->hasSpecificReturnType()) {
            // For functions with return types, we can remove it only if there's more after this return
            return $returnHelper->hasNextStmtNode();
        }

        // For functions without return types, we can only remove the return if:
        // 1. There's another statement after it, OR
        if ($returnHelper->hasNextStmtNode()) {
            return true;
        }

        // 2. It returns a non-null value (not `return;` or `return null;`)
        return !$returnHelper->isNullReturn();
    }
}
