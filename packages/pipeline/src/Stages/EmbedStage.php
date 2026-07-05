<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Stages;

use FerryAI\Core\Contracts\Embedder;
use FerryAI\Core\Contracts\Stage;

final class EmbedStage implements Stage
{
    public function __construct(
        private Embedder $embedder,
    ) {}

    #[\Override]
    public function process(mixed $input): mixed
    {
        if (\is_string($input)) {
            return $this->embedder->embed($input);
        }

        if (\is_array($input) && isset($input[0]) && \is_string($input[0])) {
            return $this->embedder->embedBatch($input);
        }

        return $input;
    }

    #[\Override]
    public function name(): string
    {
        return 'embed';
    }
}
