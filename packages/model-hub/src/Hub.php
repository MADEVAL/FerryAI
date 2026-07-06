<?php

declare(strict_types=1);

namespace FerryAI\ModelHub;

use FerryAI\Core\Contracts\ModelHub as ModelHubContract;
use FerryAI\Core\ValueObjects\ModelMetadata;

final class Hub implements ModelHubContract
{
    private HuggingFaceClient $hfClient;
    private CacheManager $cache;
    /** @phpstan-ignore property.onlyWritten */
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

        $this->cache->put($cacheKey, $destPath);

        return $destPath;
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
        yield ['progress' => 0, 'downloaded' => 0, 'total' => 0];
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
            if ($this->cached($modelId) === null) {
                $this->download($modelId);
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
        $this->cache->put($name, $path);
    }

    /**
     * @return array<string, mixed>
     */
    public function checkUpdates(): array
    {
        return [];
    }

    private function cacheKey(string $modelId, ?string $version): string
    {
        return \str_replace('/', '_', $modelId) . ($version !== null ? '-' . $version : '');
    }
}
