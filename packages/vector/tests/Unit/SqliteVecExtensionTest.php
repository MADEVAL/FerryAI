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
    private ?string $previousEnv = null;

    protected function setUp(): void
    {
        $env = \getenv('FERRY_AI_VEC_EXTENSION_LIB');
        $this->previousEnv = $env === false ? null : $env;
        \putenv('FERRY_AI_VEC_EXTENSION_LIB');
    }

    protected function tearDown(): void
    {
        if ($this->previousEnv === null) {
            \putenv('FERRY_AI_VEC_EXTENSION_LIB');
        } else {
            \putenv('FERRY_AI_VEC_EXTENSION_LIB=' . $this->previousEnv);
        }
    }

    public function testIsAvailableReturnsFalseWithoutEnv(): void
    {
        self::assertFalse((new SqliteVecExtension())->isAvailable());
    }

    public function testIsAvailableReturnsFalseWhenLibMissing(): void
    {
        \putenv('FERRY_AI_VEC_EXTENSION_LIB=/nonexistent/vec0.dll');

        self::assertFalse((new SqliteVecExtension())->isAvailable());
    }

    public function testIsLoadedReturnsFalseByDefault(): void
    {
        self::assertFalse((new SqliteVecExtension())->isLoaded());
    }

    public function testLoadReturnsFalseWithoutExtension(): void
    {
        $ext = new SqliteVecExtension();
        $store = new SQLiteStore(':memory:');

        self::assertFalse($ext->load($store));
        self::assertFalse($ext->isLoaded());
    }

    public function testSearchReturnsEmptyWhenNotLoaded(): void
    {
        $ext = new SqliteVecExtension();
        $store = new SQLiteStore(':memory:');

        self::assertSame([], $ext->search($store, 'test', [1.0, 0.0, 0.0], 10));
    }

    public function testCreateIndexIsNoopWhenNotLoaded(): void
    {
        $ext = new SqliteVecExtension();
        $store = new SQLiteStore(':memory:');

        $ext->createIndex($store, 'test', 3);

        self::assertFalse($ext->isLoaded());
    }
}
