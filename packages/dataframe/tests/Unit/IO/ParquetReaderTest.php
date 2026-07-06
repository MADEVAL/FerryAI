<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\Tests\Unit\IO;

use FerryAI\Core\Exception\IoException;
use FerryAI\DataFrame\IO\ParquetReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParquetReader::class)]
final class ParquetReaderTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__ . '/fixtures';
    }

    public function testRejectsNonParquetFile(): void
    {
        $reader = new ParquetReader();
        $notParquet = \sys_get_temp_dir() . '/not_parquet_' . \uniqid() . '.txt';
        \file_put_contents($notParquet, 'hello world');

        try {
            $this->expectException(IoException::class);
            $reader->read($notParquet);
        } finally {
            \unlink($notParquet);
        }
    }

    public function testRejectsNonexistentFile(): void
    {
        $reader = new ParquetReader();

        $this->expectException(IoException::class);

        $reader->read('/nonexistent/path.parquet');
    }

    public function testValidatesMagicBytesButReportsNotImplemented(): void
    {
        if (!\is_file($this->fixtureDir . '/simple.parquet')) {
            self::markTestSkipped('Parquet fixture not found.');
        }

        $reader = new ParquetReader();

        $this->expectException(IoException::class);
        $this->expectExceptionMessageMatches('/not yet implemented/i');

        $reader->read($this->fixtureDir . '/simple.parquet');
    }
}
