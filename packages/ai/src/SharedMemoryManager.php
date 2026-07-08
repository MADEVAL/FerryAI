<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\Exception\InvalidStateException;
use FerryAI\Core\Exception\IoException;

final class SharedMemoryManager implements SharedMemory
{
    /**
     * Bytes reserved at the start of every segment for the ownership marker
     * (first 16 bytes of sha256(modelId)); the model payload follows it.
     */
    private const int HEADER_SIZE = 16;

    /** @var array<string, int> */
    private array $segments = [];

    /** @var array<string, \Shmop> */
    private array $handles = [];

    public function __destruct()
    {
        foreach (\array_keys($this->segments) as $modelId) {
            $this->detachModel($modelId);
        }
    }

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

        $marker = self::markerFor($modelId);
        $shmId = \shmop_open($key, 'c', 0600, self::HEADER_SIZE + $size);

        if ($shmId === false) {
            // A segment already exists at this key with a different size: a stale segment or a
            // collision with a different model. Refuse rather than silently corrupt/misread it.
            throw new IoException(\sprintf(
                'Shared memory key collision (size mismatch) for model: %s. Detach the stale segment first.',
                $modelId,
            ));
        }

        $existingMarker = \shmop_read($shmId, 0, self::HEADER_SIZE);

        if ($existingMarker !== \str_repeat("\0", self::HEADER_SIZE) && $existingMarker !== $marker) {
            throw new IoException(\sprintf('Shared memory ownership mismatch (key collision) for model: %s', $modelId));
        }

        $data = \file_get_contents($modelPath);

        if (\is_string($data)) {
            \shmop_write($shmId, $marker, 0);
            \shmop_write($shmId, $data, self::HEADER_SIZE);
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

        if (\shmop_read($shmId, 0, self::HEADER_SIZE) !== self::markerFor($modelId)) {
            throw new IoException(\sprintf('Shared memory ownership mismatch (key collision) for model: %s', $modelId));
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

        return \shmop_read($shmId, self::HEADER_SIZE, \shmop_size($shmId) - self::HEADER_SIZE);
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
     * Derives a positive 31-bit System V IPC key from the model id. The key space is inherently
     * 32-bit; sha256 gives a far more uniform distribution than crc32, and the in-segment ownership
     * marker (see {@see markerFor()}) guards against the residual collision risk.
     */
    private static function keyFor(string $modelId): int
    {
        return (int) \hexdec(\substr(\hash('sha256', $modelId), 0, 8)) & 0x7FFFFFFF;
    }

    /**
     * Ownership marker written at the start of a segment: the first {@see HEADER_SIZE} raw bytes of
     * sha256(modelId). A mismatch on allocate/attach means the key resolved to another model's segment.
     */
    private static function markerFor(string $modelId): string
    {
        return \substr(\hash('sha256', $modelId, true), 0, self::HEADER_SIZE);
    }
}
