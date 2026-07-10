<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\Exception\IoException;
use FerryAI\Core\PlatformDetector;
use FerryAI\ModelHub\Signature\Sha256Verifier;

final class NativeBinaryManager implements LibraryResolver
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $home = \getenv('HOME');
        $this->cacheDir = $cacheDir ?? (\is_string($home) ? $home : \sys_get_temp_dir()) . '/.ferry-ai/bin';
    }

    #[\Override]
    public function resolve(string $library): ?string
    {
        // Prefer the controlled cache over PATH: an attacker-writable early PATH entry
        // must not be able to shadow a verified, cached binary.
        $cachedPath = $this->findInCache($library);

        if ($cachedPath !== null) {
            return $cachedPath;
        }

        $systemPath = $this->findInSystem($library);

        if ($systemPath !== null) {
            return $systemPath;
        }

        return null;
    }

    public function download(string $library, string $version): string
    {
        $platform = PlatformDetector::platformKey();
        $ext = PlatformDetector::libExtension();
        $urlPattern = \getenv('FERRY_AI_NATIVE_BINARIES_URL');

        if (\is_string($urlPattern) && $urlPattern !== '') {
            $url = \sprintf($urlPattern, $version, $library, $platform, $ext);
        } else {
            $url = \sprintf(
                'https://github.com/MADEVAL/ferry-ai-native-binaries/releases/download/v%s/%s-%s.%s',
                $version,
                $library,
                $platform,
                $ext,
            );
        }

        if (!\is_dir($this->cacheDir)) {
            \mkdir($this->cacheDir, 0755, true);
        }

        $destPath = $this->cacheDir . '/' . \basename($url);
        $data = @\file_get_contents($url);

        if ($data === false) {
            throw new IoException(\sprintf('Failed to download native binary: %s', $library));
        }

        if (\file_put_contents($destPath, $data) === false) {
            throw new IoException(\sprintf('Failed to write native binary to cache: %s', $destPath));
        }

        // Fail closed: a downloaded binary is only trusted once its SHA-256 matches the
        // checksum published next to the artifact. A corrupted/tampered file is deleted.
        $this->verifyDownload($destPath, $url . '.sha256', $library);

        return $destPath;
    }

    private function verifyDownload(string $destPath, string $sha256Url, string $library): void
    {
        $sha256 = @\file_get_contents($sha256Url);

        if ($sha256 === false) {
            @\unlink($destPath);

            throw new IoException(\sprintf('Missing SHA-256 checksum for native binary: %s', $library));
        }

        $expected = (string) \strtok(\trim($sha256), " \t\r\n");

        if ($expected === '' || !Sha256Verifier::verify($destPath, $expected)) {
            @\unlink($destPath);

            throw new IoException(\sprintf('SHA-256 verification failed for native binary: %s', $library));
        }
    }

    public function verify(string $path, string $expectedSha256): bool
    {
        if (!\file_exists($path)) {
            return false;
        }

        return Sha256Verifier::verify($path, $expectedSha256);
    }

    public function cleanup(): void
    {
        if (!\is_dir($this->cacheDir)) {
            return;
        }

        $files = \glob($this->cacheDir . '/*');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (\is_file($file)) {
                \unlink($file);
            }
        }
    }

    private function findInSystem(string $library): ?string
    {
        $paths = \explode(PATH_SEPARATOR, \getenv('PATH') ?: '');

        foreach ($paths as $dir) {
            $candidate = $dir . '/' . $library . '.' . PlatformDetector::libExtension();

            if (\file_exists($candidate)) {
                return $candidate;
            }

            $candidate = $dir . '/' . $library;

            if (\file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function findInCache(string $library): ?string
    {
        $ext = PlatformDetector::libExtension();
        $candidate = $this->cacheDir . '/' . $library . '.' . $ext;

        if (\file_exists($candidate)) {
            return $candidate;
        }

        return null;
    }
}
