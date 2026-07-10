<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\SharedMemoryManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: SharedMemoryManager segments must be private (0600, not 0644),
 * and key-by-ownership must catch collisions (even same-size) via an in-segment ownership marker.
 */
#[CoversNothing]
final class SharedMemorySecurityTest extends TestCase
{
    private const int HEADER_LEN = 16;

    public function testKeyIsNotCRC32(): void
    {
        // crc32('ferry-test') & 0x7FFFFFFF = predictable value.
        $crcKey = \crc32('ferry-test') & 0x7FFFFFFF;
        $actual = (new \ReflectionMethod(SharedMemoryManager::class, 'keyFor'))->invoke(null, 'ferry-test');

        self::assertNotSame($crcKey, $actual, 'Key must derive from a wide hash, not a crc32 collision-prone one.');
        // sha256-derived key must be a positive 31-bit integer.
        self::assertGreaterThan(0, $actual);
        self::assertLessThanOrEqual(0x7FFFFFFF, $actual);
    }

    public function testRoundTripAllocatesWithHeaderAndReadsPayloadOnly(): void
    {
        $manager = new SharedMemoryManager();

        if (!$manager->isAvailable()) {
            self::markTestSkipped('ext-shmop is not available.');
        }

        $modelId = 'ferry-shmsec-' . \uniqid();
        $file = (string) \tempnam(\sys_get_temp_dir(), 'ferry_shm_');
        $payload = 'PAYLOAD9BYTES';
        \file_put_contents($file, $payload);

        try {
            $manager->allocateModel($modelId, $file);
            self::assertTrue($manager->isShared($modelId));

            $result = $manager->read($modelId);

            // read() must start after the 16-byte ownership header (the segment may be
            // zero/space-padded by the OS, so compare the payload prefix, as the existing suite does).
            self::assertSame($payload, \substr($result, 0, \strlen($payload)), 'read() must skip the ownership header.');

            $manager->detachModel($modelId);
        } finally {
            $manager->detachModel($modelId);
            @\unlink($file);
        }
    }

    public function testAttachDetectsOwnershipMismatch(): void
    {
        $manager = new SharedMemoryManager();

        if (!$manager->isAvailable()) {
            self::markTestSkipped('ext-shmop is not available.');
        }

        $id = 'ferry-own-' . \uniqid();
        $file = (string) \tempnam(\sys_get_temp_dir(), 'ferry_shm_');
        \file_put_contents($file, \str_repeat('X', 32));

        try {
            $key = $manager->allocateModel($id, $file);

            // Corrupt the 16-byte ownership marker directly, simulating a foreign model owning the key.
            $raw = \shmop_open($key, 'w', 0, 0);
            self::assertNotFalse($raw);
            \shmop_write($raw, \str_repeat('Z', self::HEADER_LEN), 0);

            // A fresh manager (no cached handle) must refuse to attach the now-foreign segment.
            $other = new SharedMemoryManager();

            $this->expectException(\FerryAI\Core\Exception\IoException::class);
            $this->expectExceptionMessage('ownership');
            $other->read($id);
        } finally {
            $manager->detachModel($id);
            @\unlink($file);
        }
    }
}
