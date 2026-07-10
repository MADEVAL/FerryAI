<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Stages;

use FerryAI\Core\Contracts\Stage;

final class FilterStage implements Stage
{
    public function __construct(
        private \Closure $predicate,
    ) {}

    #[\Override]
    public function process(mixed $input): mixed
    {
        $result = ($this->predicate)($input);

        return $result ? $input : null;
    }

    #[\Override]
    public function name(): string
    {
        return 'filter';
    }
}
