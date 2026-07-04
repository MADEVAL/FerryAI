<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Sampling;

use FerryAI\LlamaBackend\Grammar\GbnfGrammar;

/**
 * Creates samplers by name.
 */
final class SamplerFactory
{
    /**
     * @throws \InvalidArgumentException when 'grammar' is requested without a grammar
     */
    public function create(string $type, ?GbnfGrammar $grammar = null): Sampler
    {
        return match ($type) {
            'greedy' => new GreedySampler(),
            'top_k' => new TopKSampler(),
            'grammar' => new GrammarSampler(
                $grammar ?? throw new \InvalidArgumentException("Sampler type 'grammar' requires a GbnfGrammar."),
            ),
            default => new TopPSampler(),
        };
    }
}
