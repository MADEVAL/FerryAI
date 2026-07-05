<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Stages;

use FerryAI\Core\Contracts\Stage;
use FerryAI\Core\Contracts\VectorStore;

final class StoreStage implements Stage
{
    public function __construct(
        private VectorStore $store,
    ) {}

    #[\Override]
    public function process(mixed $input): mixed
    {
        if (!\is_array($input)) {
            return $input;
        }

        $id = (string) ($input['id'] ?? \uniqid('vec_'));
        $vector = $input['vector'] ?? [];
        $metadata = $input['metadata'] ?? null;

        $this->store->add($id, $vector, $metadata);

        return $id;
    }

    #[\Override]
    public function name(): string
    {
        return 'store';
    }
}
