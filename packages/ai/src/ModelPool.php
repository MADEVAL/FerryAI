<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\Contracts\Model;

final class ModelPool
{
    /** @var array<string, Model> */
    private array $pool = [];

    /** @var array<string, int> */
    private array $memoryUsage = [];

    private int $maxMemoryBytes;

    public function __construct(?int $maxMemoryBytes = null, private readonly ?SharedMemory $sharedMemory = null)
    {
        $this->maxMemoryBytes = $maxMemoryBytes ?? 2_147_483_648;
    }

    public function __destruct()
    {
        foreach (\array_keys($this->pool) as $modelId) {
            $this->evict($modelId);
        }
    }

    /**
     * Preloads models into the pool using the supplied loader. Already-pooled ids are skipped.
     *
     * @param string[]                $modelIds
     * @param callable(string): Model $loader
     */
    public function warmup(array $modelIds, callable $loader): void
    {
        foreach ($modelIds as $modelId) {
            if (isset($this->pool[$modelId])) {
                continue;
            }

            $model = $loader($modelId);
            $this->put($modelId, $model, $model->metadata()->sizeBytes);
        }
    }

    public function acquire(string $modelId): ?Model
    {
        if (!isset($this->pool[$modelId])) {
            return null;
        }

        // Move to the end to mark it most-recently-used (true LRU eviction order).
        $model = $this->pool[$modelId];
        $memory = $this->memoryUsage[$modelId];
        unset($this->pool[$modelId], $this->memoryUsage[$modelId]);
        $this->pool[$modelId] = $model;
        $this->memoryUsage[$modelId] = $memory;

        return $model;
    }

    public function release(string $modelId): void
    {
        $this->evict($modelId);
    }

    public function put(string $modelId, Model $model, int $memoryBytes = 0): void
    {
        unset($this->pool[$modelId], $this->memoryUsage[$modelId]);

        $this->pool[$modelId] = $model;
        $this->memoryUsage[$modelId] = $memoryBytes;

        $this->enforceMemoryLimit($modelId);
    }

    public function evict(string $modelId): void
    {
        if (isset($this->pool[$modelId])) {
            $this->pool[$modelId]->unload();
            unset($this->pool[$modelId], $this->memoryUsage[$modelId]);
        }

        $this->sharedMemory?->detachModel($modelId);
    }

    /**
     * Opt-in: loads the model file at $modelPath into shared memory so that
     * sibling workers (e.g. PHP-FPM) can map the same read-only bytes.
     *
     * Returns false — never throws — when shared memory is not configured or the
     * extension is unavailable, so callers can treat sharing as best-effort.
     */
    public function shareModel(string $modelId, string $modelPath): bool
    {
        if ($this->sharedMemory === null || !$this->sharedMemory->isAvailable()) {
            return false;
        }

        try {
            $this->sharedMemory->allocateModel($modelId, $modelPath);
        } catch (\RuntimeException) {
            return false;
        }

        return true;
    }

    public function isModelShared(string $modelId): bool
    {
        return $this->sharedMemory?->isShared($modelId) ?? false;
    }

    public function size(): int
    {
        return \count($this->pool);
    }

    public function memoryUsage(): int
    {
        return \array_sum($this->memoryUsage);
    }

    /**
     * Evicts least-recently-used models until the pool fits within its memory budget.
     * The model identified by $keep is never evicted, even if it alone exceeds the limit.
     */
    private function enforceMemoryLimit(string $keep): void
    {
        while ($this->memoryUsage() > $this->maxMemoryBytes) {
            $oldest = \array_key_first($this->pool);

            if ($oldest === null || $oldest === $keep) {
                break;
            }

            $this->evict($oldest);
        }
    }
}
