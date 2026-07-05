<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Provider;

use FerryAI\Core\Enums\Device;

final class OpenVinoProvider implements ExecutionProvider
{
    #[\Override]
    public function name(): string
    {
        return 'OpenVINOExecutionProvider';
    }

    #[\Override]
    public function device(): Device
    {
        return Device::OPENVINO;
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
            'device_type' => 'CPU',
        ];
    }
}
