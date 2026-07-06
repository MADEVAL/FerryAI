<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Tests\Unit;

use FerryAI\Embedding\Pooling\ClsPooling;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClsPooling::class)]
final class ClsPoolingTest extends TestCase
{
    public function testNameReturnsCls(): void
    {
        $pooling = new ClsPooling();
        self::assertSame('cls', $pooling->name());
    }

    public function testPoolReturnsFirstTokenVector(): void
    {
        $pooling = new ClsPooling();
        $hiddenStates = [
            [0.1, 0.2, 0.3],
            [0.4, 0.5, 0.6],
            [0.7, 0.8, 0.9],
        ];

        $result = $pooling->pool($hiddenStates);

        self::assertSame([0.1, 0.2, 0.3], $result);
    }

    public function testPoolReturnsEmptyForEmptyInput(): void
    {
        $pooling = new ClsPooling();

        self::assertSame([], $pooling->pool([]));
    }

    public function testPoolIgnoresAttentionMask(): void
    {
        $pooling = new ClsPooling();
        $hiddenStates = [
            [1.0, 2.0],
            [3.0, 4.0],
        ];
        $attentionMask = [[0, 1]];

        $result = $pooling->pool($hiddenStates, $attentionMask);

        self::assertSame([1.0, 2.0], $result);
    }
}
