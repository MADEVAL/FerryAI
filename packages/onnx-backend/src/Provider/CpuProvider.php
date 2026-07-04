<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Provider;

use FerryAI\Core\Enums\Device;

final class CpuProvider implements ExecutionProvider
{
    #[\Override]
    public function name(): string
    {
        return 'CPUExecutionProvider';
    }

    #[\Override]
    public function device(): Device
    {
        return Device::CPU;
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function configure(): array
    {
        return [];
    }
}
