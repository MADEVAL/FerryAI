<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\ModelHub\CacheManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheManager::class)]
final class CacheManagerTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = \sys_get_temp_dir() . '/ferry-cache-' . \uniqid();
        \mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        \array_map('unlink', \glob($this->cacheDir . '/*'));
        \rmdir($this->cacheDir);
    }

    public function testPutAndGet(): void
    {
        $manager = new CacheManager($this->cacheDir);
        $path = $this->cacheDir . '/test-model.onnx';
        \file_put_contents($path, 'test');
        $manager->put('test-model', $path);

        $cached = $manager->get('test-model');

        self::assertNotNull($cached);
        self::assertFileExists($cached);
    }

    public function testHas(): void
    {
        $manager = new CacheManager($this->cacheDir);
        $path = $this->cacheDir . '/model.bin';
        \file_put_contents($path, 'data');
        $manager->put('model-key', $path);

        self::assertTrue($manager->has('model-key'));
        self::assertFalse($manager->has('nonexistent'));
    }

    public function testGetReturnsNullForMissing(): void
    {
        $manager = new CacheManager($this->cacheDir);

        self::assertNull($manager->get('missing-key'));
    }

    public function testRemove(): void
    {
        $manager = new CacheManager($this->cacheDir);
        $path = $this->cacheDir . '/temp.bin';
        \file_put_contents($path, 'temp');
        $manager->put('temp-key', $path);

        $manager->remove('temp-key');

        self::assertFalse($manager->has('temp-key'));
    }

    public function testCacheSize(): void
    {
        $manager = new CacheManager($this->cacheDir);
        $path = $this->cacheDir . '/sized.bin';
        \file_put_contents($path, \str_repeat('x', 1024));
        $manager->put('sized', $path);

        self::assertGreaterThan(0, $manager->cacheSize());
    }

    public function testList(): void
    {
        $manager = new CacheManager($this->cacheDir);
        $path = $this->cacheDir . '/listed.bin';
        \file_put_contents($path, 'listed');
        $manager->put('listed', $path);

        $list = $manager->list();

        self::assertArrayHasKey('listed', $list);
    }

    public function testClear(): void
    {
        $manager = new CacheManager($this->cacheDir);
        $path = $this->cacheDir . '/clear-test.bin';
        \file_put_contents($path, 'data');
        $manager->put('clear-test', $path);

        $manager->clear();

        self::assertSame(0, $manager->cacheSize());
    }

    public function testPrune(): void
    {
        $manager = new CacheManager($this->cacheDir, 100);
        $path1 = $this->cacheDir . '/m1.bin';
        $path2 = $this->cacheDir . '/m2.bin';
        \file_put_contents($path1, \str_repeat('x', 80));
        \file_put_contents($path2, \str_repeat('x', 80));
        $manager->put('m1', $path1);
        $manager->put('m2', $path2);

        $pruned = $manager->prune();

        self::assertGreaterThan(0, $pruned);
    }
}
