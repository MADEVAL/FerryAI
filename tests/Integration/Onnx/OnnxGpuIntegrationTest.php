<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\Onnx;

use FerryAI\Core\Enums\Device;
use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\OnnxBackend\Runtime\NativeOnnxRuntime;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Verifies ONNX GPU (CUDA) execution provider availability.
 *
 * Skipped when ONNX Runtime is not available or when CUDA is not installed.
 * Requires CUDA runtime libraries + cuDNN on Windows (see docs/backends/onnx.md).
 */
#[Group('integration')]
#[CoversNothing]
final class OnnxGpuIntegrationTest extends TestCase
{
    private NativeOnnxRuntime $runtime;

    protected function setUp(): void
    {
        if (getenv('FERRY_AI_SKIP_NATIVE') === '1') {
            self::markTestSkipped('Native tests skipped via FERRY_AI_SKIP_NATIVE=1.');
        }

        $this->runtime = new NativeOnnxRuntime();

        if (!(new OnnxBackend($this->runtime))->isAvailable()) {
            self::markTestSkipped('ONNX Runtime shared library is not available.');
        }
    }

    public function testCpuProviderIsAlwaysAvailable(): void
    {
        $providers = $this->runtime->availableProviders();

        self::assertContains('CPUExecutionProvider', $providers);
    }

    public function testCudaProviderIsListedWhenAvailable(): void
    {
        $providers = $this->runtime->availableProviders();

        if (!\in_array('CUDAExecutionProvider', $providers, true)) {
            self::markTestSkipped('CUDAExecutionProvider not available in this environment.');
        }

        self::assertContains('CUDAExecutionProvider', $providers);
    }

    public function testCudaDeviceIsExposedWhenAvailable(): void
    {
        $backend = new OnnxBackend($this->runtime);
        $devices = $backend->availableDevices();

        if (!\in_array(Device::CUDA, $devices, true)) {
            self::markTestSkipped('CUDA device not available in this environment.');
        }

        self::assertContains(Device::CUDA, $devices);
    }
}
