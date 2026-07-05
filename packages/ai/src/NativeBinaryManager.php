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
        $systemPath = $this->findInSystem($library);

        if ($systemPath !== null) {
            return $systemPath;
        }

        $cachedPath = $this->findInCache($library);

        if ($cachedPath !== null) {
            return $cachedPath;
        }

        return null;
    }

    public function download(string $library, string $version): string
    {
        $platform = PlatformDetector::platformKey();
        $ext = PlatformDetector::libExtension();
        $url = \sprintf(
            'https://github.com/MADEVAL/ferry-ai-native-binaries/releases/download/v%s/%s-%s.%s',
            $version,
            $library,
            $platform,
            $ext,
        );

        if (!\is_dir($this->cacheDir)) {
            \mkdir($this->cacheDir, 0755, true);
        }

        $destPath = $this->cacheDir . '/' . \basename($url);
        $data = @\file_get_contents($url);

        if ($data === false) {
            throw new IoException(\sprintf('Failed to download native binary: %s', $library));
        }

        \file_put_contents($destPath, $data);

        return $destPath;
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
