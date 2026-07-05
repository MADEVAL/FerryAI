<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\Exception\InvalidStateException;
use FerryAI\Core\Exception\IoException;

final class SharedMemoryManager implements SharedMemory
{
    /** @var array<string, int> */
    private array $segments = [];

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

        $key = \crc32($modelId);
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

        return $key;
    }

    public function attachModel(string $modelId): int
    {
        if (!$this->isAvailable()) {
            throw new InvalidStateException('ext-shmop is not available');
        }

        $key = $this->segments[$modelId] ?? \crc32($modelId);
        \shmop_open($key, 'a', 0, 0);

        return $key;
    }

    #[\Override]
    public function detachModel(string $modelId): void
    {
        unset($this->segments[$modelId]);
    }

    #[\Override]
    public function isShared(string $modelId): bool
    {
        return isset($this->segments[$modelId]);
    }
}
