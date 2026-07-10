<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Pooling;

interface PoolingStrategy
{
    /**
     * Extract an embedding vector from hidden states of a model.
     *
     * @param array<int, array<int, float>>    $hiddenStates  [seq_len, hidden_dim] or [batch, seq_len, hidden_dim]
     * @param array<int, array<int, int>>|null $attentionMask Attention mask (1 = real token, 0 = padding)
     *
     * @return array<int, float> Vector of [hidden_dim] or [batch, hidden_dim]
     */
    public function pool(array $hiddenStates, ?array $attentionMask = null): array;

    public function name(): string;
}
