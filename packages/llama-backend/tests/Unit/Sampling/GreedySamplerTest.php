<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Sampling\GreedySampler;
use FerryAI\LlamaBackend\Sampling\Sampler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GreedySampler::class)]
final class GreedySamplerTest extends TestCase
{
    public function testSelectsHighestLogit(): void
    {
        $sampler = new GreedySampler();

        self::assertInstanceOf(Sampler::class, $sampler);
        self::assertSame(2, $sampler->sample([0.1, 0.5, 0.9, 0.2], new SamplingParams()));
    }
}
