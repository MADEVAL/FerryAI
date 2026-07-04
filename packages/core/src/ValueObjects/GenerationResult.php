<?php

declare(strict_types=1);

namespace FerryAI\Core\ValueObjects;

readonly class GenerationResult
{
    /**
     * @param float[]|null $logprobs
     */
    public function __construct(
        public string $text,
        public int $tokensGenerated,
        public int $tokensPrompt,
        public int $tokensTotal,
        public float $durationMs,
        public ?array $logprobs = null,
    ) {}
}
