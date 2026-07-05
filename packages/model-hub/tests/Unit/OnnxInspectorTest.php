<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

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

    public function testInputsForNonExistentFile(): void
    {
        $inputs = OnnxInspector::inputs('/nonexistent/file.onnx');

        self::assertSame([], $inputs);
    }

    public function testOutputsForNonExistentFile(): void
    {
        $outputs = OnnxInspector::outputs('/nonexistent/file.onnx');

        self::assertSame([], $outputs);
    }
}
