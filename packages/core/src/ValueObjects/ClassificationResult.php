<?php

declare(strict_types=1);

namespace FerryAI\Core\ValueObjects;

readonly class ClassificationResult
{
    /**
     * @param string               $label      the predicted label
     * @param float                $confidence the confidence in [0.0, 1.0]
     * @param array<string, float> $allScores  all labels with their scores
     *
     * @throws \InvalidArgumentException when $confidence is out of [0.0, 1.0]
     */
    public function __construct(
        public string $label,
        public float $confidence,
        public array $allScores = [],
    ) {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new \InvalidArgumentException(\sprintf('confidence must be in [0.0, 1.0], got %F.', $confidence));
        }
    }
}
