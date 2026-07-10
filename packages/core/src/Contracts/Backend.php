<?php

declare(strict_types=1);

namespace FerryAI\Core\Contracts;

use FerryAI\Core\Enums\Device;

interface Backend
{
    /**
     * Returns the devices available to this backend, ordered fastest-first.
     *
     * @return Device[]
     */
    public function availableDevices(): array;

    /**
     * Loads a model from the given source.
     *
     * @param string      $source file path (.onnx, .gguf, .rbm), URL (https://...),
     *                            HuggingFace id (hf://org/model) or a stream resource
     * @param Device|null $device target device; null selects AUTO
     *
     * @throws \FerryAI\Core\Exception\ModelNotFoundException when the source is unavailable
     * @throws \FerryAI\Core\Exception\ModelLoadException     on a load error (format, compatibility)
     */
    public function load(string $source, ?Device $device = null): Model;

    /**
     * Returns the native engine version string (e.g. "1.18.0", "b4000").
     */
    public function version(): string;

    /**
     * Checks whether this backend is available in the current environment.
     */
    public function isAvailable(): bool;
}
