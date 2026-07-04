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
 * Exercises the real ONNX Runtime via ankane/onnxruntime.
 *
 * Skipped automatically when the native shared library is absent
 * (e.g. local dev without ONNX Runtime, or FERRY_AI_SKIP_NATIVE=1).
 */
#[Group('integration')]
#[CoversNothing]
final class OnnxRuntimeIntegrationTest extends TestCase
{
    private OnnxBackend $backend;

    protected function setUp(): void
    {
        if (getenv('FERRY_AI_SKIP_NATIVE') === '1') {
            self::markTestSkipped('Native tests skipped via FERRY_AI_SKIP_NATIVE=1.');
        }

        $this->backend = new OnnxBackend(new NativeOnnxRuntime());

        if (!$this->backend->isAvailable()) {
            self::markTestSkipped('ONNX Runtime shared library is not available in this environment.');
        }
    }

    public function testReportsAVersion(): void
    {
        self::assertNotSame('', $this->backend->version());
    }

    public function testExposesAvailableDevicesIncludingCpu(): void
    {
        $devices = $this->backend->availableDevices();

        self::assertNotEmpty($devices);
        self::assertContains(Device::CPU, $devices);
    }

    public function testAvailableProvidersContainCpu(): void
    {
        self::assertContains('CPUExecutionProvider', (new NativeOnnxRuntime())->availableProviders());
    }
}
