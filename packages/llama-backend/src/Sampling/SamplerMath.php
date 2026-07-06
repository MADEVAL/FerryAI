<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Sampling;

use FerryAI\Core\Exception\ValidationException;
use Random\Engine\Mt19937;
use Random\IntervalBoundary;
use Random\Randomizer;

/**
 * Pure numeric helpers shared by the samplers.
 */
final class SamplerMath
{
    /**
     * Returns the index of the largest logit.
     *
     * @param float[] $logits
     *
     * @throws ValidationException when the list is empty
     */
    public static function argmax(array $logits): int
    {
        if ($logits === []) {
            throw new ValidationException('Cannot take argmax of an empty logit list.');
        }

        $bestIndex = array_key_first($logits);
        $bestValue = $logits[$bestIndex];

        foreach ($logits as $index => $value) {
            if ($value > $bestValue) {
                $bestValue = $value;
                $bestIndex = $index;
            }
        }

        return $bestIndex;
    }

    /**
     * Numerically stable softmax with optional temperature scaling.
     *
     * @param float[] $logits
     *
     * @return float[] probabilities aligned to the input keys
     */
    public static function softmax(array $logits, float $temperature = 1.0): array
    {
        if ($logits === []) {
            return [];
        }

        if ($temperature > 0.0 && $temperature !== 1.0) {
            $logits = array_map(static fn(float $value): float => $value / $temperature, $logits);
        }

        $max = max($logits);
        $exps = array_map(static fn(float $value): float => exp($value - $max), $logits);
        $sum = array_sum($exps);

        if ($sum <= 0.0) {
            return $exps;
        }

        return array_map(static fn(float $value): float => $value / $sum, $exps);
    }

    /**
     * Samples an index from a probability list (need not be normalised).
     *
     * @param float[] $probabilities
     */
    public static function weightedIndex(array $probabilities, Randomizer $randomizer): int
    {
        $total = array_sum($probabilities);
        $target = $randomizer->getFloat(0.0, $total > 0.0 ? $total : 1.0, IntervalBoundary::ClosedOpen);
        $cumulative = 0.0;

        foreach ($probabilities as $index => $probability) {
            $cumulative += $probability;

            if ($target < $cumulative) {
                return (int) $index;
            }
        }

        $last = array_key_last($probabilities);

        return $last === null ? 0 : (int) $last;
    }

    public static function randomizer(?int $seed): Randomizer
    {
        return $seed === null ? new Randomizer() : new Randomizer(new Mt19937($seed));
    }

    /**
     * Applies repetition/frequency/presence penalties to a logit map, keyed by token id, using
     * the occurrence counts of previously seen tokens. Mirrors llama.cpp semantics:
     *  - repetition: positive logits are divided, negative logits multiplied by the penalty;
     *  - frequency:  logit -= frequency * occurrences;
     *  - presence:   logit -= presence for any token that has occurred.
     *
     * Returns the logits unchanged when every penalty is a no-op.
     *
     * @param array<int, float> $logits token id => logit
     * @param array<int, int>   $counts token id => occurrence count
     *
     * @return array<int, float>
     */
    public static function applyPenalties(
        array $logits,
        array $counts,
        float $repetition = 1.0,
        float $frequency = 0.0,
        float $presence = 0.0,
    ): array {
        if (($repetition === 1.0 && $frequency === 0.0 && $presence === 0.0) || $counts === []) {
            return $logits;
        }

        foreach ($counts as $tokenId => $count) {
            if ($count <= 0 || !\array_key_exists($tokenId, $logits)) {
                continue;
            }

            $logit = $logits[$tokenId];

            if ($repetition !== 1.0) {
                $logit = $logit > 0.0 ? $logit / $repetition : $logit * $repetition;
            }

            $logit -= $frequency * (float) $count;
            $logit -= $presence;

            $logits[$tokenId] = $logit;
        }

        return $logits;
    }
}
