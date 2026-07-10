<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Stages;

use FerryAI\Core\Contracts\Stage;
use FerryAI\Core\Contracts\Tokenizer;

final class TokenizeStage implements Stage
{
    public function __construct(
        private Tokenizer $tokenizer,
        private bool $addSpecialTokens = true,
    ) {}

    #[\Override]
    public function process(mixed $input): mixed
    {
        if (!\is_string($input)) {
            return $input;
        }

        return $this->tokenizer->encode($input, $this->addSpecialTokens);
    }

    #[\Override]
    public function name(): string
    {
        return 'tokenize';
    }
}
