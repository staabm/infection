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

namespace Infection\Tests\Mutator\Boolean;

use Infection\Mutator\Boolean\ArrayItem;
use Infection\Testing\BaseMutatorTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ArrayItem::class)]
final class ArrayItemTest extends BaseMutatorTestCase
{
    /**
     * @param string|string[] $expected
     */
    #[DataProvider('mutationsProvider')]
    public function test_it_can_mutate(string $input, $expected = []): void
    {
        $this->assertMutatesInput($input, $expected);
    }

    public static function mutationsProvider(): iterable
    {
        yield 'It mutates double arrow operator to a greater than comparison when operands can have side-effects and left is property' => [
            <<<'PHP'
                <?php

                [$a->foo => $b->bar];
                PHP
            ,
            <<<'PHP'
                <?php

                [$a->foo > $b->bar];
                PHP
            ,
        ];

        yield 'It mutates double arrow operator to a greater than comparison when operands can have side-effects and right is null safe property' => [
            <<<'PHP'
                <?php

                [$a => $b?->bar];
                PHP
            ,
            <<<'PHP'
                <?php

                [$a > $b?->bar];
                PHP
            ,
        ];

        yield 'It mutates double arrow operator to a greater than comparison when operands can have side-effects and left is method call' => [
            <<<'PHP'
                <?php

                [$a->foo() => $b->bar()];
                PHP
            ,
            <<<'PHP'
                <?php

                [$a->foo() > $b->bar()];
                PHP
            ,
        ];

        yield 'It mutates double arrow operator to a greater than comparison when operands can have side-effects and right is null safe method call' => [
            <<<'PHP'
                <?php

                [$a => $b?->bar()];
                PHP
            ,
            <<<'PHP'
                <?php

                [$a > $b?->bar()];
                PHP
            ,
        ];

        yield 'It does not mutate double arrow operator to a greater than comparison when key is function call' => [
            <<<'PHP'
                <?php

                [foo() => $b];
                PHP
            ,
        ];

        yield 'It does not mutate double arrow operator to a greater than comparison when value is function call' => [
            <<<'PHP'
                <?php

                [$b => foo()];
                PHP
            ,
        ];

        yield 'It mutates double arrow operator to a greater than comparison when operands can have side-effects and right is property' => [
            <<<'PHP'
                <?php

                [$foo => $b->bar];
                PHP
            ,
            <<<'PHP'
                <?php

                [$foo > $b->bar];
                PHP
            ,
        ];

        yield 'It does not mutate arrays without double arrow operator' => [
            <<<'PHP'
                <?php

                [$b];
                PHP
            ,
        ];

        yield 'It does not mutate arrays when side-effects are not expected' => [
            <<<'PHP'
                <?php

                ['string' => 1];
                [true => false];
                [$a => $b];
                PHP
            ,
        ];
    }
}
