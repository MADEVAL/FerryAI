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

    public function testDeterministicSequenceWithSeedAndDiversity(): void
    {
        $logits = [1.0, 2.0, 3.0, 2.5];

        $first = new TopPSampler();
        $second = new TopPSampler();

        $seqA = [];
        $seqB = [];

        for ($i = 0; $i < 30; $i++) {
            $seqA[] = $first->sample($logits, new SamplingParams(topP: 0.95, seed: 99));
            $seqB[] = $second->sample($logits, new SamplingParams(topP: 0.95, seed: 99));
        }

        // Reproducible: same seed => same sequence across independent samplers.
        self::assertSame($seqA, $seqB);
        // Non-degenerate: the RNG advances across tokens instead of re-seeding every call.
        self::assertGreaterThan(1, \count(array_unique($seqA)));
    }
}
