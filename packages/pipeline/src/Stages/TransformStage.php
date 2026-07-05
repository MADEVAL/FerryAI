<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Stages;

use FerryAI\Core\Contracts\Stage;

final class TransformStage implements Stage
{
    public function __construct(
        private \Closure $transform,
        private string $stageName = 'transform',
    ) {}

    #[\Override]
    public function process(mixed $input): mixed
    {
        return ($this->transform)($input);
    }

    #[\Override]
    public function name(): string
    {
        return $this->stageName;
    }
}
