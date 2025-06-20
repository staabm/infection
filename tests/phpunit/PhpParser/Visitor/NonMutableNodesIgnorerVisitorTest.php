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

namespace Infection\Tests\PhpParser\Visitor;

use Infection\PhpParser\Visitor\IgnoreNode\NodeIgnorer;
use Infection\PhpParser\Visitor\NonMutableNodesIgnorerVisitor;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[CoversClass(NonMutableNodesIgnorerVisitor::class)]
final class NonMutableNodesIgnorerVisitorTest extends BaseVisitorTestCase
{
    /**
     * @var NodeVisitor&object{nodesVisitedCount: int}
     */
    private NodeVisitor $spyVisitor;

    protected function setUp(): void
    {
        $this->spyVisitor = $this->getSpyVisitor();
    }

    public function test_it_does_not_traverse_after_ignore(): void
    {
        $this->parseAndTraverse(<<<'PHP'
            <?php

            class Foo
            {
                public function bar(): void
                {
                }
            }
            PHP
        );
        $this->assertSame(0, $this->spyVisitor->nodesVisitedCount);
    }

    /**
     * @return NodeVisitor&object{nodesVisitedCount: int}
     */
    private function getSpyVisitor(): NodeVisitor
    {
        return new class extends NodeVisitorAbstract {
            public int $nodesVisitedCount = 0;

            public function leaveNode(Node $node): void
            {
                ++$this->nodesVisitedCount;
            }
        };
    }

    private function parseAndTraverse(string $code): void
    {
        $nodes = self::parseCode($code);

        $this->traverse(
            $nodes,
            [
                new NonMutableNodesIgnorerVisitor([new class implements NodeIgnorer {
                    public function ignores(Node $node): bool
                    {
                        return true;
                    }
                }]),
                $this->spyVisitor,
            ],
        );
    }
}
