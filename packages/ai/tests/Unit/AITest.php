<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\AI;
use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Enums\BackendType;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Core\Exception\ConfigurationException;
use FerryAI\Core\Exception\InvalidStateException;
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

    public function testConfigHonorsConfiguredBackend(): void
    {
        AI::config(['backend' => 'llama']);

        self::assertSame(BackendType::Llama, AI::activeBackend());
    }

    public function testConfigHonorsConfiguredCpuBackend(): void
    {
        AI::config(['backend' => 'cpu']);

        self::assertSame(BackendType::CpuNative, AI::activeBackend());
    }

    public function testUsingFacadeBeforeConfigThrows(): void
    {
        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessageMatches('/AI::config/');

        AI::embed('hello');
    }

    public function testSwitchToOnnxBackend(): void
    {
        AI::config(['backend' => 'onnx']);
        AI::backend('onnx');

        $this->expectNotToPerformAssertions();
    }

    public function testResetBackendRecreatesAndKeepsBackendUsable(): void
    {
        AI::config(['backend' => 'onnx']);

        AI::resetBackend('onnx');

        AI::backend('onnx');

        self::assertSame(BackendType::Onnx, AI::activeBackend());
    }

    public function testSwitchToUnregisteredBackendThrows(): void
    {
        AI::config(['backend' => 'onnx']);

        $this->expectException(ConfigurationException::class);

        AI::backend('unknown-backend');
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

    public function testChatRequiresAvailableLlama(): void
    {
        AI::config(['backend' => 'onnx']);

        $this->expectException(BackendNotAvailableException::class);

        AI::chat([['role' => 'user', 'content' => 'Hi']]);
    }

    public function testStreamRequiresAvailableLlama(): void
    {
        AI::config(['backend' => 'onnx']);

        $this->expectException(BackendNotAvailableException::class);

        AI::stream([['role' => 'user', 'content' => 'Hi']]);
    }

    public function testTokenizerFromFile(): void
    {
        AI::config(['backend' => 'onnx']);

        $path = (string) tempnam(sys_get_temp_dir(), 'ferry_ai_tok_');
        file_put_contents($path, '{"model":{"type":"BPE","vocab":{"a":0,"b":1},"merges":[]}}');

        try {
            $tokenizer = AI::tokenizer($path);

            self::assertInstanceOf(Tokenizer::class, $tokenizer);
            self::assertSame(2, $tokenizer->vocabSize());
        } finally {
            unlink($path);
        }
    }

    public function testEmbedThrowsWhenNoTokenizerAvailable(): void
    {
        AI::config(['backend' => 'onnx']);

        $this->expectException(\RuntimeException::class);

        AI::embed('hello world');
    }

    public function testVectorStoreUsesConfiguredDimension(): void
    {
        AI::config(['backend' => 'onnx', 'vector' => ['dimension' => 3, 'db_path' => ':memory:']]);

        $store = AI::vector('docs');

        self::assertSame(3, $store->dimension());

        $store->add('a', [1.0, 2.0, 3.0]);
        self::assertSame(1, $store->count());
    }

    public function testResetClearsConfiguration(): void
    {
        AI::config(['backend' => 'onnx']);
        AI::reset();

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessageMatches('/AI::config/');

        AI::classify('text');
    }

    public function testClassifyThrowsWhenModelPathNotConfigured(): void
    {
        AI::config(['backend' => 'onnx']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/classify/');

        AI::classify('text');
    }

    public function testModerateThrowsWhenModelPathNotConfigured(): void
    {
        AI::config(['backend' => 'onnx']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/moderate/');

        AI::moderate('text');
    }

    public function testPredictThrowsWhenModelPathNotConfigured(): void
    {
        AI::config(['backend' => 'cpu']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/predict/');

        AI::predict(['feature' => 1]);
    }
}
