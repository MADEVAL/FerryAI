<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Sampling;

use FerryAI\Core\Exception\ValidationException;
use FerryAI\LlamaBackend\Sampling\SamplerMath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SamplerMath::class)]
final class SamplerMathTest extends TestCase
{
    public function testArgmax(): void
    {
        self::assertSame(1, SamplerMath::argmax([0.1, 0.9, 0.2]));
        self::assertSame(2, SamplerMath::argmax([-1.0, -0.5, 3.0]));
    }

    public function testArgmaxRejectsEmpty(): void
    {
        $this->expectException(ValidationException::class);

        SamplerMath::argmax([]);
    }

    public function testSoftmaxSumsToOne(): void
    {
        $probs = SamplerMath::softmax([1.0, 2.0, 3.0]);

        self::assertEqualsWithDelta(1.0, array_sum($probs), 1e-9);
        self::assertGreaterThan($probs[0], $probs[2]);
    }

    public function testWeightedIndexIsDeterministicWithSeed(): void
    {
        $probs = [0.2, 0.3, 0.5];

        $a = SamplerMath::weightedIndex($probs, SamplerMath::randomizer(123));
        $b = SamplerMath::weightedIndex($probs, SamplerMath::randomizer(123));

        self::assertSame($a, $b);
    }
}
