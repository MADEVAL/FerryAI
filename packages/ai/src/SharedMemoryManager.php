<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\Exception\InvalidStateException;
use FerryAI\Core\Exception\IoException;

final class SharedMemoryManager implements SharedMemory
{
    /** @var array<string, int> */
    private array $segments = [];

    /** @var array<string, \Shmop> */
    private array $handles = [];

    #[\Override]
    public function isAvailable(): bool
    {
        return \extension_loaded('shmop');
    }

    #[\Override]
    public function allocateModel(string $modelId, string $modelPath): int
    {
        if (!$this->isAvailable()) {
            throw new InvalidStateException('ext-shmop is not available for shared memory');
        }

        $key = self::keyFor($modelId);
        $size = \file_exists($modelPath) ? (int) \filesize($modelPath) : 0;

        if ($size === 0) {
            throw new IoException(\sprintf('Model file is empty or missing: %s', $modelPath));
        }

        $shmId = \shmop_open($key, 'c', 0644, $size);

        if ($shmId === false) {
            throw new IoException(\sprintf('Cannot allocate shared memory for model: %s', $modelId));
        }

        $data = \file_get_contents($modelPath);

        if (\is_string($data)) {
            \shmop_write($shmId, $data, 0);
        }

        $this->segments[$modelId] = $key;
        $this->handles[$modelId] = $shmId;

        return $key;
    }

    /**
     * Attaches to an existing segment for reading, keeping the handle so it can be read and freed.
     */
    public function attachModel(string $modelId): int
    {
        if (!$this->isAvailable()) {
            throw new InvalidStateException('ext-shmop is not available');
        }

        if (isset($this->handles[$modelId])) {
            return $this->segments[$modelId];
        }

        $key = $this->segments[$modelId] ?? self::keyFor($modelId);
        $shmId = \shmop_open($key, 'a', 0, 0);

        if ($shmId === false) {
            throw new IoException(\sprintf('Cannot attach shared memory for model: %s', $modelId));
        }

        $this->segments[$modelId] = $key;
        $this->handles[$modelId] = $shmId;

        return $key;
    }

    /**
     * Reads the raw bytes of a shared model segment.
     */
    public function read(string $modelId): string
    {
        if (!isset($this->handles[$modelId])) {
            $this->attachModel($modelId);
        }

        $shmId = $this->handles[$modelId];

        return \shmop_read($shmId, 0, \shmop_size($shmId));
    }

    #[\Override]
    public function detachModel(string $modelId): void
    {
        if (isset($this->handles[$modelId])) {
            // Marks the segment for removal once no process is attached, preventing a leak.
            \shmop_delete($this->handles[$modelId]);
        }

        unset($this->handles[$modelId], $this->segments[$modelId]);
    }

    #[\Override]
    public function isShared(string $modelId): bool
    {
        return isset($this->segments[$modelId]);
    }

    /**
     * Derives a positive System V IPC key from the model id (crc32 can be negative on 32-bit).
     */
    private static function keyFor(string $modelId): int
    {
        return \crc32($modelId) & 0x7FFFFFFF;
    }
}
