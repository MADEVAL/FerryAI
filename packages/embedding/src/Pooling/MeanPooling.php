<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Pooling;

final class MeanPooling extends AbstractPooling
{
    #[\Override]
    public function pool(array $hiddenStates, ?array $attentionMask = null): array
    {
        $seqLen = \count($hiddenStates);
        $hiddenDim = $seqLen > 0 ? \count($hiddenStates[0]) : 0;

        if ($hiddenDim === 0) {
            return [];
        }

        $mask = $this->maskRow($seqLen, $attentionMask);
        $result = \array_fill(0, $hiddenDim, 0.0);
        $count = 0;

        for ($i = 0; $i < $seqLen; $i++) {
            if ($mask[$i] === 0) {
                continue;
            }
            $count++;

            for ($j = 0; $j < $hiddenDim; $j++) {
                $result[$j] += $hiddenStates[$i][$j];
            }
        }

        if ($count === 0) {
            return $result;
        }

        for ($j = 0; $j < $hiddenDim; $j++) {
            $result[$j] /= (float) $count;
        }

        return $result;
    }

    #[\Override]
    public function name(): string
    {
        return 'mean';
    }
}
