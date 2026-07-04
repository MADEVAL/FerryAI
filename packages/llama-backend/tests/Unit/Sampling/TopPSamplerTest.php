<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Sampling\TopPSampler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopPSampler::class)]
final class TopPSamplerTest extends TestCase
{
    public function testNarrowNucleusSelectsTopToken(): void
    {
        $sampler = new TopPSampler();

        self::assertSame(2, $sampler->sample([0.0, 1.0, 8.0, 0.5], new SamplingParams(topP: 0.01)));
    }

    public function testDeterministicWithSeed(): void
    {
        $sampler = new TopPSampler();
        $logits = [1.0, 2.0, 3.0, 2.5];

        $a = $sampler->sample($logits, new SamplingParams(topP: 0.9, seed: 99));
        $b = $sampler->sample($logits, new SamplingParams(topP: 0.9, seed: 99));

        self::assertSame($a, $b);
    }
}
