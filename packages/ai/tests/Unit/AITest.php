<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\AI;
use FerryAI\Core\Enums\BackendType;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Core\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AI::class)]
final class AITest extends TestCase
{
    protected function setUp(): void
    {
        AI::reset();
    }

    protected function tearDown(): void
    {
        AI::reset();
    }

    public function testConfigSetsActiveBackendAndDevice(): void
    {
        AI::config(['backend' => 'onnx', 'device' => 'cpu']);

        self::assertSame(BackendType::Onnx, AI::activeBackend());
        self::assertSame(Device::CPU, AI::activeDevice());
    }

    public function testUsingFacadeBeforeConfigThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/AI::config/');

        AI::embed('hello');
    }

    public function testSwitchToOnnxBackend(): void
    {
        AI::config(['backend' => 'onnx']);
        AI::backend('onnx');

        $this->expectNotToPerformAssertions();
    }

    public function testSwitchToUnavailableBackendThrows(): void
    {
        AI::config(['backend' => 'onnx']);

        $this->expectException(BackendNotAvailableException::class);

        AI::backend('llama');
    }

    public function testSetDevice(): void
    {
        AI::config(['backend' => 'onnx']);
        AI::device('cuda');

        self::assertSame(Device::CUDA, AI::activeDevice());
    }

    public function testSetInvalidDeviceThrows(): void
    {
        AI::config(['backend' => 'onnx']);

        $this->expectException(ConfigurationException::class);

        AI::device('not-a-device');
    }

    public function testChatRequiresLaterPhase(): void
    {
        AI::config(['backend' => 'onnx']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Phase 2|llama/i');

        AI::chat([]);
    }

    public function testEmbedRequiresLaterPhase(): void
    {
        AI::config(['backend' => 'onnx']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Phase [23]/');

        AI::embed('hello world');
    }

    public function testResetClearsConfiguration(): void
    {
        AI::config(['backend' => 'onnx']);
        AI::reset();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/AI::config/');

        AI::classify('text');
    }
}
