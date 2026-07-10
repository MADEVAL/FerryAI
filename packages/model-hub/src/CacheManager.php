<?php

declare(strict_types=1);

namespace FerryAI\ModelHub;

final class CacheManager
{
    private string $cacheDir;
    private ?int $maxSizeBytes;

    public function __construct(string $cacheDir, ?int $maxSizeBytes = null)
    {
        $this->cacheDir = $cacheDir;
        $this->maxSizeBytes = $maxSizeBytes;

        if (!\is_dir($cacheDir)) {
            \mkdir($cacheDir, 0755, true);
        }
    }

    public function put(string $key, string $path): void
    {
        $targetPath = $this->getCachePath($key);
        \copy($path, $targetPath);
    }

    /**
     * Moves a caller-owned temporary file into the cache, returning its final path.
     * Unlike {@see put()} this does not leave the source behind (no temp-file leak).
     */
    public function store(string $key, string $path): string
    {
        $targetPath = $this->getCachePath($key);

        if (!@\rename($path, $targetPath)) {
            \copy($path, $targetPath);
            @\unlink($path);
        }

        return $targetPath;
    }

    public function get(string $key): ?string
    {
        $path = $this->getCachePath($key);

        if (!\file_exists($path)) {
            return null;
        }

        \touch($path);

        return $path;
    }

    public function has(string $key): bool
    {
        return \file_exists($this->getCachePath($key));
    }

    public function remove(string $key): void
    {
        $path = $this->getCachePath($key);

        if (\file_exists($path)) {
            \unlink($path);
        }
    }

    public function prune(?int $maxSizeBytes = null): int
    {
        $limit = $maxSizeBytes ?? $this->maxSizeBytes;

        if ($limit === null) {
            return 0;
        }

        $filePaths = \glob($this->cacheDir . '/*');

        if ($filePaths === false) {
            return 0;
        }

        $files = [];

        foreach ($filePaths as $file) {
            if (\is_file($file)) {
                $size = \filesize($file);
                $atime = \fileatime($file);
                $files[] = [
                    'path' => $file,
                    'atime' => $atime === false ? 0 : $atime,
                    'size' => $size === false ? 0 : $size,
                ];
            }
        }

        \usort($files, static fn(array $a, array $b): int => $a['atime'] <=> $b['atime']);

        $totalSize = \array_sum(\array_column($files, 'size'));
        $pruned = 0;

        while ($totalSize > $limit && $files !== []) {
            $oldest = \array_shift($files);
            \unlink($oldest['path']);
            $totalSize = (int) $totalSize - (int) $oldest['size'];
            $pruned++;
        }

        return $pruned;
    }

    public function cacheSize(): int
    {
        $size = 0;
        $filePaths = \glob($this->cacheDir . '/*');

        if ($filePaths === false) {
            return 0;
        }

        foreach ($filePaths as $file) {
            if (\is_file($file)) {
                $size += (int) \filesize($file);
            }
        }

        return $size;
    }

    /**
     * @return array<string, array{path: string, size: int, last_access: int}>
     */
    public function list(): array
    {
        $result = [];
        $filePaths = \glob($this->cacheDir . '/*');

        if ($filePaths === false) {
            return $result;
        }

        foreach ($filePaths as $file) {
            if (!\is_file($file)) {
                continue;
            }
            $key = \basename($file);
            $result[$key] = [
                'path' => $file,
                'size' => (int) \filesize($file),
                'last_access' => (int) \fileatime($file),
            ];
        }

        return $result;
    }

    public function clear(): void
    {
        $filePaths = \glob($this->cacheDir . '/*');

        if ($filePaths === false) {
            return;
        }

        foreach ($filePaths as $file) {
            if (\is_file($file)) {
                \unlink($file);
            }
        }
    }

    private function getCachePath(string $key): string
    {
        return $this->cacheDir . '/' . \preg_replace('/[^a-zA-Z0-9_.@%~-]/', '_', $key);
    }
}
