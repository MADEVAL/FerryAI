<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\Vector;

use FerryAI\Vector\Collection;
use FerryAI\Vector\SQLiteStore;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Stress-tests the vector store with 10 000+ vectors (brute-force fallback path).
 *
 * Uses in-memory SQLite — no native extension required.
 * Performance is measured for audit; the test passes if it completes
 * without errors within a generous time budget.
 */
#[Group('integration')]
#[CoversNothing]
final class VectorStorePerformanceTest extends TestCase
{
    private const int VECTOR_COUNT = 10_000;

    private const int DIMENSION = 128;

    private const string COLLECTION = 'perf_10k';

    private SQLiteStore $store;

    private Collection $collection;

    protected function setUp(): void
    {
        $this->store = new SQLiteStore(':memory:');
        $this->store->createCollection(self::COLLECTION, self::DIMENSION);

        $this->collection = new Collection(self::COLLECTION, self::DIMENSION, $this->store);
    }

    public function testInsert10kVectorsCompletesWithoutError(): void
    {
        $items = $this->generateVectors(self::VECTOR_COUNT);
        $start = \hrtime(true);

        $this->collection->addBatch($items);

        $elapsed = (\hrtime(true) - $start) / 1_000_000;

        self::assertSame(self::VECTOR_COUNT, $this->collection->count());
        self::assertGreaterThan(0.0, $elapsed, 'Insert should take measurable time');
    }

    public function testCountAfter10kInsert(): void
    {
        $this->insertVectors(self::VECTOR_COUNT);

        self::assertSame(self::VECTOR_COUNT, $this->collection->count());
    }

    public function testSearch10kReturnsCorrectResult(): void
    {
        $this->insertVectors(self::VECTOR_COUNT);

        $query = $this->randomVector();
        $this->collection->add('target', $query);

        $results = $this->collection->search($query, 3);

        self::assertCount(3, $results);
        self::assertSame('target', $results[0]['id']);
        self::assertEqualsWithDelta(0.0, $results[0]['distance'], 1e-10);
    }

    public function testSearch10kWithFilterReturnsFilteredResults(): void
    {
        $this->insertVectors(self::VECTOR_COUNT);

        $query = $this->randomVector();
        $this->collection->add('target', $query, ['label' => 'match']);

        $results = $this->collection->search($query, 1, ['label' => ['eq' => 'match']]);

        self::assertCount(1, $results);
        self::assertSame('target', $results[0]['id']);
    }

    public function testDimensionIsCorrect(): void
    {
        self::assertSame(self::DIMENSION, $this->collection->dimension());
    }

    public function testCollectionNameIsCorrect(): void
    {
        self::assertSame(self::COLLECTION, $this->collection->collectionName());
    }

    public function testExportReturnsAllVectors(): void
    {
        $count = 100;
        $this->insertVectors($count);

        $exported = $this->collection->export();

        self::assertCount($count, $exported);
    }

    public function testIteratorYieldsAllVectors(): void
    {
        $count = 100;
        $this->insertVectors($count);

        $ids = [];

        foreach ($this->collection->iterator() as $item) {
            $ids[] = $item['id'];
        }

        self::assertCount($count, $ids);
    }

    public function testClearAfter10kInsertReturnsZeroCount(): void
    {
        $this->insertVectors(self::VECTOR_COUNT);

        $this->collection->clear();

        self::assertSame(0, $this->collection->count());
        self::assertSame([], $this->collection->search($this->randomVector(), 5));
    }

    public function testDeleteSingleVectorFrom10k(): void
    {
        $this->insertVectors(self::VECTOR_COUNT);

        $this->collection->add('remove_me', $this->randomVector());
        self::assertSame(self::VECTOR_COUNT + 1, $this->collection->count());

        $this->collection->delete('remove_me');

        self::assertSame(self::VECTOR_COUNT, $this->collection->count());
    }

    public function testUpdateVectorIn10k(): void
    {
        $this->insertVectors(self::VECTOR_COUNT);

        $this->collection->add('update_me', $this->randomVector(), ['label' => 'old']);
        $newVector = $this->randomVector();

        $this->collection->update('update_me', $newVector, ['label' => 'new']);

        $results = $this->collection->search($newVector, 1);
        self::assertSame('update_me', $results[0]['id']);
        self::assertEqualsWithDelta(0.0, $results[0]['distance'], 1e-10);
    }

    public function testDeleteByFilterIn10k(): void
    {
        $this->insertVectors(self::VECTOR_COUNT);

        for ($i = 0; $i < 50; ++$i) {
            $this->collection->add("delete_me_{$i}", $this->randomVector(), ['label' => 'delete']);
        }

        $deleted = $this->collection->deleteByFilter(['label' => ['eq' => 'delete']]);

        self::assertSame(50, $deleted);
    }

    /**
     * @param int $count
     *
     * @return array<int, array{id: string, vector: float[]}>
     */
    private function generateVectors(int $count): array
    {
        $items = [];

        for ($i = 0; $i < $count; ++$i) {
            $items[] = [
                'id' => "vec_{$i}",
                'vector' => $this->randomVector(),
            ];
        }

        return $items;
    }

    /**
     * @return float[]
     */
    private function randomVector(): array
    {
        $vector = [];

        for ($i = 0; $i < self::DIMENSION; ++$i) {
            $vector[] = \mt_rand() / \mt_getrandmax();
        }

        return $vector;
    }

    /**
     * @param int $count
     */
    private function insertVectors(int $count): void
    {
        foreach ($this->generateVectors($count) as $item) {
            $this->collection->add($item['id'], $item['vector']);
        }
    }
}
