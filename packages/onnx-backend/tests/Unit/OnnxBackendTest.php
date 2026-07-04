<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Tests\Unit;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Core\Exception\DeviceNotAvailableException;
use FerryAI\Core\Exception\ModelLoadException;
use FerryAI\Core\Exception\ModelNotFoundException;
use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\OnnxBackend\OnnxModel;
use FerryAI\OnnxBackend\Tests\Double\MockOnnxRuntime;
use FerryAI\OnnxBackend\Tests\Double\MockOnnxSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnnxBackend::class)]
final class OnnxBackendTest extends TestCase
{
    private string $modelFile = '';

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ferry_onnx_');
        self::assertIsString($path);
        $this->modelFile = $path . '.onnx';
        rename($path, $this->modelFile);
        file_put_contents($this->modelFile, 'fake');
    }

    protected function tearDown(): void
    {
        if ($this->modelFile !== '' && is_file($this->modelFile)) {
            unlink($this->modelFile);
        }
    }

    public function testAvailableDevicesMapsProviders(): void
    {
        $backend = new OnnxBackend(new MockOnnxRuntime(
            providers: ['CPUExecutionProvider', 'CUDAExecutionProvider'],
        ));

        self::assertSame([Device::CUDA, Device::CPU], $backend->availableDevices());
    }

    public function testAvailableDevicesCpuOnly(): void
    {
        $backend = new OnnxBackend(new MockOnnxRuntime(providers: ['CPUExecutionProvider']));

        self::assertSame([Device::CPU], $backend->availableDevices());
    }

    public function testAvailableDevicesEmptyWhenUnavailable(): void
    {
        $backend = new OnnxBackend(new MockOnnxRuntime(available: false));

        self::assertSame([], $backend->availableDevices());
        self::assertFalse($backend->isAvailable());
    }

    public function testVersion(): void
    {
        $backend = new OnnxBackend(new MockOnnxRuntime(engineVersion: '1.20.0'));

        self::assertSame('1.20.0', $backend->version());
    }

    public function testLoadReturnsOnnxModel(): void
    {
        $backend = new OnnxBackend(new MockOnnxRuntime(session: new MockOnnxSession()));

        $model = $backend->load($this->modelFile);

        self::assertInstanceOf(OnnxModel::class, $model);
        self::assertSame(Device::CPU, $model->device());
    }

    public function testLoadRemoteSourceThrows(): void
    {
        $backend = new OnnxBackend(new MockOnnxRuntime());

        $this->expectException(ModelLoadException::class);

        $backend->load('https://example.com/model.onnx');
    }

    public function testLoadMissingFileThrows(): void
    {
        $backend = new OnnxBackend(new MockOnnxRuntime());

        $this->expectException(ModelNotFoundException::class);

        $backend->load(sys_get_temp_dir() . '/does-not-exist-ferry.onnx');
    }

    public function testLoadWhenUnavailableThrows(): void
    {
        $backend = new OnnxBackend(new MockOnnxRuntime(available: false));

        $this->expectException(BackendNotAvailableException::class);

        $backend->load($this->modelFile);
    }

    public function testLoadUnavailableDeviceThrows(): void
    {
        $backend = new OnnxBackend(new MockOnnxRuntime(providers: ['CPUExecutionProvider']));

        $this->expectException(DeviceNotAvailableException::class);

        $backend->load($this->modelFile, Device::CUDA);
    }
}
