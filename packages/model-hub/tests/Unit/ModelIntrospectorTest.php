<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\ModelHub\ModelIntrospector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelIntrospector::class)]
final class ModelIntrospectorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/ferry-introspect-' . \uniqid();
        \mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        \array_map('unlink', \glob($this->tempDir . '/*'));
        \rmdir($this->tempDir);
    }

    public function testIntrospectOnnx(): void
    {
        $path = $this->tempDir . '/my-model.onnx';
        \file_put_contents($path, "\x08\x08\x12\x08" . \str_repeat('x', 100));

        $metadata = ModelIntrospector::introspect($path);

        self::assertSame('my-model', $metadata->name);
        self::assertSame(104, $metadata->sizeBytes);
    }

    public function testIntrospectGguf(): void
    {
        $path = $this->tempDir . '/llama-model.gguf';
        \file_put_contents($path, 'GGUF' . \str_repeat('x', 100));

        $metadata = ModelIntrospector::introspect($path);

        self::assertSame('llama-model', $metadata->name);
    }

    public function testIntrospectUnknownFormat(): void
    {
        $path = $this->tempDir . '/something.xyz';
        \file_put_contents($path, \str_repeat("\x00", 100));

        $metadata = ModelIntrospector::introspect($path);

        self::assertSame('something', $metadata->name);
    }
}
