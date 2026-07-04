<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Provider;

use FerryAI\Core\Enums\Device;

interface ExecutionProvider
{
    /**
     * Provider name, e.g. "CPUExecutionProvider", "CUDAExecutionProvider".
     */
    public function name(): string;

    /**
     * The device this provider maps to.
     */
    public function device(): Device;

    /**
     * Whether the provider is available in the current environment.
     */
    public function isAvailable(): bool;

    /**
     * Returns provider settings for OrtSessionOptions.
     *
     * @return array<string, mixed>
     */
    public function configure(): array;
}
