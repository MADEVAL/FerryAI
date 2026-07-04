<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Grammar\GbnfGrammar;

/**
 * Grammar-constrained sampling.
 *
 * A full GBNF engine requires the token vocabulary and is applied natively by llama.cpp; in pure
 * PHP this sampler carries the grammar (exposed via {@see grammar()} for the native sampler chain)
 * and delegates token selection to an inner sampler.
 */
final class GrammarSampler implements Sampler
{
    public function __construct(
        private readonly GbnfGrammar $grammar,
        private readonly Sampler $delegate = new GreedySampler(),
    ) {}

    /**
     * @param float[] $logits
     */
    #[\Override]
    public function sample(array $logits, SamplingParams $params): int
    {
        return $this->delegate->sample($logits, $params);
    }

    public function grammar(): GbnfGrammar
    {
        return $this->grammar;
    }
}
