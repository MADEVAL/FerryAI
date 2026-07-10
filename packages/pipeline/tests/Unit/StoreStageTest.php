<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Tests\Unit;

use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Pipeline\Stages\StoreStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoreStage::class)]
final class StoreStageTest extends TestCase
{
    public function testName(): void
    {
        $store = new StubVectorStoreForStage();
        $stage = new StoreStage($store);

        self::assertSame('store', $stage->name());
    }

    public function testProcessStoresVectorAndReturnsId(): void
    {
        $store = new StubVectorStoreForStage();
        $stage = new StoreStage($store);

        $result = $stage->process(['id' => 'vec1', 'vector' => [1.0, 2.0], 'metadata' => ['label' => 'A']]);

        self::assertSame('vec1', $result);
    }

    public function testProcessGeneratesIdWhenMissing(): void
    {
        $store = new StubVectorStoreForStage();
        $stage = new StoreStage($store);

        $result = $stage->process(['vector' => [1.0, 2.0]]);

        self::assertIsString($result);
        self::assertStringStartsWith('vec_', $result);
    }

    public function testProcessReturnsInputForNonArray(): void
    {
        $store = new StubVectorStoreForStage();
        $stage = new StoreStage($store);

        $result = $stage->process('not-array');

        self::assertSame('not-array', $result);
    }
}

final class StubVectorStoreForStage implements VectorStore
{
    public int $addCalls = 0;
    public string $lastId = '';

    public function add(string $id, array $vector, ?array $metadata = null): void
    {
        $this->addCalls++;
        $this->lastId = $id;
    }
    public function addBatch(array $items): void {}
    public function search(array $queryVector, int $k = 10, ?array $filter = null): array
    {
        return [];
    }
    public function delete(string $id): void {}
    public function deleteByFilter(array $filter): int
    {
        return 0;
    }
    public function update(string $id, ?array $vector = null, ?array $metadata = null): void {}
    public function count(): int
    {
        return 0;
    }
    public function dimension(): int
    {
        return 3;
    }
    public function collectionName(): string
    {
        return 'stub';
    }
    public function iterator(): \Iterator
    {
        return new \EmptyIterator();
    }
    public function export(): array
    {
        return [];
    }
    public function clear(): void {}
}
