<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\ModelHub\Format\FormatDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormatDetector::class)]
final class FormatDetectorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/ferry-modelhub-' . \uniqid();
        \mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        \array_map('unlink', \glob($this->tempDir . '/*'));
        \rmdir($this->tempDir);
    }

    public function testDetectOnnxFormat(): void
    {
        $path = $this->tempDir . '/model.onnx';
        \file_put_contents($path, "\x08\x08\x12\x08" . \str_repeat("\x00", 100));

        $format = FormatDetector::detect($path);

        self::assertSame('onnx', $format);
    }

    public function testDetectGgufFormat(): void
    {
        $path = $this->tempDir . '/model.gguf';
        \file_put_contents($path, 'GGUF' . \str_repeat("\x00", 100));

        $format = FormatDetector::detect($path);

        self::assertSame('gguf', $format);
    }

    public function testDetectSafetensorsFormat(): void
    {
        $path = $this->tempDir . '/model.safetensors';
        $len = \pack('P', 10);
        \file_put_contents($path, $len . \str_repeat("\x00", 100));

        $format = FormatDetector::detect($path);

        self::assertSame('safetensors', $format);
    }

    public function testDetectAiArchive(): void
    {
        $path = $this->tempDir . '/model.ai';
        \file_put_contents($path, "PK\x03\x04" . \str_repeat("\x00", 100));

        $format = FormatDetector::detect($path);

        self::assertSame('ai', $format);
    }

    public function testDetectUnknownFormat(): void
    {
        $path = $this->tempDir . '/model.unknown';
        \file_put_contents($path, \str_repeat("\x00", 100));

        $format = FormatDetector::detect($path);

        self::assertSame('unknown', $format);
    }

    public function testDetectMissingFileReturnsUnknown(): void
    {
        $format = FormatDetector::detect('/nonexistent/path.model');

        self::assertSame('unknown', $format);
    }
}
