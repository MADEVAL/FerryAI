<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Pooling;

abstract class AbstractPooling implements PoolingStrategy
{
    /**
     * Resolves the effective per-position mask row (1 = real token, 0 = padding) for a sequence.
     * Missing entries and an absent mask default to 1, so callers can treat every position uniformly.
     *
     * @param array<int, array<int, int>>|null $attentionMask
     *
     * @return array<int, int>
     */
    protected function maskRow(int $seqLen, ?array $attentionMask): array
    {
        $row = $attentionMask === null ? null : ($attentionMask[0] ?? null);
        $mask = [];

        for ($i = 0; $i < $seqLen; $i++) {
            $mask[$i] = $row === null ? 1 : ($row[$i] ?? 1);
        }

        return $mask;
    }
}
