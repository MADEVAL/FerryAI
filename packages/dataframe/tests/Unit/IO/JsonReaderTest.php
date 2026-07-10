<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\Tests\Unit\IO;

use FerryAI\Core\Exception\IoException;
use FerryAI\DataFrame\IO\JsonReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonReader::class)]
final class JsonReaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = \sys_get_temp_dir() . '/ferry_json_test_' . \uniqid();
        \mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tmpDir);
    }

    private function rmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        foreach (\array_diff((array) \scandir($dir), ['.', '..']) as $item) {
            $path = $dir . \DIRECTORY_SEPARATOR . $item;
            \is_dir($path) ? $this->rmdir($path) : \unlink($path);
        }

        \rmdir($dir);
    }

    private function createFile(string $name, string $content): string
    {
        $path = $this->tmpDir . '/' . $name;
        \file_put_contents($path, $content);

        return $path;
    }

    public function testReadJsonArrayOfObjects(): void
    {
        $path = $this->createFile('test.json', <<<'JSON'
[
    {"name": "alice", "age": 25},
    {"name": "bob", "age": 30}
]
JSON);

        $reader = new JsonReader();
        $df = $reader->read($path);

        self::assertSame(2, $df->numRows());
        self::assertSame(['name', 'age'], $df->columns());
        self::assertSame(['alice', 'bob'], $df->column('name'));
    }

    public function testReadNdjson(): void
    {
        $path = $this->createFile('test.ndjson', <<<'NDJSON'
{"x": 1, "y": 2}
{"x": 3, "y": 4}
{"x": 5, "y": 6}
NDJSON);

        $reader = new JsonReader();
        $df = $reader->read($path);

        self::assertSame(3, $df->numRows());
        self::assertSame(['x', 'y'], $df->columns());
        self::assertSame([1, 3, 5], $df->column('x'));
    }

    public function testReadNdjsonWithTrailingNewline(): void
    {
        $path = $this->createFile('test.ndjson', "{\"a\": 1}\n{\"a\": 2}\n\n");

        $reader = new JsonReader();
        $df = $reader->read($path);

        self::assertSame(2, $df->numRows());
    }

    public function testReadEmptyJsonFile(): void
    {
        $path = $this->createFile('empty.json', '');

        $reader = new JsonReader();
        $df = $reader->read($path);

        self::assertSame(0, $df->numRows());
    }

    public function testReadNonexistentFileThrowsIoException(): void
    {
        $reader = new JsonReader();

        $this->expectException(IoException::class);

        $reader->read($this->tmpDir . '/nonexistent.json');
    }

    public function testReadJsonInfersColumnTypes(): void
    {
        $path = $this->createFile('test.json', <<<'JSON'
[{"int_col": 10, "float_col": 3.14, "str_col": "hello"}]
JSON);

        $reader = new JsonReader();
        $df = $reader->read($path);

        $types = $df->dtypes();

        self::assertSame('int', $types['int_col']);
        self::assertSame('float', $types['float_col']);
        self::assertSame('string', $types['str_col']);
    }

    public function testReadNdjsonWithVaryingKeys(): void
    {
        $path = $this->createFile('test.ndjson', <<<'NDJSON'
{"a": 1, "b": 2}
{"a": 3, "b": 4, "c": 5}
NDJSON);

        $reader = new JsonReader();
        $df = $reader->read($path);

        self::assertSame(['a', 'b', 'c'], $df->columns());
        self::assertSame([1, 3], $df->column('a'));
        self::assertSame([2, 4], $df->column('b'));
        self::assertSame([null, 5], $df->column('c'));
    }
}
