<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;

/**
 * Always selects the highest-probability token.
 */
final class GreedySampler implements Sampler
{
    /**
     * @param float[] $logits
     */
    #[\Override]
    public function sample(array $logits, SamplingParams $params): int
    {
        return SamplerMath::argmax($logits);
    }
}
