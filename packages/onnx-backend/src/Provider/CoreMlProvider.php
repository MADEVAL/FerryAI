<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Provider;

use FerryAI\Core\Enums\Device;

final class CoreMlProvider implements ExecutionProvider
{
    #[\Override]
    public function name(): string
    {
        return 'CoreMLExecutionProvider';
    }

    #[\Override]
    public function device(): Device
    {
        return Device::METAL;
    }

    /**
     * CoreML is only available on macOS.
     */
    #[\Override]
    public function isAvailable(): bool
    {
        return \PHP_OS_FAMILY === 'Darwin';
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
