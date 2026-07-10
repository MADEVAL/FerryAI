<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;

interface Sampler
{
    /**
     * Selects the next token from the model logits.
     *
     * @param float[] $logits per-token scores (index = token id)
     *
     * @return int the selected token id
     */
    public function sample(array $logits, SamplingParams $params): int;
}
