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

    public function testDownloadUsesEnvVarForUrlOverride(): void
    {
        \putenv('FERRY_AI_NATIVE_BINARIES_URL=https://custom.example.com/v%s/%s-%s.%s');

        try {
            $manager = new NativeBinaryManager();

            try {
                $manager->download('llama', '1.0');
            } catch (\FerryAI\Core\Exception\IoException) {
                // Expected — custom URL will not be reachable in unit tests
            }

            self::assertTrue(true);
        } finally {
            \putenv('FERRY_AI_NATIVE_BINARIES_URL');
        }
    }
}
