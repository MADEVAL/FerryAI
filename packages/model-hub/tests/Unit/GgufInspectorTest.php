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

    public function testSizeBytesReturnsZeroForNonExistentFile(): void
    {
        $size = GgufInspector::sizeBytes('/nonexistent/model.gguf');

        self::assertSame(0, $size);
    }
}
