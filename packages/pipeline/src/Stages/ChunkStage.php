<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Stages;

use FerryAI\Core\Contracts\Stage;
use FerryAI\Core\Contracts\Tokenizer;

final class ChunkStage implements Stage
{
    public function __construct(
        private Tokenizer $tokenizer,
        private int $maxTokens = 512,
        private int $overlap = 64,
    ) {}

    #[\Override]
    public function process(mixed $input): mixed
    {
        if (!\is_string($input)) {
            return [$input];
        }

        return $this->tokenizer->chunk($input, $this->maxTokens, $this->overlap);
    }

    #[\Override]
    public function name(): string
    {
        return 'chunk';
    }
}
