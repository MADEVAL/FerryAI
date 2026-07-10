<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend\Tests\Unit;

use FerryAI\Core\Contracts\Backend;
use FerryAI\CpuBackend\CpuNativeBackend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CpuNativeBackend::class)]
final class CpuNativeBackendTest extends TestCase
{
    public function testImplementsBackend(): void
    {
        $backend = new CpuNativeBackend();

        self::assertInstanceOf(Backend::class, $backend);
    }

    public function testAvailableDevicesReturnsCpu(): void
    {
        $backend = new CpuNativeBackend();

        $devices = $backend->availableDevices();

        self::assertCount(1, $devices);
        self::assertSame(\FerryAI\Core\Enums\Device::CPU, $devices[0]);
    }

    public function testVersion(): void
    {
        $backend = new CpuNativeBackend();

        self::assertIsString($backend->version());
        self::assertNotEmpty($backend->version());
    }

    public function testIsAvailable(): void
    {
        $backend = new CpuNativeBackend();

        $available = $backend->isAvailable();

        self::assertIsBool($available);
    }

    public function testLoadThrowsModelNotFoundException(): void
    {
        $backend = new CpuNativeBackend();

        $this->expectException(\FerryAI\Core\Exception\ModelNotFoundException::class);
        $backend->load('/nonexistent/model.rbm');
    }
}
