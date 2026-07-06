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

        // Sort once by descending logit: pick the highest-probability candidates first.
        $pairs = [];

        foreach ($logits as $id => $value) {
            $pairs[] = [(int) $id, $value];
        }

        \usort($pairs, static fn(array $a, array $b): int => $b[1] <=> $a[1]);

        // Pre-filter: which single characters can start a valid grammar continuation?
        // Instead of calling isViable() + decoder() for every candidate token (~150k),
        // we check only printable ASCII bytes (~95) and then filter candidates whose
        // first decoded character is not in the viable set.
        $validFirstChars = $this->computeValidFirstChars();

        foreach ($pairs as $pair) {
            $token = $pair[0];

            if ($token === $this->eosToken) {
                if ($this->matcher->isComplete($this->accumulated)) {
                    return $token;
                }

                continue;
            }

            $piece = $decoder($token);

            if ($piece === '') {
                continue;
            }

            // Fast-path: drop tokens whose first byte cannot start a viable continuation.
            if ($validFirstChars !== null && !isset($validFirstChars[$piece[0]])) {
                continue;
            }

            if ($this->matcher->isViable($this->accumulated . $piece)) {
                $this->accumulated .= $piece;

                return $token;
            }
        }

        if ($this->eosToken >= 0 && $this->matcher->isComplete($this->accumulated)) {
            return $this->eosToken;
        }

        return $this->delegate->sample($logits, $params);
    }

    /**
     * @return array<string, true>|null set of first bytes that can continue the grammar,
     *         or null when the accumulated prefix is empty (every char is viable).
     */
    private function computeValidFirstChars(): ?array
    {
        if ($this->accumulated === '') {
            return null;
        }

        $prefix = $this->accumulated;
        $valid = [];

        // Printable ASCII only — covers every BPE/WordPiece token start.
        for ($c = 32; $c <= 126; ++$c) {
            $ch = \chr($c);

            if ($this->matcher->isViable($prefix . $ch)) {
                $valid[$ch] = true;
            }
        }

        // Also check the empty / control path: sometimes grammar allows EOS (empty continuation).
        // It is checked separately via $this->eosToken above.

        return $valid;
    }

    public function grammar(): GbnfGrammar
    {
        return $this->grammar;
    }
}
