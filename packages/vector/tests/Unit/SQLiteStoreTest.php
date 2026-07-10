<?php

declare(strict_types=1);

namespace FerryAI\Vector\Tests\Unit;

use FerryAI\Vector\SQLiteStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SQLiteStore::class)]
final class SQLiteStoreTest extends TestCase
{
    private SQLiteStore $store;

    protected function setUp(): void
    {
        $this->store = new SQLiteStore(':memory:');
    }

    public function testCreateCollection(): void
    {
        $this->store->createCollection('test_collection', 128);

        self::assertTrue($this->store->collectionExists('test_collection'));
    }

    public function testCreateCollectionRejectsUnsafeName(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);
        $this->store->createCollection('a"b', 3);
    }

    public function testInsertVectorRejectsUnsafeCollectionName(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);
        $this->store->insertVector('x"; DROP TABLE t; --', 'id', \pack('f*', 1.0));
    }

    public function testCollectionExistsReturnsFalseForUnknown(): void
    {
        self::assertFalse($this->store->collectionExists('nonexistent'));
    }

    public function testInsertAndGetVector(): void
    {
        $this->store->createCollection('vectors', 3);
        $blob = \pack('f*', 0.1, 0.2, 0.3);

        $this->store->insertVector('vectors', 'id1', $blob, '{"label":"A"}');

        $result = $this->store->getVector('vectors', 'id1');
        self::assertSame('id1', $result['id']);
        self::assertSame($blob, $result['vector']);
        self::assertSame('{"label":"A"}', $result['metadata']);
    }

    public function testGetVectorReturnsNullForMissing(): void
    {
        $this->store->createCollection('vectors', 2);

        $result = $this->store->getVector('vectors', 'missing');

        self::assertNull($result);
    }

    public function testDeleteVector(): void
    {
        $this->store->createCollection('vectors', 3);
        $blob = \pack('f*', 0.1, 0.2, 0.3);
        $this->store->insertVector('vectors', 'id1', $blob);

        $this->store->deleteVector('vectors', 'id1');

        self::assertNull($this->store->getVector('vectors', 'id1'));
    }

    public function testCountVectors(): void
    {
        $this->store->createCollection('vectors', 2);
        $blob = \pack('f*', 0.1, 0.2);

        self::assertSame(0, $this->store->countVectors('vectors'));

        $this->store->insertVector('vectors', 'a', $blob);
        $this->store->insertVector('vectors', 'b', $blob);

        self::assertSame(2, $this->store->countVectors('vectors'));
    }

    public function testIterateVectors(): void
    {
        $this->store->createCollection('vectors', 2);
        $blob1 = \pack('f*', 0.1, 0.2);
        $blob2 = \pack('f*', 0.3, 0.4);
        $this->store->insertVector('vectors', 'a', $blob1);
        $this->store->insertVector('vectors', 'b', $blob2);

        $ids = [];

        foreach ($this->store->iterateVectors('vectors') as $row) {
            $ids[] = $row['id'];
        }

        self::assertSame(['a', 'b'], $ids);
    }

    public function testClearCollection(): void
    {
        $this->store->createCollection('vectors', 2);
        $blob = \pack('f*', 0.1, 0.2);
        $this->store->insertVector('vectors', 'a', $blob);
        $this->store->insertVector('vectors', 'b', $blob);

        $this->store->clearCollection('vectors');

        self::assertSame(0, $this->store->countVectors('vectors'));
    }

    public function testInsertVectorReplacesExisting(): void
    {
        $this->store->createCollection('vectors', 2);
        $blob1 = \pack('f*', 0.1, 0.2);
        $blob2 = \pack('f*', 0.3, 0.4);

        $this->store->insertVector('vectors', 'id1', $blob1);
        $this->store->insertVector('vectors', 'id1', $blob2);

        $result = $this->store->getVector('vectors', 'id1');
        self::assertSame($blob2, $result['vector']);
    }
}
