<?php

declare(strict_types=1);

namespace FerryAI\Pipeline;

use FerryAI\Core\Exception\InferenceException;
use Fiber;
use Generator;

class FiberPipeline extends Pipeline
{
    private ?float $timeoutSeconds = null;

    public function setTimeout(?float $seconds): self
    {
        $this->timeoutSeconds = $seconds;

        return $this;
    }

    /**
     * Runs the pipeline, enforcing the configured timeout as a wall-clock deadline that is
     * checked around each produced item (cooperative — a single blocking stage cannot be
     * pre-empted, but the deadline stops further processing).
     *
     * @return Generator<mixed>
     */
    #[\Override]
    public function run(mixed $input): Generator
    {
        $start = \microtime(true);

        foreach (parent::run($input) as $key => $value) {
            $this->assertWithinTimeout($start);

            yield $key => $value;
        }

        $this->assertWithinTimeout($start);
    }

    private function assertWithinTimeout(float $start): void
    {
        if ($this->timeoutSeconds !== null && (\microtime(true) - $start) > $this->timeoutSeconds) {
            throw new InferenceException(\sprintf('Pipeline exceeded its timeout of %.3fs.', $this->timeoutSeconds));
        }
    }

    /**
     * @psalm-suppress TooManyTemplateParams
     *
     * @return Fiber
     * @phpstan-return Fiber<mixed, mixed, Generator<mixed>, mixed>
     */
    public function runAsync(mixed $input): Fiber
    {
        return new Fiber(function () use ($input): Generator {
            return $this->run($input);
        });
    }
}
