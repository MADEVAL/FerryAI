<?php

declare(strict_types=1);

namespace FerryAI\Core\ValueObjects;

use FerryAI\Core\Exception\ValidationException;

readonly class SamplingParams
{
    /**
     * @param string[]|null $stop
     *
     * @throws ValidationException when a parameter is out of its valid range
     */
    public function __construct(
        public float $temperature = 0.7,
        public float $topP = 1.0,
        public int $topK = 40,
        public float $repetitionPenalty = 1.0,
        public float $frequencyPenalty = 0.0,
        public float $presencePenalty = 0.0,
        public int $maxTokens = 2048,
        public ?array $stop = null,
        public ?int $seed = null,
    ) {
        if (!($temperature >= 0.0 && $temperature <= 2.0)) {
            throw new ValidationException(\sprintf('temperature must be in [0.0, 2.0], got %F.', $temperature));
        }

        if (!($topP >= 0.0 && $topP <= 1.0)) {
            throw new ValidationException(\sprintf('topP must be in [0.0, 1.0], got %F.', $topP));
        }

        if ($topK < 1) {
            throw new ValidationException(\sprintf('topK must be >= 1, got %d.', $topK));
        }

        if (!($repetitionPenalty > 0.0)) {
            throw new ValidationException(\sprintf('repetitionPenalty must be > 0.0, got %F.', $repetitionPenalty));
        }

        if (!($frequencyPenalty >= -2.0 && $frequencyPenalty <= 2.0)) {
            throw new ValidationException(\sprintf('frequencyPenalty must be in [-2.0, 2.0], got %F.', $frequencyPenalty));
        }

        if (!($presencePenalty >= -2.0 && $presencePenalty <= 2.0)) {
            throw new ValidationException(\sprintf('presencePenalty must be in [-2.0, 2.0], got %F.', $presencePenalty));
        }

        if ($maxTokens < 1) {
            throw new ValidationException(\sprintf('maxTokens must be >= 1, got %d.', $maxTokens));
        }
    }
}
