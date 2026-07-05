<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Sampling;

use FerryAI\Core\Exception\ValidationException;
use FerryAI\Core\ValueObjects\SamplingParams;

/**
 * Top-K sampling: keeps the K highest logits, then samples proportionally.
 */
final class TopKSampler implements Sampler
{
    /**
     * @param float[] $logits
     */
    #[\Override]
    public function sample(array $logits, SamplingParams $params): int
    {
        if ($logits === []) {
            throw new ValidationException('Cannot sample from an empty logit list.');
        }

        $indices = array_keys($logits);
        usort($indices, static fn(int $a, int $b): int => $logits[$b] <=> $logits[$a]);

        $top = \array_slice($indices, 0, max(1, $params->topK));
        $topLogits = array_map(static fn(int $index): float => $logits[$index], $top);
        $probabilities = SamplerMath::softmax($topLogits, $params->temperature);

        $position = SamplerMath::weightedIndex($probabilities, SamplerMath::randomizer($params->seed));

        return $top[$position];
    }
}
