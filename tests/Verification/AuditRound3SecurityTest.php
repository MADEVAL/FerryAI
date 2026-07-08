<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Core\Exception\IoException;
use FerryAI\Core\Exception\ModelLoadException;
use FerryAI\Core\PlatformDetector;
use FerryAI\CpuBackend\CpuNativeBackend;
use FerryAI\CpuBackend\RubixMLAdapter;
use FerryAI\NativeBinaryManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guards for the round-3 security fixes. Each test drives real code and
 * asserts the CORRECTED behaviour, so a regression is caught by execution, not source reading.
 *
 * Findings:
 *  - Object injection via unserialize() when loading a CPU/RubixML model file.
 *  - Native binary downloaded and cached without a SHA-256 integrity check.
 *  - resolve() trusted PATH before the controlled cache.
 */
#[CoversNothing]
final class AuditRound3SecurityTest extends TestCase
{
    public function testCpuNativeBackendDoesNotInstantiateObjectsFromModelFile(): void
    {
        UnserializeCanaryGadget::$fired = false;

        $file = (string) \tempnam(\sys_get_temp_dir(), 'ferry_rbm_');
        \file_put_contents($file, \serialize(new UnserializeCanaryGadget()));

        $backend = new CpuNativeBackend();

        try {
            $backend->load($file);
            self::fail('An object payload must be rejected by the is_array() guard.');
        } catch (ModelLoadException) {
            // Expected: object payloads are not a valid CPU model.
        } finally {
            @\unlink($file);
        }

        self::assertFalse(
            UnserializeCanaryGadget::$fired,
            'unserialize() must not instantiate objects from an untrusted model file.',
        );
    }

    public function testRubixMLAdapterFallbackDoesNotInstantiateObjects(): void
    {
        UnserializeCanaryGadget::$fired = false;

        $adapter = new RubixMLAdapter();
        // Force the loader past the availability guard so the raw-unserialize fallback runs
        // even though rubix/ml is not installed in the test toolchain.
        (new \ReflectionProperty($adapter, 'available'))->setValue($adapter, true);

        $file = (string) \tempnam(\sys_get_temp_dir(), 'ferry_rbx_');
        \file_put_contents($file, \serialize(new UnserializeCanaryGadget()));

        try {
            $adapter->loadModel($file);
        } catch (\Throwable) {
            // Loading may legitimately fail; the only guarantee under test is that no gadget ran.
        } finally {
            @\unlink($file);
        }

        self::assertFalse(
            UnserializeCanaryGadget::$fired,
            'The RubixML raw-unserialize fallback must not instantiate arbitrary objects.',
        );
    }

    public function testResolvePrefersControlledCacheOverSystemPath(): void
    {
        $ext = PlatformDetector::libExtension();
        $lib = 'ferrycanary' . \substr(\md5(\uniqid('', true)), 0, 8);

        $cacheDir = \sys_get_temp_dir() . '/ferry-cache-' . \uniqid('', true);
        \mkdir($cacheDir, 0o777, true);
        $cacheLib = $cacheDir . '/' . $lib . '.' . $ext;
        \file_put_contents($cacheLib, 'CACHE');

        $pathDir = \sys_get_temp_dir() . '/ferry-path-' . \uniqid('', true);
        \mkdir($pathDir, 0o777, true);
        $pathLib = $pathDir . '/' . $lib . '.' . $ext;
        \file_put_contents($pathLib, 'PATH');

        $originalPath = \getenv('PATH');
        \putenv('PATH=' . $pathDir . \PATH_SEPARATOR . ($originalPath === false ? '' : $originalPath));

        try {
            $manager = new NativeBinaryManager($cacheDir);

            self::assertSame(
                $cacheLib,
                $manager->resolve($lib),
                'resolve() must prefer the controlled cache over an attacker-writable PATH entry.',
            );
        } finally {
            \putenv('PATH=' . ($originalPath === false ? '' : $originalPath));
            @\unlink($cacheLib);
            @\unlink($pathLib);
            @\rmdir($cacheDir);
            @\rmdir($pathDir);
        }
    }

    public function testDownloadRejectsBinaryWithMismatchedSha256(): void
    {
        $platform = PlatformDetector::platformKey();
        $ext = PlatformDetector::libExtension();
        $lib = 'ferrydl';
        $version = '9.9.9';

        $srcDir = \sys_get_temp_dir() . '/ferry-src-' . \uniqid('', true);
        \mkdir($srcDir, 0o777, true);
        $artifact = $srcDir . '/' . $lib . '-' . $platform . '.' . $ext;
        \file_put_contents($artifact, 'BINARY-CONTENT');
        \file_put_contents($artifact . '.sha256', \str_repeat('0', 64));

        $cacheDir = \sys_get_temp_dir() . '/ferry-dlcache-' . \uniqid('', true);
        \putenv('FERRY_AI_NATIVE_BINARIES_URL=' . $srcDir . '/%2$s-%3$s.%4$s');

        try {
            $manager = new NativeBinaryManager($cacheDir);

            $threw = false;

            try {
                $manager->download($lib, $version);
            } catch (IoException) {
                $threw = true;
            }

            self::assertTrue($threw, 'download() must reject a binary whose SHA-256 does not match.');
            self::assertFileDoesNotExist(
                $cacheDir . '/' . \basename($artifact),
                'A corrupted download must not remain in the cache.',
            );
        } finally {
            \putenv('FERRY_AI_NATIVE_BINARIES_URL');
            @\unlink($artifact);
            @\unlink($artifact . '.sha256');
            @\rmdir($srcDir);
            $this->removeDir($cacheDir);
        }
    }

    public function testDownloadAcceptsBinaryWithMatchingSha256(): void
    {
        $platform = PlatformDetector::platformKey();
        $ext = PlatformDetector::libExtension();
        $lib = 'ferrydl';
        $version = '9.9.9';
        $content = 'BINARY-CONTENT';

        $srcDir = \sys_get_temp_dir() . '/ferry-src-' . \uniqid('', true);
        \mkdir($srcDir, 0o777, true);
        $artifact = $srcDir . '/' . $lib . '-' . $platform . '.' . $ext;
        \file_put_contents($artifact, $content);
        \file_put_contents($artifact . '.sha256', \hash('sha256', $content));

        $cacheDir = \sys_get_temp_dir() . '/ferry-dlcache-' . \uniqid('', true);
        \putenv('FERRY_AI_NATIVE_BINARIES_URL=' . $srcDir . '/%2$s-%3$s.%4$s');

        try {
            $manager = new NativeBinaryManager($cacheDir);
            $path = $manager->download($lib, $version);

            self::assertFileExists($path);
            self::assertSame($content, \file_get_contents($path));
        } finally {
            \putenv('FERRY_AI_NATIVE_BINARIES_URL');
            @\unlink($artifact);
            @\unlink($artifact . '.sha256');
            @\rmdir($srcDir);
            $this->removeDir($cacheDir);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        foreach (\glob($dir . '/*') ?: [] as $file) {
            @\unlink($file);
        }

        @\rmdir($dir);
    }
}

final class UnserializeCanaryGadget
{
    public static bool $fired = false;

    public function __wakeup(): void
    {
        self::$fired = true;
    }
}
