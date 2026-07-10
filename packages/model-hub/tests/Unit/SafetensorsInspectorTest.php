<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\ModelHub\Format\SafetensorsInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SafetensorsInspector::class)]
final class SafetensorsInspectorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/ferry-sft-' . \uniqid();
        \mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        \array_map('unlink', \glob($this->tempDir . '/*'));
        \rmdir($this->tempDir);
    }

    public function testInspectReadsTensorMetadata(): void
    {
        $path = $this->tempDir . '/model.safetensors';
        $this->writeTestFile($path, [
            'model.weight' => ['dtype' => 'F32', 'shape' => [2, 4], 'data' => \str_repeat("\x00", 32)],
            'model.bias'   => ['dtype' => 'F16', 'shape' => [4], 'data' => \str_repeat("\x00", 8)],
        ]);

        $info = SafetensorsInspector::inspect($path);

        self::assertSame('safetensors', $info['format']);
        self::assertSame(2, $info['tensor_count']);
        self::assertSame(40, $info['data_size_bytes']);

        self::assertSame('F32', $info['tensors']['model.weight']['dtype']);
        self::assertSame([2, 4], $info['tensors']['model.weight']['shape']);
        self::assertSame(32, $info['tensors']['model.weight']['size_bytes']);

        self::assertSame('F16', $info['tensors']['model.bias']['dtype']);
        self::assertSame([4], $info['tensors']['model.bias']['shape']);
        self::assertSame(8, $info['tensors']['model.bias']['size_bytes']);
    }

    public function testInspectReturnsEmptyForNonExistentFile(): void
    {
        self::assertSame([], SafetensorsInspector::inspect($this->tempDir . '/none.safetensors'));
    }

    public function testInspectReturnsEmptyForNonSafetensorsFile(): void
    {
        $path = $this->tempDir . '/not-safetensors.bin';
        \file_put_contents($path, \str_repeat("\x00", 32));

        self::assertSame([], SafetensorsInspector::inspect($path));
    }

    public function testInspectReturnsEmptyForTruncatedFile(): void
    {
        $path = $this->tempDir . '/truncated.safetensors';
        \file_put_contents($path, \pack('P', 99999) . '{"x":');

        self::assertSame([], SafetensorsInspector::inspect($path));
    }

    public function testSizeBytes(): void
    {
        $path = $this->tempDir . '/model.safetensors';
        $this->writeTestFile($path, [
            'w' => ['dtype' => 'F32', 'shape' => [1], 'data' => "\x00\x00\x00\x00"],
        ]);

        // header (8 + ~40 bytes JSON) + 4 bytes data
        $expectedSize = \filesize($path);
        self::assertGreaterThan(0, SafetensorsInspector::sizeBytes($path));
        self::assertSame($expectedSize, SafetensorsInspector::sizeBytes($path));
    }

    /**
     * @param array<string, array{dtype: string, shape: int[], data: string}> $tensors
     */
    private function writeTestFile(string $path, array $tensors): void
    {
        $offset = 0;
        $meta = [];

        foreach ($tensors as $name => $t) {
            $size = \strlen($t['data']);
            $meta[$name] = [
                'dtype' => $t['dtype'],
                'shape' => $t['shape'],
                'data_offsets' => [$offset, $offset + $size],
            ];
            $offset += $size;
        }

        $json = \json_encode($meta, \JSON_UNESCAPED_SLASHES);
        \file_put_contents($path, \pack('P', \strlen($json)) . $json . \implode('', \array_column($tensors, 'data')));
    }
}
