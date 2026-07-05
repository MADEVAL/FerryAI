<?php

declare(strict_types=1);

namespace FerryAI\Pipeline;

use Fiber;
use Generator;

class FiberPipeline extends Pipeline
{
    /** @phpstan-ignore property.onlyWritten */
    private ?float $timeoutSeconds = null;

    public function setTimeout(?float $seconds): self
    {
        $this->timeoutSeconds = $seconds;

        return $this;
    }

    /**
     * @return Fiber
     */
    public function runAsync(mixed $input): Fiber
    {
        return new Fiber(function () use ($input): Generator {
            return $this->run($input);
        });
    }
}
