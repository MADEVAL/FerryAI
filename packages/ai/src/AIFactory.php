<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\AIConfig;
use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Embedder;
use FerryAI\Core\Contracts\ModelHub;
use FerryAI\Core\Contracts\Pipeline;
use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Core\Enums\BackendType;
use FerryAI\CpuBackend\CpuNativeBackend;
use FerryAI\Embedding\Embedder as EmbedderImpl;
use FerryAI\LlamaBackend\LlamaBackend;
use FerryAI\ModelHub\Hub;
use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\Pipeline\Pipeline as PipelineImpl;
use FerryAI\Tokenizer\TokenizerFactory;
use FerryAI\Vector\CollectionManager;
use FerryAI\Vector\PostgresCollection;
use FerryAI\Vector\PostgresStore;
use FerryAI\Vector\SQLiteStore;

final class AIFactory
{
    private readonly AIConfig $config;

    public function __construct(?AIConfig $config = null, private readonly ?LibraryResolver $libraryResolver = null)
    {
        $this->config = $config ?? AIConfig::fromArray([]);
    }

    public function config(): AIConfig
    {
        return $this->config;
    }

    public function createBackend(BackendType $type): Backend
    {
        return match ($type) {
            BackendType::Onnx => new OnnxBackend(),
            BackendType::Llama => $this->createLlamaBackend(),
            BackendType::CpuNative => new CpuNativeBackend(),
        };
    }

    private function createLlamaBackend(): LlamaBackend
    {
        $this->ensureLlamaLibraryResolved();

        return new LlamaBackend();
    }

    /**
     * Best-effort: locate the llama.cpp shared library and expose it via
     * FERRY_AI_LLAMA_LIB when the environment does not already point at one.
     * Never downloads and never throws — resolution failure leaves the env untouched.
     */
    private function ensureLlamaLibraryResolved(): void
    {
        $current = \getenv('FERRY_AI_LLAMA_LIB');

        if (\is_string($current) && $current !== '') {
            return;
        }

        $resolver = $this->libraryResolver ?? new NativeBinaryManager();
        $path = $resolver->resolve('llama');

        if ($path !== null) {
            \putenv('FERRY_AI_LLAMA_LIB=' . $path);
        }
    }

    public function createTokenizer(string $modelName): Tokenizer
    {
        return (new TokenizerFactory())->create($modelName);
    }

    public function createVectorStore(string $collection, int $dimension): VectorStore
    {
        if ($this->vectorDriver() === 'pgsql') {
            return $this->createPostgresVectorStore($collection, $dimension);
        }

        $dbPath = $this->config->get('vector.db_path', ':memory:');
        $store = new SQLiteStore(\is_string($dbPath) ? $dbPath : ':memory:');
        $manager = new CollectionManager($store);

        return $manager->create($collection, $dimension);
    }

    private function vectorDriver(): string
    {
        $driver = $this->config->get('vector.driver');

        if (!\is_string($driver)) {
            $env = \getenv('FERRY_AI_VECTOR_DRIVER');
            $driver = $env !== false ? $env : 'sqlite';
        }

        return \strtolower($driver);
    }

    private function createPostgresVectorStore(string $collection, int $dimension): VectorStore
    {
        $dsn = (string) $this->config->get('vector.dsn', \getenv('FERRY_AI_PG_DSN') ?: 'pgsql:host=127.0.0.1;port=5432');
        $user = (string) $this->config->get('vector.user', \getenv('FERRY_AI_PG_USER') ?: 'postgres');
        $password = (string) $this->config->get('vector.password', \getenv('FERRY_AI_PG_PASSWORD') ?: 'postgres');
        $metric = (string) $this->config->get('vector.metric', 'cosine');

        $store = new PostgresStore($dsn, $user, $password);

        if ($store->collectionExists($collection)) {
            $dimension = $store->getDimension($collection) ?? $dimension;
        } elseif ($dimension > 0) {
            $store->createCollection($collection, $dimension, $metric);
        }

        return new PostgresCollection($collection, $dimension, $store, $metric);
    }

    public function createModelHub(): ModelHub
    {
        $cacheDir = $this->config->modelCache();

        return new Hub($cacheDir);
    }

    public function createPipeline(): Pipeline
    {
        return new PipelineImpl();
    }

    public function createEmbedder(string $modelName): Embedder
    {
        $backend = $this->createBackend($this->config->backend());
        [$modelPath, $tokenizerPath] = self::resolveEmbeddingPaths(
            $modelName,
            $this->config->get('backends.embedding.tokenizer_path'),
        );
        $tokenizer = (new TokenizerFactory())->createFromFile($tokenizerPath);
        $pooling = (string) $this->config->get('embedding.pooling', 'mean');
        $normalize = (bool) $this->config->get('embedding.normalize', true);

        return new EmbedderImpl($modelPath, $backend, $tokenizer, $pooling, $normalize);
    }

    /**
     * Resolves the ONNX model file and the tokenizer.json for an embedding model.
     * Accepts a model directory, a model file, or a bare name (left to the backend/tokenizer
     * to raise an actionable error). An explicit tokenizer path overrides the default.
     *
     * @return array{0: string, 1: string}
     */
    private static function resolveEmbeddingPaths(string $modelName, mixed $tokenizerPath): array
    {
        $explicit = \is_string($tokenizerPath) && $tokenizerPath !== '' ? $tokenizerPath : null;

        if (\is_dir($modelName)) {
            return [$modelName . '/model.onnx', $explicit ?? $modelName . '/tokenizer.json'];
        }

        if (\is_file($modelName)) {
            return [$modelName, $explicit ?? \dirname($modelName) . '/tokenizer.json'];
        }

        return [$modelName, $explicit ?? $modelName];
    }
}
