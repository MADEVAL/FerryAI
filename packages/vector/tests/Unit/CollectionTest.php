<?php

declare(strict_types=1);

namespace FerryAI\Vector\Tests\Unit;

use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Vector\Collection;
use FerryAI\Vector\SQLiteStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Collection::class)]
final class CollectionTest extends TestCase
{
    private SQLiteStore $store;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->store = new SQLiteStore(':memory:');
        $this->store->createCollection('test', 3);
        $this->collection = new Collection('test', 3, $this->store);
    }

    public function testCollectionName(): void
    {
        self::assertSame('test', $this->collection->collectionName());
    }

    public function testDimension(): void
    {
        self::assertSame(3, $this->collection->dimension());
    }

    public function testAddAndCount(): void
    {
        $this->collection->add('id1', [0.1, 0.2, 0.3]);

        self::assertSame(1, $this->collection->count());
    }

    public function testAddWithMetadata(): void
    {
        $this->collection->add('id1', [1.0, 2.0, 3.0], ['label' => 'A']);

        $results = $this->collection->search([1.0, 2.0, 3.0], 1);

        self::assertCount(1, $results);
        self::assertSame('id1', $results[0]['id']);
        self::assertSame(['label' => 'A'], $results[0]['metadata']);
    }

    public function testAddBatch(): void
    {
        $this->collection->addBatch([
            ['id' => 'a', 'vector' => [1.0, 0.0, 0.0]],
            ['id' => 'b', 'vector' => [0.0, 1.0, 0.0]],
        ]);

        self::assertSame(2, $this->collection->count());
    }

    public function testSearchReturnsCorrectResults(): void
    {
        $this->collection->add('a', [1.0, 0.0, 0.0]);
        $this->collection->add('b', [0.0, 1.0, 0.0]);
        $this->collection->add('c', [0.0, 0.0, 1.0]);

        $results = $this->collection->search([1.0, 0.0, 0.0], 2);

        self::assertCount(2, $results);
        self::assertSame('a', $results[0]['id']);
    }

    public function testSearchWithFilter(): void
    {
        $this->collection->add('a', [1.0, 0.0, 0.0], ['label' => 'X']);
        $this->collection->add('b', [0.9, 0.1, 0.0], ['label' => 'Y']);

        $results = $this->collection->search([1.0, 0.0, 0.0], 10, ['label' => ['eq' => 'Y']]);

        self::assertCount(1, $results);
        self::assertSame('b', $results[0]['id']);
    }

    public function testDelete(): void
    {
        $this->collection->add('id1', [1.0, 2.0, 3.0]);

        $this->collection->delete('id1');

        self::assertSame(0, $this->collection->count());
    }

    public function testDeleteByFilter(): void
    {
        $this->collection->add('a', [1.0, 0.0, 0.0], ['label' => 'X']);
        $this->collection->add('b', [0.0, 1.0, 0.0], ['label' => 'Y']);
        $this->collection->add('c', [0.0, 0.0, 1.0], ['label' => 'X']);

        $deleted = $this->collection->deleteByFilter(['label' => ['eq' => 'X']]);

        self::assertSame(2, $deleted);
        self::assertSame(1, $this->collection->count());
    }

    public function testUpdate(): void
    {
        $this->collection->add('id1', [1.0, 2.0, 3.0], ['label' => 'old']);

        $this->collection->update('id1', null, ['label' => 'new']);

        $results = $this->collection->search([1.0, 2.0, 3.0], 1);
        self::assertSame(['label' => 'new'], $results[0]['metadata']);
    }

    public function testUpdateVector(): void
    {
        $this->collection->add('id1', [1.0, 0.0, 0.0]);

        $this->collection->update('id1', [0.0, 1.0, 0.0]);

        $results = $this->collection->search([0.0, 1.0, 0.0], 1);
        self::assertSame('id1', $results[0]['id']);
    }

    public function testClear(): void
    {
        $this->collection->add('a', [1.0, 0.0, 0.0]);
        $this->collection->add('b', [0.0, 1.0, 0.0]);

        $this->collection->clear();

        self::assertSame(0, $this->collection->count());
    }

    public function testIterator(): void
    {
        $this->collection->add('a', [1.0, 0.0, 0.0]);
        $this->collection->add('b', [0.0, 1.0, 0.0]);

        $ids = [];

        foreach ($this->collection->iterator() as $item) {
            $ids[] = $item['id'];
        }

        self::assertContains('a', $ids);
        self::assertContains('b', $ids);
    }

    public function testExport(): void
    {
        $this->collection->add('a', [1.0, 0.0, 0.0], ['label' => 'A']);

        $data = $this->collection->export();

        self::assertCount(1, $data);
        self::assertSame('a', $data[0]['id']);
        self::assertSame([1.0, 0.0, 0.0], $data[0]['vector']);
    }

    public function testExportEmptyCollection(): void
    {
        self::assertSame([], $this->collection->export());
    }

    public function testImplementsVectorStore(): void
    {
        self::assertInstanceOf(VectorStore::class, $this->collection);
    }
}
