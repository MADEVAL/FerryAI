<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Pooling;

final class EosPooling implements PoolingStrategy
{
    #[\Override]
    public function pool(array $hiddenStates, ?array $attentionMask = null): array
    {
        if ($hiddenStates === []) {
            return [];
        }

        $lastIndex = \count($hiddenStates) - 1;

        return $hiddenStates[$lastIndex];
    }

    #[\Override]
    public function name(): string
    {
        return 'eos';
    }
}
