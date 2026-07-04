<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use FerryAI\LlamaBackend\Sampling\GrammarSampler;
use FerryAI\LlamaBackend\Sampling\GreedySampler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrammarSampler::class)]
final class GrammarSamplerTest extends TestCase
{
    public function testDelegatesSampling(): void
    {
        $grammar = GbnfGrammar::fromString('root ::= "a"');
        $sampler = new GrammarSampler($grammar, new GreedySampler());

        self::assertSame(2, $sampler->sample([0.1, 0.2, 0.9], new SamplingParams()));
    }

    public function testExposesGrammar(): void
    {
        $grammar = GbnfGrammar::fromString('root ::= "a"');

        self::assertSame($grammar, (new GrammarSampler($grammar))->grammar());
    }
}
