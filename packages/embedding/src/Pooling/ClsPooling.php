<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Pooling;

final class ClsPooling implements PoolingStrategy
{
    #[\Override]
    public function pool(array $hiddenStates, ?array $attentionMask = null): array
    {
        if ($hiddenStates === []) {
            return [];
        }

        return $hiddenStates[0];
    }

    #[\Override]
    public function name(): string
    {
        return 'cls';
    }
}
