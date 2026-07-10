<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\ModelHub\StreamLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamLoader::class)]
final class StreamLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/ferry-streamloader-' . \uniqid();
        \mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        \array_map('unlink', \glob($this->tempDir . '/*'));
        \rmdir($this->tempDir);
    }

    public function testLoadMmapReturnsHandle(): void
    {
        $path = $this->tempDir . '/test.bin';
        \file_put_contents($path, 'hello');

        $loader = new StreamLoader();
        $handle = $loader->loadMmap($path);

        self::assertIsResource($handle);
        \fclose($handle);
    }

    public function testLoadMmapReturnsNullForMissingFile(): void
    {
        $loader = new StreamLoader();

        self::assertNull($loader->loadMmap($this->tempDir . '/nonexistent.bin'));
    }

    public function testLoadStreamYieldsChunks(): void
    {
        $path = $this->tempDir . '/stream.bin';
        $data = \str_repeat('A', 3000);
        \file_put_contents($path, $data);

        $loader = new StreamLoader();
        $chunks = \iterator_to_array($loader->loadStream($path, 1024));

        self::assertGreaterThan(0, \count($chunks));
        $full = \implode('', $chunks);
        self::assertSame($data, $full);
    }

    public function testLoadStreamReturnsEmptyForMissingFile(): void
    {
        $loader = new StreamLoader();
        $chunks = \iterator_to_array($loader->loadStream('/nonexistent/file.bin'));

        self::assertSame([], $chunks);
    }
}
