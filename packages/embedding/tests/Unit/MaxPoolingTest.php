<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Tests\Unit;

use FerryAI\Embedding\Pooling\MaxPooling;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MaxPooling::class)]
final class MaxPoolingTest extends TestCase
{
    public function testNameReturnsMax(): void
    {
        $pooling = new MaxPooling();
        self::assertSame('max', $pooling->name());
    }

    public function testPoolReturnsElementWiseMax(): void
    {
        $pooling = new MaxPooling();
        $hiddenStates = [
            [1.0, 5.0, 3.0],
            [4.0, 2.0, 6.0],
            [2.0, 8.0, 1.0],
        ];

        $result = $pooling->pool($hiddenStates);

        self::assertSame([4.0, 8.0, 6.0], $result);
    }

    public function testPoolIgnoresAttentionMask(): void
    {
        $pooling = new MaxPooling();
        $hiddenStates = [
            [10.0, 1.0],
            [5.0, 0.5],
        ];
        $attentionMask = [[0, 1]];

        $result = $pooling->pool($hiddenStates, $attentionMask);

        self::assertSame([10.0, 1.0], $result);
    }

    public function testPoolSingleToken(): void
    {
        $pooling = new MaxPooling();
        $hiddenStates = [
            [7.0, 3.0],
        ];

        $result = $pooling->pool($hiddenStates);

        self::assertSame([7.0, 3.0], $result);
    }

    public function testPoolWithNegativeValues(): void
    {
        $pooling = new MaxPooling();
        $hiddenStates = [
            [-5.0, -1.0],
            [-3.0, -8.0],
        ];

        $result = $pooling->pool($hiddenStates);

        self::assertSame([-3.0, -1.0], $result);
    }
}
