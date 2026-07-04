<?php

declare(strict_types=1);

namespace FerryAI\Tests\Double;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Enums\Device;

/**
 * Minimal Backend double for registry/router tests.
 */
final class StubBackend implements Backend
{
    public function __construct(private readonly bool $available = true) {}

    /**
     * @return Device[]
     */
    public function availableDevices(): array
    {
        return $this->available ? [Device::CPU] : [];
    }

    public function load(string $source, ?Device $device = null): Model
    {
        throw new \RuntimeException('StubBackend cannot load models.');
    }

    public function version(): string
    {
        return 'stub';
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }
}
