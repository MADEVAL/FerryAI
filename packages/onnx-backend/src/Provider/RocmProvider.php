<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Provider;

use FerryAI\Core\Enums\Device;

final class RocmProvider implements ExecutionProvider
{
    #[\Override]
    public function name(): string
    {
        return 'ROCMExecutionProvider';
    }

    #[\Override]
    public function device(): Device
    {
        return Device::ROCM;
    }

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
        return [
            'device_id' => 0,
            'memory_limit' => 0,
        ];
    }
}
