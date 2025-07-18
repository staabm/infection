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

namespace Infection\Mutator\Boolean;

use Infection\Mutator\Definition;
use Infection\Mutator\MutatorCategory;
use Infection\Mutator\Util\AbstractAllSubExprNegation;
use Infection\Mutator\Util\NameResolver;
use function is_string;
use PhpParser\Node;
use ReflectionClass;
use ReflectionException;

/**
 * @internal
 *
 * @extends AbstractAllSubExprNegation<Node\Expr\BinaryOp\BooleanAnd>
 */
final class LogicalAndAllSubExprNegation extends AbstractAllSubExprNegation
{
    private ?string $seenVariabeName = null;

    public static function getDefinition(): Definition
    {
        return new Definition(
            <<<'TXT'
                Negates all sub-expressions of an AND expression (`&&`).
                TXT
            ,
            MutatorCategory::ORTHOGONAL_REPLACEMENT,
            null,
            <<<'DIFF'
                - $a = $b && $c;
                + $a = !$b && !$c;
                DIFF,
        );
    }

    protected function getSupportedBinaryOpExprClass(): string
    {
        return Node\Expr\BinaryOp\BooleanAnd::class;
    }

    protected function isSubConditionMutable(Node\Expr $node): bool
    {
        if (!parent::isSubConditionMutable($node)) {
            return false;
        }

        if (
            $node instanceof Node\Expr\BooleanNot
            && $node->expr instanceof Node\Expr\Instanceof_
            && $node->expr->expr instanceof Node\Expr\Variable
            && is_string($node->expr->expr->name)
            && $node->expr->class instanceof Node\Name
        ) {
            if ($this->seenVariabeName === null) {
                $this->seenVariabeName = $node->expr->expr->name;
            } else {
                if ($this->seenVariabeName !== $node->expr->expr->name) {
                    return true;
                }
            }

            $resolvedName = NameResolver::resolveName($node->expr->class);

            try {
                $reflectionClass = new ReflectionClass($resolvedName->name); // @phpstan-ignore argument.type

                if (!$reflectionClass->isInterface() && !$reflectionClass->isTrait()) {
                    return false;
                }
            } catch (ReflectionException) {
            }
        }

        return true;
    }
}
