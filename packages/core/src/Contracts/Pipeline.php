<?php

declare(strict_types=1);

namespace FerryAI\Core\Contracts;

interface Pipeline
{
    /**
     * Adds a stage to the pipeline.
     *
     * @return $this
     */
    public function pipe(Stage $stage): self;

    /**
     * Runs the pipeline over the input.
     *
     * @param mixed $input initial data (string, array, Generator)
     *
     * @return \Generator<mixed> lazy processing; each item passed through all stages
     */
    public function run(mixed $input): \Generator;

    /**
     * Returns the configured stages.
     *
     * @return Stage[]
     */
    public function stages(): array;

    /**
     * Invokable form supporting the PHP 8.5 pipe operator.
     *
     * @return \Generator<mixed>
     */
    public function __invoke(mixed $input): \Generator;
}
