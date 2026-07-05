<?php

declare(strict_types=1);

namespace FerryAI\Pipeline;

use FerryAI\Core\Contracts\Pipeline as PipelineContract;
use FerryAI\Core\Contracts\Stage;
use Generator;

class Pipeline implements PipelineContract
{
    /** @var Stage[] */
    private array $stages = [];

    #[\Override]
    public function pipe(Stage $stage): self
    {
        $this->stages[] = $stage;

        return $this;
    }

    #[\Override]
    public function run(mixed $input): Generator
    {
        $items = $this->normalizeInput($input);

        foreach ($items as $item) {
            $result = $this->processItem($item);

            if ($result !== null) {
                yield $result;
            }
        }
    }

    #[\Override]
    public function stages(): array
    {
        return $this->stages;
    }

    #[\Override]
    public function __invoke(mixed $input): Generator
    {
        return $this->run($input);
    }

    /**
     * @return \Traversable|array
     */
    private function normalizeInput(mixed $input): array|\Traversable
    {
        if (\is_array($input)) {
            return $input;
        }

        if ($input instanceof \Traversable) {
            return $input;
        }

        return [$input];
    }

    private function processItem(mixed $item): mixed
    {
        $current = $item;

        foreach ($this->stages as $stage) {
            $current = $stage->process($current);

            if ($current === null) {
                return null;
            }
        }

        return $current;
    }
}
