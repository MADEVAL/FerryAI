<?php

declare(strict_types=1);

namespace FerryAI\Core\Contracts;

interface Stage
{
    /**
     * Processes a single data item.
     *
     * @param mixed $input data from the previous stage (or the initial input)
     *
     *
     * @throws \RuntimeException on a processing error; the pipeline stops
     * @return mixed             data for the next stage
     */
    public function process(mixed $input): mixed;

    /**
     * Returns the stage name (for logging and profiling).
     */
    public function name(): string;
}
