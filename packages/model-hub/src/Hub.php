<?php

declare(strict_types=1);

namespace FerryAI\ModelHub;

use FerryAI\Core\Contracts\ModelHub as ModelHubContract;
use FerryAI\Core\ValueObjects\ModelMetadata;

final class Hub implements ModelHubContract
{
    private HuggingFaceClient $hfClient;
    private CacheManager $cache;
    private Downloader $downloader;

    public function __construct(
        string $cacheDir,
        ?string $hfToken = null,
        private readonly ?string $publicKey = null,
    ) {
        $this->hfClient = new HuggingFaceClient($hfToken);
        $this->cache = new CacheManager($cacheDir);
        $this->downloader = new Downloader();
    }

    #[\Override]
    public function download(string $modelId, ?string $version = null): string
    {
        $cached = $this->cached($modelId, $version);

        if ($cached !== null) {
            return $cached;
        }

        $files = $this->hfClient->listFiles($modelId);

        if ($files === []) {
            throw new \FerryAI\Core\Exception\ModelNotFoundException($modelId);
        }

        $cacheKey = $this->cacheKey($modelId, $version);
        $destPath = \sys_get_temp_dir() . '/' . $cacheKey . '.model';

        foreach ($files as $file) {
            if (\str_ends_with($file, '.onnx') || \str_ends_with($file, '.gguf')) {
                $this->hfClient->downloadFile($modelId, $file, $destPath);
                break;
            }
        }

        if (!\file_exists($destPath)) {
            if (isset($files[0])) {
                $this->hfClient->downloadFile($modelId, $files[0], $destPath);
            } else {
                throw new \FerryAI\Core\Exception\ModelNotFoundException($modelId);
            }
        }

        $this->cache->store($cacheKey, $destPath);

        return $this->cache->get($cacheKey) ?? $destPath;
    }

    #[\Override]
    public function cached(string $modelId, ?string $version = null): ?string
    {
        $key = $this->cacheKey($modelId, $version);

        return $this->cache->get($key);
    }

    #[\Override]
    public function verify(string $path, ?string $sha256 = null, ?string $signature = null): bool
    {
        return ModelVerifier::verify($path, $sha256, $signature, $this->publicKey);
    }

    #[\Override]
    public function introspect(string $path): ModelMetadata
    {
        return ModelIntrospector::introspect($path);
    }

    #[\Override]
    public function downloadWithProgress(string $modelId, ?string $version = null): \Generator
    {
        $cached = $this->cached($modelId, $version);

        if ($cached !== null) {
            yield ['progress' => 1.0, 'downloaded' => 0, 'total' => 0];

            return $cached;
        }

        yield ['progress' => 0.0, 'downloaded' => 0, 'total' => 0];

        $files = $this->hfClient->listFiles($modelId);

        if ($files === []) {
            throw new \FerryAI\Core\Exception\ModelNotFoundException($modelId);
        }

        $modelFile = null;

        foreach ($files as $file) {
            if (\str_ends_with($file, '.onnx') || \str_ends_with($file, '.gguf')) {
                $modelFile = $file;

                break;
            }
        }

        $modelFile ??= $files[0];
        $cacheKey = $this->cacheKey($modelId, $version);
        $destPath = \sys_get_temp_dir() . '/' . $cacheKey . '.model';
        $url = \sprintf('https://huggingface.co/%s/resolve/main/%s', $modelId, $modelFile);

        yield from $this->downloader->downloadWithProgress($url, $destPath);

        $this->cache->store($cacheKey, $destPath);

        return $this->cache->get($cacheKey) ?? $destPath;
    }

    #[\Override]
    public function remove(string $modelId, ?string $version = null): void
    {
        $key = $this->cacheKey($modelId, $version);
        $this->cache->remove($key);
    }

    #[\Override]
    public function prune(?int $maxSizeBytes = null): int
    {
        return $this->cache->prune($maxSizeBytes);
    }

    #[\Override]
    public function cacheSize(): int
    {
        return $this->cache->cacheSize();
    }

    #[\Override]
    public function warmup(array $modelIds): void
    {
        foreach ($modelIds as $modelId) {
            if ($this->cached($modelId) !== null) {
                continue;
            }

            try {
                $this->download($modelId);
            } catch (\FerryAI\Core\Exception\FerryAIException) {
                // warmup is best-effort: skip models that cannot be downloaded now
            }
        }
    }

    /**
     * @return string[]
     */
    public function list(): array
    {
        return \array_keys($this->cache->list());
    }

    public function register(string $name, string $path, ?string $sha256 = null): void
    {
        if ($sha256 !== null && !ModelVerifier::verify($path, $sha256, null, $this->publicKey)) {
            throw new \FerryAI\Core\Exception\ModelLoadException($path, 'SHA-256 checksum mismatch');
        }

        $this->cache->put($name, $path);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function checkUpdates(): array
    {
        $updates = [];

        foreach ($this->list() as $cacheKey) {
            $modelId = self::decodeModelId($cacheKey);
            $updates[$cacheKey] = $this->hfClient->getModelInfo($modelId);
        }

        return $updates;
    }

    private function cacheKey(string $modelId, ?string $version): string
    {
        return \rawurlencode($modelId) . ($version !== null ? '@' . \rawurlencode($version) : '');
    }

    /**
     * Inverse of {@see cacheKey()}: recovers the original model id from a cache key,
     * dropping any '@version' suffix. rawurlencode never emits '@', so the split is lossless.
     */
    private static function decodeModelId(string $cacheKey): string
    {
        $encoded = \explode('@', $cacheKey, 2)[0];

        return \rawurldecode($encoded);
    }
}
