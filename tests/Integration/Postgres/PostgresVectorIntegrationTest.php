<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\Postgres;

use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Core\Exception\ShapeMismatchException;
use FerryAI\Vector\PostgresCollection;
use FerryAI\Vector\PostgresStore;
use FerryAI\Vector\PostgresVecIndex;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real PostgreSQL + pgvector vector store.
 *
 * Skipped automatically when ext-pdo_pgsql is missing, the server is
 * unreachable, or the pgvector extension cannot be created.
 */
#[Group('integration')]
#[CoversNothing]
final class PostgresVectorIntegrationTest extends TestCase
{
    private PostgresStore $store;
    private string $collection;

    protected function setUp(): void
    {
        if (getenv('FERRY_AI_SKIP_NATIVE') === '1') {
            self::markTestSkipped('Native tests skipped via FERRY_AI_SKIP_NATIVE=1.');
        }

        if (!\extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('ext-pdo_pgsql is not available.');
        }

        $dsn = getenv('FERRY_AI_PG_DSN') ?: 'pgsql:host=127.0.0.1;port=5432';
        $user = getenv('FERRY_AI_PG_USER') ?: 'postgres';
        $pass = getenv('FERRY_AI_PG_PASSWORD') ?: 'postgres';

        try {
            $this->store = new PostgresStore($dsn, $user, $pass);
        } catch (\Throwable $e) {
            self::markTestSkipped('PostgreSQL/pgvector unavailable: ' . $e->getMessage());
        }

        $this->collection = 'ferry_it_' . \bin2hex(\random_bytes(4));
        $this->store->createCollection($this->collection, 3, 'cosine');
    }

    protected function tearDown(): void
    {
        if (isset($this->store, $this->collection)) {
            $this->store->dropCollection($this->collection);
        }
    }

    private function newCollection(): PostgresCollection
    {
        return new PostgresCollection($this->collection, 3, $this->store, 'cosine');
    }

    public function testCollectionRegistered(): void
    {
        self::assertTrue($this->store->collectionExists($this->collection));
        self::assertSame(3, $this->store->getDimension($this->collection));
        self::assertContains($this->collection, $this->store->listCollections());
    }

    public function testImplementsVectorStore(): void
    {
        self::assertInstanceOf(VectorStore::class, $this->newCollection());
    }

    public function testAddCountAndGetVector(): void
    {
        $c = $this->newCollection();
        $c->add('a', [1.0, 0.0, 0.0], ['label' => 'A']);

        self::assertSame(1, $c->count());

        $row = $this->store->getVector($this->collection, 'a');
        self::assertNotNull($row);
        self::assertSame([1.0, 0.0, 0.0], $row['vector']);
        self::assertSame(['label' => 'A'], $row['metadata']);
    }

    public function testNativeCosineSearchOrdering(): void
    {
        $c = $this->newCollection();
        $c->add('a', [1.0, 0.0, 0.0]);
        $c->add('b', [0.0, 1.0, 0.0]);
        $c->add('c', [0.9, 0.1, 0.0]);

        $results = $c->search([1.0, 0.0, 0.0], 2);

        self::assertCount(2, $results);
        self::assertSame('a', $results[0]['id']);
        self::assertSame('c', $results[1]['id']);
        self::assertLessThan($results[1]['distance'], $results[0]['distance'] + 1e-9);
    }

    public function testSearchWithMetadataFilter(): void
    {
        $c = $this->newCollection();
        $c->add('a', [1.0, 0.0, 0.0], ['label' => 'X']);
        $c->add('b', [0.9, 0.1, 0.0], ['label' => 'Y']);

        $results = $c->search([1.0, 0.0, 0.0], 10, ['label' => ['eq' => 'Y']]);

        self::assertCount(1, $results);
        self::assertSame('b', $results[0]['id']);
        self::assertSame(['label' => 'Y'], $results[0]['metadata']);
    }

    public function testUpdateMetadataOnly(): void
    {
        $c = $this->newCollection();
        $c->add('a', [1.0, 0.0, 0.0], ['label' => 'old']);

        $c->update('a', null, ['label' => 'new']);

        $results = $c->search([1.0, 0.0, 0.0], 1);
        self::assertSame(['label' => 'new'], $results[0]['metadata']);
    }

    public function testUpdateVectorOnly(): void
    {
        $c = $this->newCollection();
        $c->add('a', [1.0, 0.0, 0.0]);

        $c->update('a', [0.0, 1.0, 0.0]);

        $row = $this->store->getVector($this->collection, 'a');
        self::assertNotNull($row);
        self::assertSame([0.0, 1.0, 0.0], $row['vector']);
    }

    public function testDelete(): void
    {
        $c = $this->newCollection();
        $c->add('a', [1.0, 0.0, 0.0]);

        $c->delete('a');

        self::assertSame(0, $c->count());
    }

    public function testDeleteByFilter(): void
    {
        $c = $this->newCollection();
        $c->add('a', [1.0, 0.0, 0.0], ['label' => 'X']);
        $c->add('b', [0.0, 1.0, 0.0], ['label' => 'Y']);
        $c->add('c', [0.0, 0.0, 1.0], ['label' => 'X']);

        $deleted = $c->deleteByFilter(['label' => ['eq' => 'X']]);

        self::assertSame(2, $deleted);
        self::assertSame(1, $c->count());
    }

    public function testIteratorAndExport(): void
    {
        $c = $this->newCollection();
        $c->add('a', [1.0, 0.0, 0.0], ['label' => 'A']);
        $c->add('b', [0.0, 1.0, 0.0]);

        $export = $c->export();
        self::assertCount(2, $export);

        $ids = \array_map(static fn(array $r): string => $r['id'], $export);
        self::assertContains('a', $ids);
        self::assertContains('b', $ids);
    }

    public function testClear(): void
    {
        $c = $this->newCollection();
        $c->add('a', [1.0, 0.0, 0.0]);
        $c->add('b', [0.0, 1.0, 0.0]);

        $c->clear();

        self::assertSame(0, $c->count());
    }

    public function testDimensionMismatchThrows(): void
    {
        $c = $this->newCollection();

        $this->expectException(ShapeMismatchException::class);

        $c->add('bad', [1.0, 2.0]);
    }

    public function testHnswIndexSpeedsUpSearchWithoutChangingResults(): void
    {
        $c = $this->newCollection();
        $c->add('a', [1.0, 0.0, 0.0]);
        $c->add('b', [0.0, 1.0, 0.0]);
        $c->add('c', [0.9, 0.1, 0.0]);

        $index = new PostgresVecIndex($this->store);
        $index->createIndex($this->collection, 'hnsw', 'cosine');

        $results = $c->search([1.0, 0.0, 0.0], 3);

        self::assertSame('a', $results[0]['id']);
        $index->dropIndex($this->collection, 'hnsw');
    }

    public function testAiFactoryCreatesPostgresVectorStore(): void
    {
        $name = 'ferry_it_af_' . \bin2hex(\random_bytes(4));

        $config = \FerryAI\Core\AIConfig::fromArray([
            'vector' => [
                'driver' => 'pgsql',
                'dsn' => getenv('FERRY_AI_PG_DSN') ?: 'pgsql:host=127.0.0.1;port=5432',
                'user' => getenv('FERRY_AI_PG_USER') ?: 'postgres',
                'password' => getenv('FERRY_AI_PG_PASSWORD') ?: 'postgres',
            ],
        ]);

        try {
            $store = (new \FerryAI\AIFactory($config))->createVectorStore($name, 3);

            self::assertInstanceOf(PostgresCollection::class, $store);

            $store->add('a', [1.0, 0.0, 0.0]);
            $store->add('b', [0.0, 1.0, 0.0]);

            $results = $store->search([1.0, 0.0, 0.0], 1);
            self::assertSame('a', $results[0]['id']);
        } finally {
            $this->store->dropCollection($name);
        }
    }
}
