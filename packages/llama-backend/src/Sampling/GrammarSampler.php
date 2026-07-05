<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use FerryAI\LlamaBackend\Grammar\GbnfMatcher;

/**
 * Grammar-constrained sampling.
 *
 * Standalone (no decoder) it simply delegates — it cannot enforce a grammar without the token
 * texts. {@see LlamaModel} binds it to the session ({@see bind()}) with a token→piece decoder and
 * the EOS id; it then strictly masks tokens, picking the highest-logit token whose piece keeps the
 * generated text a viable prefix of the grammar, and emitting EOS once the grammar is satisfied.
 */
final class GrammarSampler implements Sampler
{
    private readonly GbnfMatcher $matcher;

    private string $accumulated = '';

    public function __construct(
        private readonly GbnfGrammar $grammar,
        private readonly Sampler $delegate = new GreedySampler(),
        private readonly ?\Closure $decoder = null,
        private readonly int $eosToken = -1,
    ) {
        $this->matcher = new GbnfMatcher($grammar);
    }

    /**
     * Returns a fresh sampler bound to a token decoder + EOS id (fresh grammar state per generation).
     *
     * @param \Closure(int): string $decoder
     */
    public function bind(\Closure $decoder, int $eosToken): self
    {
        return new self($this->grammar, $this->delegate, $decoder, $eosToken);
    }

    /**
     * @param float[] $logits
     */
    #[\Override]
    public function sample(array $logits, SamplingParams $params): int
    {
        if ($this->decoder === null) {
            return $this->delegate->sample($logits, $params);
        }

        $decoder = $this->decoder;
        $work = $logits;

        while ($work !== []) {
            $bestKey = null;
            $bestValue = -\INF;

            foreach ($work as $id => $value) {
                if ($value > $bestValue) {
                    $bestValue = $value;
                    $bestKey = $id;
                }
            }

            if ($bestKey === null) {
                break;
            }

            $token = (int) $bestKey;

            if ($token === $this->eosToken) {
                if ($this->matcher->isComplete($this->accumulated)) {
                    return $token;
                }

                unset($work[$bestKey]);

                continue;
            }

            $piece = $decoder($token);

            if ($piece !== '' && $this->matcher->isViable($this->accumulated . $piece)) {
                $this->accumulated .= $piece;

                return $token;
            }

            unset($work[$bestKey]);
        }

        if ($this->eosToken >= 0 && $this->matcher->isComplete($this->accumulated)) {
            return $this->eosToken;
        }

        return $this->delegate->sample($logits, $params);
    }

    public function grammar(): GbnfGrammar
    {
        return $this->grammar;
    }
}
