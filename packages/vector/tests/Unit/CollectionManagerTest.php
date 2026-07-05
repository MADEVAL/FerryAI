<?php

declare(strict_types=1);

namespace FerryAI\Vector\Tests\Unit;

use FerryAI\Vector\CollectionManager;
use FerryAI\Vector\SQLiteStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CollectionManager::class)]
final class CollectionManagerTest extends TestCase
{
    private SQLiteStore $store;
    private CollectionManager $manager;

    protected function setUp(): void
    {
        $this->store = new SQLiteStore(':memory:');
        $this->manager = new CollectionManager($this->store);
    }

    public function testCreateAndOpen(): void
    {
        $col = $this->manager->create('my_vectors', 128);

        self::assertSame('my_vectors', $col->collectionName());
        self::assertSame(128, $col->dimension());
    }

    public function testOpenReturnsSameCollection(): void
    {
        $this->manager->create('my_vectors', 64);

        $col = $this->manager->open('my_vectors');

        self::assertSame('my_vectors', $col->collectionName());
    }

    public function testDelete(): void
    {
        $this->manager->create('temp', 10);

        $this->manager->delete('temp');

        self::assertFalse($this->manager->exists('temp'));
    }

    public function testList(): void
    {
        $this->manager->create('a', 1);
        $this->manager->create('b', 2);

        $list = $this->manager->list();

        self::assertContains('a', $list);
        self::assertContains('b', $list);
    }

    public function testExists(): void
    {
        self::assertFalse($this->manager->exists('unknown'));

        $this->manager->create('known', 8);

        self::assertTrue($this->manager->exists('known'));
    }
}
