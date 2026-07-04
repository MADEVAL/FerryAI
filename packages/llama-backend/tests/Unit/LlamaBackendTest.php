<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Core\Exception\DeviceNotAvailableException;
use FerryAI\Core\Exception\ModelLoadException;
use FerryAI\Core\Exception\ModelNotFoundException;
use FerryAI\LlamaBackend\LlamaBackend;
use FerryAI\LlamaBackend\LlamaModel;
use FerryAI\LlamaBackend\Tests\Double\MockLlamaRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LlamaBackend::class)]
final class LlamaBackendTest extends TestCase
{
    private string $modelFile = '';

    protected function setUp(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ferry_gguf_');
        $this->modelFile = $path . '.gguf';
        rename($path, $this->modelFile);
        file_put_contents($this->modelFile, 'GGUF');
    }

    protected function tearDown(): void
    {
        if ($this->modelFile !== '' && is_file($this->modelFile)) {
            unlink($this->modelFile);
        }
    }

    public function testAvailableDevicesCpuOnly(): void
    {
        self::assertSame([Device::CPU], (new LlamaBackend(new MockLlamaRuntime(gpu: false)))->availableDevices());
    }

    public function testAvailableDevicesWithGpu(): void
    {
        self::assertSame(
            [Device::CUDA, Device::CPU],
            (new LlamaBackend(new MockLlamaRuntime(gpu: true)))->availableDevices(),
        );
    }

    public function testAvailableDevicesEmptyWhenUnavailable(): void
    {
        $backend = new LlamaBackend(new MockLlamaRuntime(available: false));

        self::assertSame([], $backend->availableDevices());
        self::assertFalse($backend->isAvailable());
    }

    public function testVersion(): void
    {
        self::assertSame('mock-llama-b1', (new LlamaBackend(new MockLlamaRuntime()))->version());
    }

    public function testLoadReturnsLlamaModel(): void
    {
        $model = (new LlamaBackend(new MockLlamaRuntime()))->load($this->modelFile);

        self::assertInstanceOf(LlamaModel::class, $model);
        self::assertSame(Device::CPU, $model->device());
    }

    public function testLoadRemoteThrows(): void
    {
        $this->expectException(ModelLoadException::class);

        (new LlamaBackend(new MockLlamaRuntime()))->load('https://example.com/model.gguf');
    }

    public function testLoadMissingFileThrows(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new LlamaBackend(new MockLlamaRuntime()))->load(sys_get_temp_dir() . '/nope-ferry.gguf');
    }

    public function testLoadUnavailableThrows(): void
    {
        $this->expectException(BackendNotAvailableException::class);

        (new LlamaBackend(new MockLlamaRuntime(available: false)))->load($this->modelFile);
    }

    public function testLoadUnavailableDeviceThrows(): void
    {
        $this->expectException(DeviceNotAvailableException::class);

        (new LlamaBackend(new MockLlamaRuntime(gpu: false)))->load($this->modelFile, Device::CUDA);
    }
}
