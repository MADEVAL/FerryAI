<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Pooling;

final class MaxPooling implements PoolingStrategy
{
    #[\Override]
    public function pool(array $hiddenStates, ?array $attentionMask = null): array
    {
        $seqLen = \count($hiddenStates);
        $hiddenDim = $seqLen > 0 ? \count($hiddenStates[0]) : 0;

        if ($hiddenDim === 0) {
            return [];
        }

        $result = $hiddenStates[0];

        for ($i = 1; $i < $seqLen; $i++) {
            for ($j = 0; $j < $hiddenDim; $j++) {
                if ($hiddenStates[$i][$j] > $result[$j]) {
                    $result[$j] = $hiddenStates[$i][$j];
                }
            }
        }

        return $result;
    }

    #[\Override]
    public function name(): string
    {
        return 'max';
    }
}
