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

    public function testDeterministicWithSeed(): void
    {
        $sampler = new TopKSampler();
        $logits = [1.0, 2.0, 3.0, 0.5];

        $a = $sampler->sample($logits, new SamplingParams(topK: 3, seed: 7));
        $b = $sampler->sample($logits, new SamplingParams(topK: 3, seed: 7));

        self::assertSame($a, $b);
    }
}
