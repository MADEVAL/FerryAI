<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Tests\Unit;

use FerryAI\Embedding\Pooling\EosPooling;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EosPooling::class)]
final class EosPoolingTest extends TestCase
{
    public function testNameReturnsEos(): void
    {
        $pooling = new EosPooling();
        self::assertSame('eos', $pooling->name());
    }

    public function testPoolReturnsLastTokenVector(): void
    {
        $pooling = new EosPooling();
        $hiddenStates = [
            [0.1, 0.2, 0.3],
            [0.4, 0.5, 0.6],
            [0.7, 0.8, 0.9],
        ];

        $result = $pooling->pool($hiddenStates);

        self::assertSame([0.7, 0.8, 0.9], $result);
    }

    public function testPoolIgnoresAttentionMask(): void
    {
        $pooling = new EosPooling();
        $hiddenStates = [
            [1.0, 2.0],
            [3.0, 4.0],
        ];
        $attentionMask = [[1, 0]];

        $result = $pooling->pool($hiddenStates, $attentionMask);

        self::assertSame([3.0, 4.0], $result);
    }
}
