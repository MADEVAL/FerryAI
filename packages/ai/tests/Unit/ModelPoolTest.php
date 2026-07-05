<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\CpuBackend\CpuNativeModel;
use FerryAI\ModelPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelPool::class)]
final class ModelPoolTest extends TestCase
{
    public function testSizeIsInitiallyZero(): void
    {
        $pool = new ModelPool();

        self::assertSame(0, $pool->size());
    }

    public function testPutAndAcquire(): void
    {
        $pool = new ModelPool();
        $model = new CpuNativeModel('test', []);
        $pool->put('test-model', $model);

        self::assertSame(1, $pool->size());
        self::assertSame($model, $pool->acquire('test-model'));
    }

    public function testAcquireReturnsNullForMissingModel(): void
    {
        $pool = new ModelPool();

        self::assertNull($pool->acquire('nonexistent'));
    }

    public function testEvictRemovesModel(): void
    {
        $pool = new ModelPool();
        $model = new CpuNativeModel('test', []);
        $pool->put('test-model', $model);

        $pool->evict('test-model');

        self::assertSame(0, $pool->size());
        self::assertNull($pool->acquire('test-model'));
    }

    public function testMemoryUsageTracksSize(): void
    {
        $pool = new ModelPool();
        $model = new CpuNativeModel('test', []);
        $pool->put('test', $model, 1024);

        self::assertSame(1024, $pool->memoryUsage());
    }

    public function testWarmupDoesNotError(): void
    {
        $pool = new ModelPool();

        $pool->warmup(['nonexistent-model']);

        self::assertSame(0, $pool->size());
    }

    public function testReleaseDoesNotError(): void
    {
        $pool = new ModelPool();

        $pool->release('any');

        self::assertTrue(true);
    }
}
