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

    public function __construct(?int $maxMemoryBytes = null)
    {
        $this->maxMemoryBytes = $maxMemoryBytes ?? 2_147_483_648;
    }

    public function warmup(array $modelIds): void {}

    public function acquire(string $modelId): ?Model
    {
        return $this->pool[$modelId] ?? null;
    }

    public function release(string $modelId): void {}

    public function put(string $modelId, Model $model, int $memoryBytes = 0): void
    {
        $this->pool[$modelId] = $model;
        $this->memoryUsage[$modelId] = $memoryBytes;
    }

    public function evict(string $modelId): void
    {
        if (isset($this->pool[$modelId])) {
            $this->pool[$modelId]->unload();
            unset($this->pool[$modelId], $this->memoryUsage[$modelId]);
        }
    }

    public function size(): int
    {
        return \count($this->pool);
    }

    public function memoryUsage(): int
    {
        return \array_sum($this->memoryUsage);
    }
}
