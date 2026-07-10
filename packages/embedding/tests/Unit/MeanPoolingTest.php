<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Tests\Unit;

use FerryAI\Embedding\Pooling\MeanPooling;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeanPooling::class)]
final class MeanPoolingTest extends TestCase
{
    public function testNameReturnsMean(): void
    {
        $pooling = new MeanPooling();
        self::assertSame('mean', $pooling->name());
    }

    public function testPoolAveragesAllTokens(): void
    {
        $pooling = new MeanPooling();
        $hiddenStates = [
            [0.0, 2.0],
            [2.0, 4.0],
        ];

        $result = $pooling->pool($hiddenStates);

        self::assertSame([1.0, 3.0], $result);
    }

    public function testPoolWithAttentionMaskIgnoresPadding(): void
    {
        $pooling = new MeanPooling();
        $hiddenStates = [
            [1.0, 2.0],
            [3.0, 4.0],
            [5.0, 6.0],
        ];
        $attentionMask = [[1, 1, 0]];

        $result = $pooling->pool($hiddenStates, $attentionMask);

        self::assertSame([2.0, 3.0], $result);
    }

    public function testPoolWithFullyMaskedReturnsZeros(): void
    {
        $pooling = new MeanPooling();
        $hiddenStates = [
            [1.0, 2.0],
        ];
        $attentionMask = [[0]];

        $result = $pooling->pool($hiddenStates, $attentionMask);

        self::assertSame([0.0, 0.0], $result);
    }

    public function testPoolSingleToken(): void
    {
        $pooling = new MeanPooling();
        $hiddenStates = [
            [3.0, 5.0],
        ];

        $result = $pooling->pool($hiddenStates);

        self::assertSame([3.0, 5.0], $result);
    }
}
