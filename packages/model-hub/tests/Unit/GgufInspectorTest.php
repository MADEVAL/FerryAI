<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\ModelHub\Format\GgufInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GgufInspector::class)]
final class GgufInspectorTest extends TestCase
{
    public function testInspectReturnsMetadataForNonExistentFile(): void
    {
        $metadata = GgufInspector::inspect('/nonexistent/model.gguf');

        self::assertSame('model', $metadata->name);
        self::assertSame('unknown', $metadata->version);
    }

    public function testMetadataReturnsEmptyForNonExistentFile(): void
    {
        $metadata = GgufInspector::metadata('/nonexistent/model.gguf');

        self::assertSame([], $metadata);
    }

    public function testMetadataReturnsEmptyForNonGgufFile(): void
    {
        $path = \sys_get_temp_dir() . '/ferry-gguf-' . \uniqid();
        \file_put_contents($path, 'NOT_GGUF_DATA');

        try {
            $metadata = GgufInspector::metadata($path);

            self::assertSame([], $metadata);
        } finally {
            @\unlink($path);
        }
    }

    public function testMetadataParsesMinimalGgufFile(): void
    {
        $path = \sys_get_temp_dir() . '/ferry-gguf-' . \uniqid();

        $data = 'GGUF';
        $data .= \pack('V', 3);
        $data .= \pack('P', 0);
        $data .= \pack('P', 1);
        $key = 'general.architecture';
        $data .= \pack('P', \strlen($key)) . $key . \str_repeat("\x00", 8 - ((\strlen($key) + 8) % 8) % 8);
        $data .= \pack('V', 8);
        $value = 'llama';
        $data .= \pack('P', \strlen($value)) . $value;

        \file_put_contents($path, $data);

        try {
            $metadata = GgufInspector::metadata($path);

            self::assertNotEmpty($metadata, 'GgufInspector::metadata() must not silently return empty for a valid GGUF file.');
            self::assertArrayHasKey('general.architecture', $metadata);
            self::assertSame('llama', $metadata['general.architecture']);
        } finally {
            @\unlink($path);
        }
    }

    public function testSizeBytesReturnsZeroForNonExistentFile(): void
    {
        $size = GgufInspector::sizeBytes('/nonexistent/model.gguf');

        self::assertSame(0, $size);
    }
}
