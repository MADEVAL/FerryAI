<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\CpuBackend\CpuNativeModel;
use FerryAI\ModelPool;
use FerryAI\SharedMemory;
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

    public function testWarmupLoadsModelsViaLoader(): void
    {
        $pool = new ModelPool();

        $pool->warmup(['m1', 'm2'], static fn(string $id): CpuNativeModel => new CpuNativeModel($id, []));

        self::assertSame(2, $pool->size());
        self::assertInstanceOf(CpuNativeModel::class, $pool->acquire('m1'));
    }

    public function testWarmupSkipsAlreadyPooledModels(): void
    {
        $pool = new ModelPool();
        $existing = new CpuNativeModel('m1', []);
        $pool->put('m1', $existing);

        $pool->warmup(['m1'], static fn(string $id): CpuNativeModel => new CpuNativeModel($id, []));

        self::assertSame($existing, $pool->acquire('m1'));
    }

    public function testEvictsOldestWhenOverMemoryLimit(): void
    {
        $pool = new ModelPool(maxMemoryBytes: 1500);

        $pool->put('a', new CpuNativeModel('a', []), 1000);
        $pool->put('b', new CpuNativeModel('b', []), 1000);

        self::assertNull($pool->acquire('a'));
        self::assertNotNull($pool->acquire('b'));
        self::assertLessThanOrEqual(1500, $pool->memoryUsage());
    }

    public function testAcquireRefreshesRecencySoRecentlyUsedModelSurvivesEviction(): void
    {
        $pool = new ModelPool(maxMemoryBytes: 2500);

        $pool->put('a', new CpuNativeModel('a', []), 1000);
        $pool->put('b', new CpuNativeModel('b', []), 1000);

        // Touch 'a' so it becomes most-recently-used; 'b' is now the LRU victim.
        self::assertNotNull($pool->acquire('a'));

        // Adding 'c' forces eviction; the LRU policy must drop 'b', not the just-used 'a'.
        $pool->put('c', new CpuNativeModel('c', []), 1000);

        self::assertNotNull($pool->acquire('a'), 'recently-used model must not be evicted');
        self::assertNull($pool->acquire('b'), 'least-recently-used model should be evicted');
    }

    public function testReleaseDoesNotError(): void
    {
        $pool = new ModelPool();

        $pool->release('any');

        self::assertTrue(true);
    }

    public function testShareModelReturnsFalseWithoutSharedMemory(): void
    {
        $pool = new ModelPool();

        self::assertFalse($pool->shareModel('m1', '/path/model.bin'));
        self::assertFalse($pool->isModelShared('m1'));
    }

    public function testShareModelDelegatesToSharedMemory(): void
    {
        $pool = new ModelPool(null, self::fakeSharedMemory());

        self::assertTrue($pool->shareModel('m1', '/path/model.bin'));
        self::assertTrue($pool->isModelShared('m1'));
    }

    public function testShareModelReturnsFalseWhenUnavailable(): void
    {
        $unavailable = new class implements SharedMemory {
            #[\Override]
            public function isAvailable(): bool
            {
                return false;
            }

            #[\Override]
            public function allocateModel(string $modelId, string $modelPath): int
            {
                return 0;
            }

            #[\Override]
            public function detachModel(string $modelId): void {}

            #[\Override]
            public function isShared(string $modelId): bool
            {
                return false;
            }
        };

        $pool = new ModelPool(null, $unavailable);

        self::assertFalse($pool->shareModel('m1', '/path/model.bin'));
    }

    public function testEvictDetachesSharedModel(): void
    {
        $pool = new ModelPool(null, self::fakeSharedMemory());
        $pool->put('m1', new CpuNativeModel('m1', []));
        $pool->shareModel('m1', '/path/model.bin');

        $pool->evict('m1');

        self::assertFalse($pool->isModelShared('m1'));
    }

    private static function fakeSharedMemory(): SharedMemory
    {
        return new class implements SharedMemory {
            /** @var array<string, string> */
            private array $allocated = [];

            #[\Override]
            public function isAvailable(): bool
            {
                return true;
            }

            #[\Override]
            public function allocateModel(string $modelId, string $modelPath): int
            {
                $this->allocated[$modelId] = $modelPath;

                return 1;
            }

            #[\Override]
            public function detachModel(string $modelId): void
            {
                unset($this->allocated[$modelId]);
            }

            #[\Override]
            public function isShared(string $modelId): bool
            {
                return isset($this->allocated[$modelId]);
            }
        };
    }
}
