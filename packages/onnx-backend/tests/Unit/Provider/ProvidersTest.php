<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Tests\Unit\Provider;

use FerryAI\Core\Enums\Device;
use FerryAI\OnnxBackend\Provider\CpuProvider;
use FerryAI\OnnxBackend\Provider\ExecutionProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CpuProvider::class)]
final class ProvidersTest extends TestCase
{
    public function testInterfaceContract(): void
    {
        self::assertTrue(interface_exists(ExecutionProvider::class));

        foreach (['name', 'device', 'isAvailable', 'configure'] as $method) {
            self::assertTrue(method_exists(ExecutionProvider::class, $method));
        }
    }

    public function testCpuProvider(): void
    {
        $provider = new CpuProvider();

        self::assertInstanceOf(ExecutionProvider::class, $provider);
        self::assertSame('CPUExecutionProvider', $provider->name());
        self::assertSame(Device::CPU, $provider->device());
        self::assertTrue($provider->isAvailable());
        self::assertSame([], $provider->configure());
    }
}
