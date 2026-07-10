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
        $data .= \pack('P', \strlen($key)) . $key;
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

    public function testMetadataParsesSpecCompliantFileWithMultipleKeys(): void
    {
        // Per the GGUF spec, metadata fields are written sequentially WITHOUT padding.
        $path = \sys_get_temp_dir() . '/ferry-gguf-' . \uniqid();

        $string = static fn(string $s): string => \pack('P', \strlen($s)) . $s;

        $data = 'GGUF';
        $data .= \pack('V', 3);   // version
        $data .= \pack('P', 0);   // tensor count
        $data .= \pack('P', 2);   // kv count

        // KV1: general.architecture = "llama" (string, type 8)
        $data .= $string('general.architecture');
        $data .= \pack('V', 8);
        $data .= $string('llama');

        // KV2: general.file_type = 15 (uint32, type 4)
        $data .= $string('general.file_type');
        $data .= \pack('V', 4);
        $data .= \pack('V', 15);

        \file_put_contents($path, $data);

        try {
            $metadata = GgufInspector::metadata($path);

            self::assertSame('llama', $metadata['general.architecture'] ?? null);
            self::assertSame(15, $metadata['general.file_type'] ?? null);
        } finally {
            @\unlink($path);
        }
    }

    public function testMetadataParsesInt8ValueWithoutConsumingExtraByte(): void
    {
        $path = \sys_get_temp_dir() . '/ferry-gguf-' . \uniqid();

        $data = 'GGUF';
        $data .= \pack('V', 3);
        $data .= \pack('P', 0);
        $data .= \pack('P', 1);
        $key = 'x';
        $data .= \pack('P', \strlen($key)) . $key;
        $data .= \pack('V', 1); // GGUF value type 1 = int8
        $data .= \pack('c', 5); // the int8 value

        \file_put_contents($path, $data);

        try {
            $metadata = GgufInspector::metadata($path);

            self::assertArrayHasKey('x', $metadata);
            self::assertSame(5, $metadata['x']);
        } finally {
            @\unlink($path);
        }
    }
}
