<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\Sqlite;

use FerryAI\Vector\Collection;
use FerryAI\Vector\SQLiteStore;
use FerryAI\Vector\SqliteVecExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real sqlite-vec (vec0) loadable extension.
 *
 * Skipped automatically when Pdo\Sqlite is missing, the vec0 library is absent,
 * or it cannot be loaded into the connection.
 */
#[Group('integration')]
#[CoversNothing]
final class SqliteVecIntegrationTest extends TestCase
{
    private ?string $previousEnv = null;
    private string $lib = '';

    protected function setUp(): void
    {
        if (getenv('FERRY_AI_SKIP_NATIVE') === '1') {
            self::markTestSkipped('Native tests skipped via FERRY_AI_SKIP_NATIVE=1.');
        }

        if (!\class_exists(\Pdo\Sqlite::class)) {
            self::markTestSkipped('Pdo\\Sqlite is not available.');
        }

        $env = getenv('FERRY_AI_VEC_EXTENSION_LIB');
        $this->previousEnv = $env === false ? null : $env;

        $this->lib = ($env !== false && $env !== '') ? $env : 'D:\\FerryAI\\vec0.dll';

        if (!\is_file($this->lib)) {
            self::markTestSkipped('sqlite-vec library not found: ' . $this->lib);
        }

        \putenv('FERRY_AI_VEC_EXTENSION_LIB=' . $this->lib);

        $probe = new SqliteVecExtension();

        if (!$probe->load(new SQLiteStore(':memory:'))) {
            self::markTestSkipped('sqlite-vec could not be loaded into the connection.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->previousEnv === null) {
            \putenv('FERRY_AI_VEC_EXTENSION_LIB');
        } else {
            \putenv('FERRY_AI_VEC_EXTENSION_LIB=' . $this->previousEnv);
        }
    }

    public function testExtensionLoadsAndReportsAvailable(): void
    {
        $ext = new SqliteVecExtension();
        $store = new SQLiteStore(':memory:');

        self::assertTrue($ext->isAvailable());
        self::assertTrue($ext->load($store));
        self::assertTrue($ext->isLoaded());
    }

    public function testExtensionCrudAndKnnSearch(): void
    {
        $store = new SQLiteStore(':memory:');
        $ext = new SqliteVecExtension();
        $ext->load($store);
        $ext->createIndex($store, 'items', 3, 'cosine');

        $ext->upsert($store, 'items', 'a', [1.0, 0.0, 0.0]);
        $ext->upsert($store, 'items', 'b', [0.0, 1.0, 0.0]);
        $ext->upsert($store, 'items', 'c', [0.9, 0.1, 0.0]);

        $results = $ext->search($store, 'items', [1.0, 0.0, 0.0], 2);

        self::assertCount(2, $results);
        self::assertSame('a', $results[0]['id']);
        self::assertSame('c', $results[1]['id']);

        $ext->remove($store, 'items', 'a');
        $after = $ext->search($store, 'items', [1.0, 0.0, 0.0], 2);
        self::assertSame('c', $after[0]['id']);
    }

    public function testCollectionUsesVecIndexForSearch(): void
    {
        $store = new SQLiteStore(':memory:');
        $store->createCollection('docs', 3);

        $collection = new Collection('docs', 3, $store);

        $collection->add('a', [1.0, 0.0, 0.0], ['label' => 'A']);
        $collection->add('b', [0.0, 1.0, 0.0]);
        $collection->add('c', [0.9, 0.1, 0.0]);

        $results = $collection->search([1.0, 0.0, 0.0], 2);

        self::assertCount(2, $results);
        self::assertSame('a', $results[0]['id']);
        self::assertSame(['label' => 'A'], $results[0]['metadata']);

        $collection->delete('a');
        $afterDelete = $collection->search([1.0, 0.0, 0.0], 1);
        self::assertSame('c', $afterDelete[0]['id']);

        $collection->clear();
        self::assertSame([], $collection->search([1.0, 0.0, 0.0], 5));
    }

    public function testCollectionFilteredSearchStillWorks(): void
    {
        $store = new SQLiteStore(':memory:');
        $store->createCollection('docs', 3);

        $collection = new Collection('docs', 3, $store);
        $collection->add('a', [1.0, 0.0, 0.0], ['label' => 'X']);
        $collection->add('b', [0.9, 0.1, 0.0], ['label' => 'Y']);

        $results = $collection->search([1.0, 0.0, 0.0], 10, ['label' => ['eq' => 'Y']]);

        self::assertCount(1, $results);
        self::assertSame('b', $results[0]['id']);
    }
}
