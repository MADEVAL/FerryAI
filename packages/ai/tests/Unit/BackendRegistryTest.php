<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\BackendRegistry;
use FerryAI\Core\Enums\BackendType;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Tests\Double\StubBackend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackendRegistry::class)]
final class BackendRegistryTest extends TestCase
{
    public function testRegisterAndHas(): void
    {
        $registry = new BackendRegistry();

        self::assertFalse($registry->has(BackendType::Onnx));

        $registry->register(BackendType::Onnx, new StubBackend());

        self::assertTrue($registry->has(BackendType::Onnx));
    }

    public function testGet(): void
    {
        $registry = new BackendRegistry();
        $backend = new StubBackend();
        $registry->register(BackendType::Onnx, $backend);

        self::assertSame($backend, $registry->get(BackendType::Onnx));
    }

    public function testGetUnregisteredThrows(): void
    {
        $this->expectException(BackendNotAvailableException::class);

        (new BackendRegistry())->get(BackendType::Llama);
    }

    public function testAll(): void
    {
        $registry = new BackendRegistry();
        $registry->register(BackendType::Onnx, new StubBackend());
        $registry->register(BackendType::CpuNative, new StubBackend());

        self::assertCount(2, $registry->all());
    }

    public function testAutoDetectPrefersHigherPriority(): void
    {
        $registry = new BackendRegistry();
        $registry->register(BackendType::CpuNative, new StubBackend(true));
        $registry->register(BackendType::Onnx, new StubBackend(true));
        $registry->register(BackendType::Llama, new StubBackend(true));

        self::assertSame(BackendType::Llama, $registry->autoDetect());
    }

    public function testAutoDetectSkipsUnavailable(): void
    {
        $registry = new BackendRegistry();
        $registry->register(BackendType::Llama, new StubBackend(false));
        $registry->register(BackendType::Onnx, new StubBackend(true));

        self::assertSame(BackendType::Onnx, $registry->autoDetect());
    }

    public function testAutoDetectThrowsWhenNoneAvailable(): void
    {
        $registry = new BackendRegistry();
        $registry->register(BackendType::Onnx, new StubBackend(false));

        $this->expectException(BackendNotAvailableException::class);

        $registry->autoDetect();
    }
}
