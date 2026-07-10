<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Sampling;

use FerryAI\Core\Exception\ValidationException;
use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Grammar\GbnfGrammar;

/**
 * Creates samplers by name or from sampling parameters.
 */
final class SamplerFactory
{
    /**
     * @throws ValidationException when 'grammar' is requested without a grammar
     */
    public function create(string $type, ?GbnfGrammar $grammar = null): Sampler
    {
        return match ($type) {
            'greedy' => new GreedySampler(),
            'top_k' => new TopKSampler(),
            'grammar' => new GrammarSampler(
                $grammar ?? throw new ValidationException("Sampler type 'grammar' requires a GbnfGrammar."),
            ),
            default => new TopPSampler(),
        };
    }

    /**
     * Selects a sampler from the request parameters:
     * a grammar (if given) constrains output; temperature 0 is deterministic (greedy);
     * topP < 1.0 selects nucleus (top-p) sampling; otherwise top-K sampling.
     */
    public function forParams(SamplingParams $params, ?GbnfGrammar $grammar = null): Sampler
    {
        if ($grammar !== null) {
            return new GrammarSampler($grammar);
        }

        if ($params->temperature <= 0.0) {
            return new GreedySampler();
        }

        if ($params->topP < 1.0) {
            return new TopPSampler();
        }

        return new TopKSampler();
    }
}
