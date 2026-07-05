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
use FerryAI\Vector\SQLiteStore;

final class AIFactory
{
    private readonly AIConfig $config;

    public function __construct(?AIConfig $config = null)
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
            BackendType::Llama => new LlamaBackend(),
            BackendType::CpuNative => new CpuNativeBackend(),
        };
    }

    public function createTokenizer(string $modelName): Tokenizer
    {
        return (new TokenizerFactory())->create($modelName);
    }

    public function createVectorStore(string $collection, int $dimension): VectorStore
    {
        $dbPath = $this->config->get('vector.db_path', ':memory:');
        $store = new SQLiteStore($dbPath);
        $manager = new CollectionManager($store);

        return $manager->create($collection, $dimension);
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
        $tokenizer = $this->createTokenizer($modelName);

        return new EmbedderImpl($modelName, $backend, $tokenizer);
    }
}
