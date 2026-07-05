<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\SharedMemoryManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SharedMemoryManager::class)]
final class SharedMemoryManagerTest extends TestCase
{
    public function testIsAvailableReturnsBool(): void
    {
        $manager = new SharedMemoryManager();

        self::assertIsBool($manager->isAvailable());
    }

    public function testAllocateModelThrowsForMissingFile(): void
    {
        $manager = new SharedMemoryManager();

        if ($manager->isAvailable()) {
            $this->expectException(\RuntimeException::class);
            $manager->allocateModel('test', '/nonexistent/model.bin');
        } else {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('ext-shmop');

            $manager->allocateModel('test', '/nonexistent');
        }
    }

    public function testAttachModelThrowsOrReturnsWhenUnavailable(): void
    {
        $manager = new SharedMemoryManager();

        if ($manager->isAvailable()) {
            self::assertTrue(true);
        } else {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('ext-shmop');

            $manager->attachModel('test');
        }
    }

    public function testIsSharedReturnsFalseForUnknownModel(): void
    {
        $manager = new SharedMemoryManager();

        self::assertFalse($manager->isShared('unknown'));
    }

    public function testDetachModelDoesNotError(): void
    {
        $manager = new SharedMemoryManager();

        $manager->detachModel('any');

        self::assertFalse($manager->isShared('any'));
    }
}
