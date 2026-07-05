<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit;

use FerryAI\Core\AIConfig;
use FerryAI\Core\Enums\BackendType;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AIConfig::class)]
final class AIConfigTest extends TestCase
{
    public function testDefaultsResolveWithoutError(): void
    {
        $config = AIConfig::fromArray([]);

        self::assertSame(BackendType::Onnx, $config->backend());
        self::assertSame(Device::AUTO, $config->device());
        self::assertSame(2048, $config->maxTokens());
        self::assertSame(0.7, $config->temperature());
        self::assertSame(1.0, $config->topP());
        self::assertSame(30, $config->streamTimeout());
        self::assertTrue($config->verifySignatures());
        self::assertSame('warning', $config->logLevel());
        self::assertSame([], $config->backendsConfig());
    }

    public function testBackendParsing(): void
    {
        self::assertSame(BackendType::Onnx, AIConfig::fromArray(['backend' => 'onnx'])->backend());
        self::assertSame(BackendType::Llama, AIConfig::fromArray(['backend' => 'llama'])->backend());
        self::assertSame(BackendType::CpuNative, AIConfig::fromArray(['backend' => 'cpu'])->backend());
    }

    public function testUnknownBackendThrows(): void
    {
        $this->expectException(ConfigurationException::class);

        AIConfig::fromArray(['backend' => 'nonsense'])->backend();
    }

    public function testDotNotationGet(): void
    {
        $config = AIConfig::fromArray(['backends' => ['onnx' => ['providers' => ['cpu']]]]);

        self::assertSame(['cpu'], $config->get('backends.onnx.providers'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        self::assertSame('fallback', AIConfig::fromArray([])->get('does.not.exist', 'fallback'));
    }

    public function testHasWithDotNotation(): void
    {
        $config = AIConfig::fromArray(['backends' => ['onnx' => ['providers' => ['cpu']]]]);

        self::assertTrue($config->has('backends.onnx.providers'));
        self::assertFalse($config->has('backends.llama'));
    }

    public function testSetIsImmutable(): void
    {
        $original = AIConfig::fromArray([]);
        $updated = $original->set('temperature', 0.9);

        self::assertSame(0.9, $updated->temperature());
        self::assertSame(0.7, $original->temperature());
        self::assertNotSame($original, $updated);
    }

    public function testArrayAccessRead(): void
    {
        $config = AIConfig::fromArray([]);

        self::assertTrue(isset($config['temperature']));
        self::assertSame(0.7, $config['temperature']);
        self::assertFalse(isset($config['missing']));
    }

    public function testToArrayContainsDefaults(): void
    {
        $config = AIConfig::fromArray([]);

        self::assertArrayHasKey('backend', $config->toArray());
        self::assertSame('auto', $config->toArray()['backend']);
    }
}
