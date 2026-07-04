<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Provider;

use FerryAI\Core\Enums\Device;

final class CudaProvider implements ExecutionProvider
{
    public function __construct(
        private readonly int $deviceId = 0,
        private readonly ?int $memoryLimit = null,
    ) {}

    #[\Override]
    public function name(): string
    {
        return 'CUDAExecutionProvider';
    }

    #[\Override]
    public function device(): Device
    {
        return Device::CUDA;
    }

    /**
     * GPU probing requires the native FFI layer (added with the ONNX runtime binding);
     * until then availability is reported as false.
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
        $config = ['device_id' => $this->deviceId];

        if ($this->memoryLimit !== null) {
            $config['memory_limit'] = $this->memoryLimit;
        }

        return $config;
    }
}
