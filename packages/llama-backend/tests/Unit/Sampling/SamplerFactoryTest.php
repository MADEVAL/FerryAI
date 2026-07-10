<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Sampling;

use FerryAI\Core\Exception\ValidationException;
use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use FerryAI\LlamaBackend\Sampling\GrammarSampler;
use FerryAI\LlamaBackend\Sampling\GreedySampler;
use FerryAI\LlamaBackend\Sampling\SamplerFactory;
use FerryAI\LlamaBackend\Sampling\TopKSampler;
use FerryAI\LlamaBackend\Sampling\TopPSampler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SamplerFactory::class)]
final class SamplerFactoryTest extends TestCase
{
    private SamplerFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SamplerFactory();
    }

    public function testCreatesKnownSamplers(): void
    {
        self::assertInstanceOf(GreedySampler::class, $this->factory->create('greedy'));
        self::assertInstanceOf(TopPSampler::class, $this->factory->create('top_p'));
        self::assertInstanceOf(TopKSampler::class, $this->factory->create('top_k'));
    }

    public function testCreatesGrammarSampler(): void
    {
        $sampler = $this->factory->create('grammar', GbnfGrammar::fromString('root ::= "a"'));

        self::assertInstanceOf(GrammarSampler::class, $sampler);
    }

    public function testGrammarSamplerWithoutGrammarThrows(): void
    {
        $this->expectException(ValidationException::class);

        $this->factory->create('grammar');
    }

    public function testDefaultsToTopP(): void
    {
        self::assertInstanceOf(TopPSampler::class, $this->factory->create('unknown'));
    }

    public function testForParamsGreedyWhenTemperatureZero(): void
    {
        $sampler = $this->factory->forParams(new SamplingParams(temperature: 0.0));

        self::assertInstanceOf(GreedySampler::class, $sampler);
    }

    public function testForParamsTopPWhenTemperaturePositive(): void
    {
        $sampler = $this->factory->forParams(new SamplingParams(temperature: 0.7, topP: 0.9));

        self::assertInstanceOf(TopPSampler::class, $sampler);
    }

    public function testForParamsTopKWhenTopPIsDefault(): void
    {
        $sampler = $this->factory->forParams(new SamplingParams(temperature: 0.7));

        self::assertInstanceOf(
            TopKSampler::class,
            $sampler,
            'forParams() must return TopKSampler when topP >= 1.0 — topK should not be ignored.',
        );
    }

    public function testForParamsGrammarWhenProvided(): void
    {
        $sampler = $this->factory->forParams(
            new SamplingParams(temperature: 0.7),
            GbnfGrammar::fromString('root ::= "a"'),
        );

        self::assertInstanceOf(GrammarSampler::class, $sampler);
    }
}
