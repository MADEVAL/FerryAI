<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Embedding\Pooling\EosPooling;
use FerryAI\Embedding\Pooling\MaxPooling;
use FerryAI\Embedding\Pooling\MeanPooling;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: every pooling strategy must honour the attention mask per the
 * PoolingStrategy contract (1 = real token, 0 = padding). Previously Max and Eos ignored it,
 * folding padding positions into the embedding.
 */
#[CoversNothing]
final class PoolingMaskConsistencyTest extends TestCase
{
    public function testMaxPoolingSkipsMaskedPositions(): void
    {
        // Token 1 ([9,2]) is padding; only token 0 ([1,5]) counts.
        $result = (new MaxPooling())->pool([[1.0, 5.0], [9.0, 2.0]], [[1, 0]]);

        self::assertSame([1.0, 5.0], $result);
    }

    public function testMaxPoolingWithoutMaskUsesAllPositions(): void
    {
        $result = (new MaxPooling())->pool([[1.0, 5.0], [9.0, 2.0]]);

        self::assertSame([9.0, 5.0], $result);
    }

    public function testMaxPoolingAllMaskedReturnsZeroVector(): void
    {
        $result = (new MaxPooling())->pool([[1.0, 5.0], [9.0, 2.0]], [[0, 0]]);

        self::assertSame([0.0, 0.0], $result);
    }

    public function testEosPoolingReturnsLastUnmaskedToken(): void
    {
        // Last real token is index 1 ([2,2]); index 2 ([3,3]) is padding.
        $result = (new EosPooling())->pool([[1.0, 1.0], [2.0, 2.0], [3.0, 3.0]], [[1, 1, 0]]);

        self::assertSame([2.0, 2.0], $result);
    }

    public function testEosPoolingWithoutMaskReturnsLastToken(): void
    {
        $result = (new EosPooling())->pool([[1.0, 1.0], [2.0, 2.0], [3.0, 3.0]]);

        self::assertSame([3.0, 3.0], $result);
    }

    public function testMeanPoolingStillHonoursMask(): void
    {
        // Only token 0 ([2,4]) counts; token 1 is padding.
        $result = (new MeanPooling())->pool([[2.0, 4.0], [8.0, 8.0]], [[1, 0]]);

        self::assertSame([2.0, 4.0], $result);
    }
}
