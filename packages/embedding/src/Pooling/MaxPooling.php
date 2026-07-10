<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Pooling;

final class MaxPooling extends AbstractPooling
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
        $result = null;

        for ($i = 0; $i < $seqLen; $i++) {
            if ($mask[$i] === 0) {
                continue;
            }

            if ($result === null) {
                $result = $hiddenStates[$i];

                continue;
            }

            for ($j = 0; $j < $hiddenDim; $j++) {
                if ($hiddenStates[$i][$j] > $result[$j]) {
                    $result[$j] = $hiddenStates[$i][$j];
                }
            }
        }

        return $result ?? \array_fill(0, $hiddenDim, 0.0);
    }

    #[\Override]
    public function name(): string
    {
        return 'max';
    }
}
