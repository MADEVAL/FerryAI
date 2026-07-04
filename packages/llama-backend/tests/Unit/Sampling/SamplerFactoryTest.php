<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Sampling;

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
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->create('grammar');
    }

    public function testDefaultsToTopP(): void
    {
        self::assertInstanceOf(TopPSampler::class, $this->factory->create('unknown'));
    }
}
