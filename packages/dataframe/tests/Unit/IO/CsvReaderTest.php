<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\Tests\Unit\IO;

use FerryAI\Core\Exception\IoException;
use FerryAI\DataFrame\DataFrame;
use FerryAI\DataFrame\IO\CsvReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvReader::class)]
final class CsvReaderTest extends TestCase
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

    private function createCsv(string $content): string
    {
        $path = $this->tmpDir . '/test.csv';
        \file_put_contents($path, $content);

        return $path;
    }

    public function testReadCsvWithHeader(): void
    {
        $path = $this->createCsv("name,age,score\nalice,25,0.8\nbob,30,0.9\n");

        $reader = new CsvReader();
        $df = $reader->read($path, true);

        self::assertSame(2, $df->numRows());
        self::assertSame(['name', 'age', 'score'], $df->columns());
        self::assertSame(['alice', 'bob'], $df->column('name'));
        self::assertSame([25, 30], $df->column('age'));
    }

    public function testReadCsvWithoutHeader(): void
    {
        $path = $this->createCsv("alice,25\nbob,30\n");

        $reader = new CsvReader();
        $df = $reader->read($path, false);

        self::assertSame(2, $df->numRows());
        self::assertSame(['col_0', 'col_1'], $df->columns());
        self::assertSame(['alice', 'bob'], $df->column('col_0'));
    }

    public function testReadCsvWithTabDelimiter(): void
    {
        $path = $this->createCsv("name\tage\tcity\nalice\t25\tparis\n");

        $reader = new CsvReader();
        $df = $reader->read($path, true);

        self::assertSame(1, $df->numRows());
        self::assertSame(['alice'], $df->column('name'));
    }

    public function testReadCsvWithSemicolonDelimiter(): void
    {
        $path = $this->createCsv("name;age;city\nalice;25;paris\n");

        $reader = new CsvReader();
        $df = $reader->read($path, true);

        self::assertSame(1, $df->numRows());
        self::assertSame(['alice'], $df->column('name'));
    }

    public function testReadEmptyCsvReturnsEmptyDataFrame(): void
    {
        $path = $this->createCsv('');

        $reader = new CsvReader();
        $df = $reader->read($path, true);

        self::assertSame(0, $df->numRows());
    }

    public function testReadNonexistentCsvThrowsIoException(): void
    {
        $reader = new CsvReader();

        $this->expectException(IoException::class);

        $reader->read($this->tmpDir . '/nonexistent.csv');
    }

    public function testReadCsvCoercesNumericValues(): void
    {
        $path = $this->createCsv("x,y\n1,2.5\n3,4.0\n");

        $reader = new CsvReader();
        $df = $reader->read($path, true);

        $types = $df->dtypes();

        self::assertSame('int', $types['x']);
        self::assertSame('float', $types['y']);
    }

    public function testReadCsvWithQuotedFields(): void
    {
        $path = $this->createCsv("name,note\nalice,\"hello, world\"\n");

        $reader = new CsvReader();
        $df = $reader->read($path, true);

        self::assertSame(['alice'], $df->column('name'));
        self::assertSame(['hello, world'], $df->column('note'));
    }

    public function testFromCsvStaticMethod(): void
    {
        $path = $this->createCsv("a,b\n1,2\n");

        $df = DataFrame::fromCsv($path, true);

        self::assertSame(1, $df->numRows());
        self::assertSame(['a', 'b'], $df->columns());
    }
}
