<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\NativeBinaryManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeBinaryManager::class)]
final class NativeBinaryManagerTest extends TestCase
{
    public function testResolveReturnsNullForUnknownLibrary(): void
    {
        $manager = new NativeBinaryManager();

        $path = $manager->resolve('nonexistent_library_xyz');

        self::assertNull($path);
    }

    public function testVerifyReturnsFalseForMissingFile(): void
    {
        $manager = new NativeBinaryManager();

        self::assertFalse($manager->verify('/nonexistent/file.dll', 'abc123'));
    }

    public function testCleanupDoesNotError(): void
    {
        $manager = new NativeBinaryManager(\sys_get_temp_dir() . '/ferry-bin-empty');

        $manager->cleanup();

        self::assertTrue(true);
    }

    public function testConstructorDefaultCacheDir(): void
    {
        $manager = new NativeBinaryManager();

        self::assertInstanceOf(NativeBinaryManager::class, $manager);
    }

    public function testCustomCacheDir(): void
    {
        $dir = \sys_get_temp_dir() . '/ferry-bin-custom-' . \uniqid();
        $manager = new NativeBinaryManager($dir);

        self::assertInstanceOf(NativeBinaryManager::class, $manager);
    }
}
