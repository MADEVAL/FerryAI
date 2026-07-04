<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Provider;

use FerryAI\Core\Enums\Device;

final class TensorRtProvider implements ExecutionProvider
{
    public function __construct(private readonly int $deviceId = 0) {}

    #[\Override]
    public function name(): string
    {
        return 'TensorrtExecutionProvider';
    }

    #[\Override]
    public function device(): Device
    {
        return Device::CUDA;
    }

    /**
     * TensorRT probing requires the native FFI layer; until then availability is reported as false.
     */
    #[\Override]
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function configure(): array
    {
        return ['device_id' => $this->deviceId];
    }
}
