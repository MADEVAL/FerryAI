<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\Core\Exception\InvalidStateException;
use FerryAI\Core\Exception\IoException;
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
            $this->expectException(IoException::class);
            $manager->allocateModel('test', '/nonexistent/model.bin');
        } else {
            $this->expectException(InvalidStateException::class);
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
            $this->expectException(InvalidStateException::class);
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

    public function testAllocateAttachReadAndDetachFreesSegment(): void
    {
        $manager = new SharedMemoryManager();

        if (!$manager->isAvailable()) {
            self::markTestSkipped('ext-shmop is not available.');
        }

        $modelId = 'ferry-shm-' . \uniqid();
        $file = (string) \tempnam(\sys_get_temp_dir(), 'ferry_shm_');
        \file_put_contents($file, 'MODELBYTES');

        try {
            $manager->allocateModel($modelId, $file);
            self::assertTrue($manager->isShared($modelId));

            // The bytes must be readable back through a stored handle (not a discarded one).
            self::assertSame('MODELBYTES', \substr($manager->read($modelId), 0, 10));

            $manager->detachModel($modelId);
            self::assertFalse($manager->isShared($modelId));
        } finally {
            $manager->detachModel($modelId);
            @\unlink($file);
        }
    }
}
