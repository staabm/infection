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

use Infection\PhpParser\Visitor\ReflectionVisitor;
use Infection\Reflection\AnonymousClassReflection;
use Infection\Reflection\ClassReflection;
use Infection\Reflection\NullReflection;
use InfectionReflectionAnonymousClass\Bug;
use InfectionReflectionAnonymousClass\Bug2;
use InfectionReflectionClassMethod\Foo;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[CoversClass(ReflectionVisitor::class)]
final class ReflectionVisitorTest extends BaseVisitorTestCase
{
    /**
     * @var NodeVisitor&object{isInsideFunction: bool}
     */
    private NodeVisitor $spyVisitor;

    protected function setUp(): void
    {
        $this->spyVisitor = $this->getInsideFunctionSpyVisitor();
    }

    #[DataProvider('isPartOfSignatureFlagProvider')]
    public function test_it_marks_nodes_which_are_part_of_the_function_signature(string $nodeClass, bool $expected): void
    {
        $nodes = self::parseCode(
            $this->getFileContent('Reflection/rv-part-of-signature-flag.php'),
        );

        $this->traverse(
            $nodes,
            [
                new ParentConnectingVisitor(),
                self::createNameResolver(),
                new ReflectionVisitor(),
                $spyVisitor = $this->getPartOfSignatureSpyVisitor($nodeClass),
            ],
        );

        $this->assertSame($expected, $spyVisitor->isPartOfSignature);
    }

    #[DataProvider('isPartOfSignatureFlagWithAttributesProvider')]
    public function test_it_marks_nodes_which_are_part_of_the_function_signature_with_attributes(string $nodeClass, bool $expected): void
    {
        $code = $this->getFileContent('Reflection/rv-part-of-signature-flag-with-attributes.php');

        $spyVisitor = $this->getPartOfSignatureSpyVisitor($nodeClass);

        $this->parseAndTraverse($code, $spyVisitor);

        $this->assertSame($expected, $spyVisitor->isPartOfSignature);
    }

    public function test_it_detects_if_it_is_traversing_inside_class_method(): void
    {
        $code = $this->getFileContent('Reflection/rv-inside-class-method.php');

        $this->parseAndTraverse($code);

        $this->assertTrue($this->spyVisitor->isInsideFunction);
    }

    public function test_it_does_not_traverse_a_regular_global_or_namespaced_function(): void
    {
        $code = $this->getFileContent('Reflection/rv-inside-function.php');

        $this->parseAndTraverse($code);

        $this->assertFalse($this->spyVisitor->isInsideFunction);
    }

    public function test_it_traverses_a_plain_function_inside_a_class(): void
    {
        $code = $this->getFileContent('Reflection/rv-inside-plain-function-in-class.php');

        $spyVisitor = $this->getSpyVisitor(Node\Expr\FuncCall::class);

        $this->parseAndTraverse($code, $spyVisitor);

        $this->assertTrue($spyVisitor->spyCalled);
    }

    public function test_it_traverses_a_plain_function_inside_a_closure(): void
    {
        $code = $this->getFileContent('Reflection/rv-inside-plain-function-in-closure.php');

        $spyVisitor = $this->getSpyVisitor(Node\Expr\FuncCall::class);

        $this->parseAndTraverse($code, $spyVisitor);

        $this->assertTrue($spyVisitor->spyCalled);
    }

    public function test_it_detects_if_it_is_traversing_inside_a_closure(): void
    {
        $code = $this->getFileContent('Reflection/rv-inside-closure.php');

        $this->parseAndTraverse($code);

        $this->assertTrue($this->spyVisitor->isInsideFunction);
    }

    public function test_it_does_not_add_the_inside_function_flag_if_not_necessary(): void
    {
        $code = $this->getFileContent('Reflection/rv-without-function.php');

        $this->parseAndTraverse($code);

        $this->assertFalse($this->spyVisitor->isInsideFunction);
    }

    public function test_it_can_mark_nodes_as_inside_function_for_an_anonymous_class(): void
    {
        $code = $this->getFileContent('Reflection/rv-anonymous-class.php');

        $this->parseAndTraverse($code);

        $this->assertTrue($this->spyVisitor->isInsideFunction);
    }

    public function test_it_sets_reflection_class_to_nodes(): void
    {
        $code = $this->getFileContent('Reflection/rv-inside-class-method.php');

        $reflectionSpyVisitor = $this->getReflectionClassSpyVisitor();

        $this->parseAndTraverse($code, $reflectionSpyVisitor);

        $this->assertInstanceOf(ClassReflection::class, $reflectionSpyVisitor->reflectionClass);
        $this->assertSame(Foo::class, $reflectionSpyVisitor->reflectionClass->getName());
    }

    public function test_it_sets_reflection_class_to_nodes_in_anonymous_class(): void
    {
        $code = $this->getFileContent('Reflection/rv-anonymous-class-inside-class.php');

        $reflectionSpyVisitor = $this->getReflectionClassesSpyVisitor();

        $this->parseAndTraverse($code, $reflectionSpyVisitor);

        $this->assertInstanceOf(NullReflection::class, $reflectionSpyVisitor->fooReflectionClass);

        $this->assertInstanceOf(ClassReflection::class, $reflectionSpyVisitor->createAnonymousClassReflectionClass);
        $this->assertSame(Bug::class, $reflectionSpyVisitor->createAnonymousClassReflectionClass->getName());
    }

    public function test_it_sets_reflection_class_to_nodes_in_anonymous_class_that_extends(): void
    {
        $code = $this->getFileContent('Reflection/rv-anonymous-class-inside-class-that-extends.php');

        $reflectionSpyVisitor = $this->getReflectionClassesSpyVisitor();

        $this->parseAndTraverse($code, $reflectionSpyVisitor);

        $this->assertInstanceOf(AnonymousClassReflection::class, $reflectionSpyVisitor->fooReflectionClass);

        $this->assertInstanceOf(ClassReflection::class, $reflectionSpyVisitor->createAnonymousClassReflectionClass);
        $this->assertSame(Bug2::class, $reflectionSpyVisitor->createAnonymousClassReflectionClass->getName());
    }

    public static function isPartOfSignatureFlagProvider(): iterable
    {
        yield [Node\Stmt\ClassMethod::class, true];

        yield [Node\Param::class, true];                    // $param

        yield [Node\Scalar\DNumber::class, true];           // 2.0

        yield [Node\Scalar\LNumber::class, false];          // 1

        yield [Node\Expr\BinaryOp\Identical::class, false]; // ===

        yield [Node\Arg::class, false];

        yield [Node\Expr\Array_::class, false];             // []
    }

    public static function isPartOfSignatureFlagWithAttributesProvider(): iterable
    {
        yield [Node\Stmt\ClassMethod::class, true];

        yield [Node\Param::class, true];                    // $param

        yield [Node\Expr\ConstFetch::class, true];          // false

        yield [Node\Scalar\DNumber::class, true];           // 2.0

        yield [Node\Scalar\LNumber::class, false];          // 1

        yield [Node\Expr\BinaryOp\Identical::class, false]; // ===

        yield [Node\Arg::class, false];

        yield [Node\Expr\Array_::class, false];             // []
    }

    /**
     * @return NodeVisitor&object{isPartOfSignature: bool}
     */
    private function getPartOfSignatureSpyVisitor(string $nodeClass): NodeVisitor
    {
        return new class($nodeClass) extends NodeVisitorAbstract {
            public bool $isPartOfSignature;

            public function __construct(
                private readonly string $nodeClassUnderTest,
            ) {
            }

            public function leaveNode(Node $node): void
            {
                if ($node instanceof $this->nodeClassUnderTest) {
                    $this->isPartOfSignature = $node->getAttribute(ReflectionVisitor::IS_ON_FUNCTION_SIGNATURE, false);
                }
            }
        };
    }

    /**
     * @return NodeVisitor&object{spyCalled: bool}
     */
    private function getSpyVisitor(string $nodeClass): NodeVisitor
    {
        return new class($nodeClass) extends NodeVisitorAbstract {
            public bool $spyCalled = false;

            public function __construct(
                private readonly string $nodeClassUnderTest,
            ) {
            }

            public function leaveNode(Node $node): void
            {
                if ($node instanceof $this->nodeClassUnderTest) {
                    $this->spyCalled = true;
                }
            }
        };
    }

    /**
     * @return NodeVisitor&object{isInsideFunction: bool}
     */
    private function getInsideFunctionSpyVisitor(): NodeVisitor
    {
        return new class extends NodeVisitorAbstract {
            public bool $isInsideFunction = false;

            public function enterNode(Node $node): ?int
            {
                if ($node->hasAttribute(ReflectionVisitor::IS_INSIDE_FUNCTION_KEY)) {
                    $this->isInsideFunction = true;

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }

                return null;
            }
        };
    }

    /**
     * @return NodeVisitor&object{reflectionClass: ClassReflection|null}
     */
    private function getReflectionClassSpyVisitor(): NodeVisitor
    {
        return new class extends NodeVisitorAbstract {
            public ?ClassReflection $reflectionClass = null;

            public function enterNode(Node $node): ?int
            {
                if ($node->hasAttribute(ReflectionVisitor::REFLECTION_CLASS_KEY)) {
                    $this->reflectionClass = $node->getAttribute(ReflectionVisitor::REFLECTION_CLASS_KEY);

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }

                return null;
            }
        };
    }

    /**
     * @return NodeVisitor&object{fooReflectionClass: ClassReflection|null, createAnonymousClassReflectionClass: ClassReflection|null}
     */
    private function getReflectionClassesSpyVisitor(): NodeVisitor
    {
        return new class extends NodeVisitorAbstract {
            public ?ClassReflection $fooReflectionClass = null;

            public ?ClassReflection $createAnonymousClassReflectionClass = null;

            public function enterNode(Node $node): ?int
            {
                $name = $node->getAttribute(ReflectionVisitor::FUNCTION_NAME);

                if ($name === 'foo') {
                    $this->fooReflectionClass = $node->getAttribute(ReflectionVisitor::REFLECTION_CLASS_KEY);

                    return NodeTraverser::DONT_TRAVERSE_CHILDREN;
                }

                return null;
            }

            public function leaveNode(Node $node): void
            {
                $name = $node->getAttribute(ReflectionVisitor::FUNCTION_NAME);

                if ($name === 'createAnonymousClass') {
                    $this->createAnonymousClassReflectionClass = $node->getAttribute(ReflectionVisitor::REFLECTION_CLASS_KEY);
                }
            }
        };
    }

    private function parseAndTraverse(string $code, ?NodeVisitor $nodeVisitor = null): void
    {
        $nodes = self::parseCode($code);

        $this->traverse(
            $nodes,
            [
                self::createNameResolver(),
                new ParentConnectingVisitor(),
                new ReflectionVisitor(),
                $nodeVisitor ?: $this->spyVisitor,
            ],
        );
    }

    private static function createNameResolver(): NameResolver
    {
        return new NameResolver(null, [
            'preserveOriginalNames' => true,
            'replaceNodes' => false,
        ]);
    }
}
