<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\Tests\Unit\IO;

use FerryAI\DataFrame\Column;
use FerryAI\DataFrame\DataFrame;
use FerryAI\DataFrame\IO\CsvReader;
use FerryAI\DataFrame\IO\CsvWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvWriter::class)]
final class CsvWriterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = \sys_get_temp_dir() . '/ferry_csv_test_' . \uniqid();
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

    public function testWriteCsvWithHeader(): void
    {
        $df = new DataFrame(
            new Column('name', 'string', ['alice', 'bob']),
            new Column('age', 'int', [25, 30]),
        );

        $path = $this->tmpDir . '/out.csv';
        $writer = new CsvWriter();
        $writer->write($df, $path, true);

        $expected = "name,age\nalice,25\nbob,30\n";

        self::assertStringEqualsFile($path, $expected);
    }

    public function testWriteCsvWithoutHeader(): void
    {
        $df = new DataFrame(
            new Column('x', 'int', [1, 2]),
        );

        $path = $this->tmpDir . '/out.csv';
        $writer = new CsvWriter();
        $writer->write($df, $path, false);

        $expected = "1\n2\n";

        self::assertStringEqualsFile($path, $expected);
    }

    public function testWriteCsvRoundTrip(): void
    {
        $original = new DataFrame(
            new Column('name', 'string', ['alice', 'bob']),
            new Column('score', 'float', [0.8, 0.9]),
            new Column('count', 'int', [10, 20]),
        );

        $path = $this->tmpDir . '/roundtrip.csv';
        $original->toCsv($path, true);

        $reloaded = (new CsvReader())->read($path, true);

        self::assertSame($original->numRows(), $reloaded->numRows());
        self::assertSame($original->columns(), $reloaded->columns());
        self::assertSame($original->column('name'), $reloaded->column('name'));
        self::assertSame($original->column('count'), $reloaded->column('count'));
        self::assertEqualsWithDelta($original->column('score'), $reloaded->column('score'), 0.0001);
    }

    public function testWriteCsvToNonexistentDirectory(): void
    {
        $df = new DataFrame(
            new Column('a', 'int', [1]),
        );

        $path = $this->tmpDir . '/new_dir/sub_dir/out.csv';
        $writer = new CsvWriter();
        $writer->write($df, $path, true);

        self::assertFileExists($path);
    }
}
