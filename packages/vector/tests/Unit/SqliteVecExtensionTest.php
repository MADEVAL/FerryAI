<?php

declare(strict_types=1);

namespace FerryAI\Vector\Tests\Unit;

use FerryAI\Vector\SQLiteStore;
use FerryAI\Vector\SqliteVecExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqliteVecExtension::class)]
final class SqliteVecExtensionTest extends TestCase
{
    public function testIsAvailableReturnsFalseByDefault(): void
    {
        $ext = new SqliteVecExtension();

        self::assertFalse($ext->isAvailable());
    }

    public function testIsLoadedReturnsFalseByDefault(): void
    {
        $ext = new SqliteVecExtension();

        self::assertFalse($ext->isLoaded());
    }

    public function testLoadDoesNotError(): void
    {
        $ext = new SqliteVecExtension();
        $store = new SQLiteStore(':memory:');

        $ext->load($store);

        self::assertFalse($ext->isLoaded());
    }

    public function testSearchReturnsEmptyArray(): void
    {
        $ext = new SqliteVecExtension();

        $results = $ext->search('test', 'blob', 10);

        self::assertSame([], $results);
    }

    public function testCreateIndexDoesNotError(): void
    {
        $ext = new SqliteVecExtension();
        $ext->createIndex('test', 'hnsw');

        self::assertTrue(true);
    }
}
