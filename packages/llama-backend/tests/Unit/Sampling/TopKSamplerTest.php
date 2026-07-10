<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Sampling\TopKSampler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopKSampler::class)]
final class TopKSamplerTest extends TestCase
{
    public function testTopOneEqualsArgmax(): void
    {
        $sampler = new TopKSampler();

        self::assertSame(1, $sampler->sample([0.1, 5.0, 0.2], new SamplingParams(topK: 1)));
    }

    public function testDeterministicSequenceWithSeedAndDiversity(): void
    {
        $logits = [1.0, 2.0, 3.0, 2.5];

        $first = new TopKSampler();
        $second = new TopKSampler();

        $seqA = [];
        $seqB = [];

        for ($i = 0; $i < 30; $i++) {
            $seqA[] = $first->sample($logits, new SamplingParams(topK: 4, seed: 7));
            $seqB[] = $second->sample($logits, new SamplingParams(topK: 4, seed: 7));
        }

        self::assertSame($seqA, $seqB);
        self::assertGreaterThan(1, \count(array_unique($seqA)));
    }
}
