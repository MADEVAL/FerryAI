<?php

declare(strict_types=1);

namespace FerryAI\Vector;

final class SqliteVecExtension
{
    private bool $loaded = false;

    public function isAvailable(): bool
    {
        return \extension_loaded('FFI') && \getenv('FERRY_AI_VEC_EXTENSION_LIB') !== false;
    }

    public function load(SQLiteStore $store): void
    {
        $this->loaded = $this->isAvailable();
    }

    public function createIndex(string $collection, string $indexType = 'hnsw'): void {}

    /**
     * @return array<int, array{id: string, distance: float}>
     */
    public function search(string $collection, string $vectorBlob, int $k): array
    {
        return [];
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }
}
