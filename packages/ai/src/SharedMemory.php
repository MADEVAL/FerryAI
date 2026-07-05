<?php

declare(strict_types=1);

namespace FerryAI;

/**
 * Abstraction over cross-process shared memory for read-only model weights.
 *
 * Implemented by {@see SharedMemoryManager} (System V shmop). Kept as an interface
 * so the {@see ModelPool} can depend on the capability without binding to the
 * concrete extension, and so tests can substitute a fake without touching real
 * OS shared-memory segments.
 *
 * Limitation: only raw model *files* (by path) can be shared. Already-instantiated
 * model objects wrap native handles that cannot be serialized across workers.
 */
interface SharedMemory
{
    public function isAvailable(): bool;

    /**
     * Loads the model file at $modelPath into a shared segment keyed by $modelId.
     *
     * @return int the shared-memory key
     */
    public function allocateModel(string $modelId, string $modelPath): int;

    public function detachModel(string $modelId): void;

    public function isShared(string $modelId): bool;
}
