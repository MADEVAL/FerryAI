<?php

declare(strict_types=1);

namespace FerryAI\Core\Contracts;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\ValueObjects\ModelMetadata;

interface Model
{
    /**
     * Runs inference (forward pass).
     *
     * @param array<string, mixed> $inputs input name => data (Tensor | PHP array | string)
     *
     *
     * @throws \FerryAI\Core\Exception\InferenceException     on an execution error
     * @throws \FerryAI\Core\Exception\ShapeMismatchException on an input shape mismatch
     * @return array<string, mixed>                           output name => data (Tensor)
     */
    public function run(array $inputs): array;

    /**
     * Returns model input metadata.
     *
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public function inputs(): array;

    /**
     * Returns model output metadata.
     *
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public function outputs(): array;

    /**
     * Returns model metadata: name, author, version, license, size, tags.
     */
    public function metadata(): ModelMetadata;

    /**
     * Returns the device the model is loaded on.
     */
    public function device(): Device;

    /**
     * Frees native model resources. The model is unusable afterwards.
     */
    public function unload(): void;
}
