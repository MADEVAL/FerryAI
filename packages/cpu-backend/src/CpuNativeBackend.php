<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Enums\Device;

final class CpuNativeBackend implements Backend
{
    #[\Override]
    public function availableDevices(): array
    {
        return [Device::CPU];
    }

    #[\Override]
    public function load(string $source, ?Device $device = null): Model
    {
        if (!\file_exists($source)) {
            throw new \FerryAI\Core\Exception\ModelNotFoundException($source);
        }

        $content = \file_get_contents($source);

        if ($content === false) {
            throw new \FerryAI\Core\Exception\ModelLoadException($source, 'Cannot read model file');
        }

        $data = @\unserialize($content);

        if ($data === false || !\is_array($data)) {
            throw new \FerryAI\Core\Exception\ModelLoadException($source, 'Invalid or unsupported RubixML model format');
        }

        return new CpuNativeModel($source, $data);
    }

    #[\Override]
    public function version(): string
    {
        return 'cpu-native-1.0';
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return true;
    }
}
