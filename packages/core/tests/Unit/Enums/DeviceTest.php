<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Enums;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\DeviceNotAvailableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Device::class)]
final class DeviceTest extends TestCase
{
    public function testAllNineCasesAreDefined(): void
    {
        self::assertCount(9, Device::cases());
    }

    public function testBackingValues(): void
    {
        self::assertSame('cpu', Device::CPU->value);
        self::assertSame('cuda', Device::CUDA->value);
        self::assertSame('rocm', Device::ROCM->value);
        self::assertSame('metal', Device::METAL->value);
        self::assertSame('vulkan', Device::VULKAN->value);
        self::assertSame('directml', Device::DIRECTML->value);
        self::assertSame('openvino', Device::OPENVINO->value);
        self::assertSame('opencl', Device::OPENCL->value);
        self::assertSame('auto', Device::AUTO->value);
    }

    public function testTryFrom(): void
    {
        self::assertSame(Device::CUDA, Device::tryFrom('cuda'));
        self::assertNull(Device::tryFrom('does-not-exist'));
    }

    /**
     * @return iterable<string, array{Device, int}>
     */
    public static function priorityProvider(): iterable
    {
        yield 'cuda' => [Device::CUDA, 90];
        yield 'rocm' => [Device::ROCM, 80];
        yield 'metal' => [Device::METAL, 70];
        yield 'vulkan' => [Device::VULKAN, 60];
        yield 'directml' => [Device::DIRECTML, 50];
        yield 'openvino' => [Device::OPENVINO, 40];
        yield 'opencl' => [Device::OPENCL, 30];
        yield 'cpu' => [Device::CPU, 10];
        yield 'auto' => [Device::AUTO, 0];
    }

    #[DataProvider('priorityProvider')]
    public function testPriority(Device $device, int $expected): void
    {
        self::assertSame($expected, $device->priority());
    }

    public function testGpuPriorityBeatsCpu(): void
    {
        self::assertGreaterThan(Device::CPU->priority(), Device::CUDA->priority());
    }

    public function testResolveReturnsPreferredWhenAvailable(): void
    {
        self::assertSame(
            Device::CUDA,
            Device::resolve(Device::CUDA, [Device::CPU, Device::CUDA]),
        );
    }

    public function testResolveAutoPicksHighestPriorityAvailable(): void
    {
        self::assertSame(
            Device::CUDA,
            Device::resolve(Device::AUTO, [Device::CUDA, Device::CPU]),
        );
    }

    public function testResolveAutoWithSingleDevice(): void
    {
        self::assertSame(
            Device::CPU,
            Device::resolve(Device::AUTO, [Device::CPU]),
        );
    }

    public function testResolveAutoThrowsWhenNothingAvailable(): void
    {
        try {
            Device::resolve(Device::AUTO, []);
            self::fail('Expected DeviceNotAvailableException was not thrown.');
        } catch (DeviceNotAvailableException $exception) {
            self::assertSame(Device::AUTO, $exception->requestedDevice());
        }
    }

    public function testResolveThrowsWhenPreferredNotAvailable(): void
    {
        try {
            Device::resolve(Device::CUDA, [Device::CPU]);
            self::fail('Expected DeviceNotAvailableException was not thrown.');
        } catch (DeviceNotAvailableException $exception) {
            self::assertSame(Device::CUDA, $exception->requestedDevice());
        }
    }
}
