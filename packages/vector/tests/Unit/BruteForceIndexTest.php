<?php

declare(strict_types=1);

namespace FerryAI\Vector\Tests\Unit;

use FerryAI\Vector\BruteForceIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BruteForceIndex::class)]
final class BruteForceIndexTest extends TestCase
{
    private BruteForceIndex $index;

    protected function setUp(): void
    {
        $this->index = new BruteForceIndex();
    }

    public function testSearchReturnsTopKResults(): void
    {
        $vectors = [
            ['id' => 'a', 'vector' => [1.0, 0.0]],
            ['id' => 'b', 'vector' => [0.0, 1.0]],
            ['id' => 'c', 'vector' => [0.5, 0.5]],
        ];
        $query = [1.0, 0.0];

        $results = $this->index->search($query, $vectors, 2);

        self::assertCount(2, $results);
        self::assertSame('a', $results[0]['id']);
    }

    public function testSearchWithCosineMetric(): void
    {
        $vectors = [
            ['id' => 'a', 'vector' => [1.0, 0.0]],
            ['id' => 'b', 'vector' => [-1.0, 0.0]],
        ];
        $query = [1.0, 0.0];

        $results = $this->index->search($query, $vectors, 2, 'cosine');

        self::assertCount(2, $results);
        self::assertSame('a', $results[0]['id']);
        self::assertLessThan($results[1]['distance'], $results[0]['distance']);
    }

    public function testSearchWithEuclideanMetric(): void
    {
        $vectors = [
            ['id' => 'a', 'vector' => [0.0, 0.0]],
            ['id' => 'b', 'vector' => [10.0, 0.0]],
        ];
        $query = [0.0, 0.0];

        $results = $this->index->search($query, $vectors, 2, 'euclidean');

        self::assertCount(2, $results);
        self::assertSame('a', $results[0]['id']);
        self::assertEqualsWithDelta(0.0, $results[0]['distance'], 0.0001);
    }

    public function testSearchReturnsEmptyForEmptyVectors(): void
    {
        $results = $this->index->search([1.0, 2.0], [], 5);

        self::assertSame([], $results);
    }

    public function testSearchLimitsResultsToAvailable(): void
    {
        $vectors = [
            ['id' => 'a', 'vector' => [1.0, 0.0]],
        ];

        $results = $this->index->search([0.0, 0.0], $vectors, 10);

        self::assertCount(1, $results);
    }
}
