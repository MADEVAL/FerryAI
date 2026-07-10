<?php

declare(strict_types=1);

namespace FerryAI\Core\Contracts;

use FerryAI\Core\ValueObjects\ModelMetadata;

interface ModelHub
{
    /**
     * Downloads a model from the HuggingFace Hub.
     *
     * @param string      $modelId e.g. "sentence-transformers/all-MiniLM-L6-v2"
     * @param string|null $version version/tag; null means latest
     *
     *
     * @throws \FerryAI\Core\Exception\ModelNotFoundException
     * @return string                                         local path to the downloaded model file
     */
    public function download(string $modelId, ?string $version = null): string;

    /**
     * Returns the cached model path, or null when not cached.
     */
    public function cached(string $modelId, ?string $version = null): ?string;

    /**
     * Verifies a model file (SHA-256 + Ed25519 signature).
     *
     * @throws \FerryAI\Core\Exception\ModelLoadException when verification fails
     */
    public function verify(string $path, ?string $sha256 = null, ?string $signature = null): bool;

    /**
     * Reads model metadata without a full load.
     */
    public function introspect(string $path): ModelMetadata;

    /**
     * Downloads a model while yielding progress.
     *
     * @return \Generator<int, array{progress: float, downloaded: int, total: int}>
     */
    public function downloadWithProgress(string $modelId, ?string $version = null): \Generator;

    /**
     * Removes a model from the cache; null version removes all versions.
     */
    public function remove(string $modelId, ?string $version = null): void;

    /**
     * Prunes the cache using an LRU policy.
     *
     * @param int|null $maxSizeBytes maximum cache size; null uses configuration
     *
     * @return int number of removed models
     */
    public function prune(?int $maxSizeBytes = null): int;

    /**
     * Returns the total cache size in bytes.
     */
    public function cacheSize(): int;

    /**
     * Pre-downloads a list of models (cache warmup).
     *
     * @param string[] $modelIds
     */
    public function warmup(array $modelIds): void;
}
