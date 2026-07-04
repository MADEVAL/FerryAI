<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Tests\Unit\Provider;

use FerryAI\Core\Enums\Device;
use FerryAI\OnnxBackend\Provider\CoreMlProvider;
use FerryAI\OnnxBackend\Provider\CpuProvider;
use FerryAI\OnnxBackend\Provider\CudaProvider;
use FerryAI\OnnxBackend\Provider\DirectMlProvider;
use FerryAI\OnnxBackend\Provider\ExecutionProvider;
use FerryAI\OnnxBackend\Provider\TensorRtProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CpuProvider::class)]
#[CoversClass(CudaProvider::class)]
#[CoversClass(TensorRtProvider::class)]
#[CoversClass(CoreMlProvider::class)]
#[CoversClass(DirectMlProvider::class)]
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

    public function testCudaProviderNameAndDevice(): void
    {
        $provider = new CudaProvider();

        self::assertSame('CUDAExecutionProvider', $provider->name());
        self::assertSame(Device::CUDA, $provider->device());
        self::assertFalse($provider->isAvailable());
    }

    public function testCudaProviderConfigure(): void
    {
        self::assertSame(['device_id' => 0], (new CudaProvider())->configure());
        self::assertSame(
            ['device_id' => 1, 'memory_limit' => 2048],
            (new CudaProvider(1, 2048))->configure(),
        );
    }

    public function testTensorRtProvider(): void
    {
        $provider = new TensorRtProvider();

        self::assertSame('TensorrtExecutionProvider', $provider->name());
        self::assertSame(Device::CUDA, $provider->device());
        self::assertFalse($provider->isAvailable());
    }

    public function testCoreMlProvider(): void
    {
        $provider = new CoreMlProvider();

        self::assertSame('CoreMLExecutionProvider', $provider->name());
        self::assertSame(Device::METAL, $provider->device());
        self::assertSame(\PHP_OS_FAMILY === 'Darwin', $provider->isAvailable());
    }

    public function testDirectMlProvider(): void
    {
        $provider = new DirectMlProvider();

        self::assertSame('DmlExecutionProvider', $provider->name());
        self::assertSame(Device::DIRECTML, $provider->device());
        self::assertFalse($provider->isAvailable());
    }
}
