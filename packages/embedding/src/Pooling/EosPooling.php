<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Pooling;

final class EosPooling extends AbstractPooling
{
    #[\Override]
    public function pool(array $hiddenStates, ?array $attentionMask = null): array
    {
        if ($hiddenStates === []) {
            return [];
        }

        $seqLen = \count($hiddenStates);
        $mask = $this->maskRow($seqLen, $attentionMask);

        for ($i = $seqLen - 1; $i >= 0; $i--) {
            if ($mask[$i] !== 0) {
                return $hiddenStates[$i];
            }
        }

        return $hiddenStates[$seqLen - 1];
    }

    #[\Override]
    public function name(): string
    {
        return 'eos';
    }
}
