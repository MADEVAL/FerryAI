<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;

/**
 * Nucleus (top-p) sampling: keeps the smallest set of tokens whose cumulative probability
 * reaches topP, then samples proportionally among them.
 */
final class TopPSampler implements Sampler
{
    /**
     * @param float[] $logits
     */
    #[\Override]
    public function sample(array $logits, SamplingParams $params): int
    {
        if ($logits === []) {
            throw new \InvalidArgumentException('Cannot sample from an empty logit list.');
        }

        $probabilities = SamplerMath::softmax($logits, $params->temperature);

        $indices = array_keys($probabilities);
        usort($indices, static fn(int $a, int $b): int => $probabilities[$b] <=> $probabilities[$a]);

        $kept = [];
        $cumulative = 0.0;

        foreach ($indices as $index) {
            $kept[] = $index;
            $cumulative += $probabilities[$index];

            if ($cumulative >= $params->topP) {
                break;
            }
        }

        $keptProbabilities = array_map(static fn(int $index): float => $probabilities[$index], $kept);
        $position = SamplerMath::weightedIndex($keptProbabilities, SamplerMath::randomizer($params->seed));

        return $kept[$position];
    }
}
