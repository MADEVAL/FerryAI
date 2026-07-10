<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\Core\Exception\InvalidStateException;
use FerryAI\ModelHub\Format\OnnxInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnnxInspector::class)]
final class OnnxInspectorTest extends TestCase
{
    public function testInspectReturnsMetadataForNonExistentFile(): void
    {
        $metadata = OnnxInspector::inspect('/nonexistent/model.onnx');

        self::assertSame('model', $metadata->name);
        self::assertSame('unknown', $metadata->version);
    }

    public function testInputsThrowsForValidFile(): void
    {
        $path = \sys_get_temp_dir() . '/ferry-onnx-' . \uniqid();
        \file_put_contents($path, "\x08\x08\x12\x08" . \str_repeat('x', 100));

        try {
            $this->expectException(InvalidStateException::class);
            $this->expectExceptionMessageMatches('/not yet implemented/');

            OnnxInspector::inputs($path);
        } finally {
            @\unlink($path);
        }
    }

    public function testOutputsThrowsForValidFile(): void
    {
        $path = \sys_get_temp_dir() . '/ferry-onnx-' . \uniqid();
        \file_put_contents($path, "\x08\x08\x12\x08" . \str_repeat('x', 100));

        try {
            $this->expectException(InvalidStateException::class);
            $this->expectExceptionMessageMatches('/not yet implemented/');

            OnnxInspector::outputs($path);
        } finally {
            @\unlink($path);
        }
    }
}
