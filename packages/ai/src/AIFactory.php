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
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\OnnxBackend\OnnxBackend;

/**
 * Creates platform components from configuration.
 *
 * In Phase 1 only the ONNX backend is available; other components are gated to later phases.
 */
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

    /**
     * @throws BackendNotAvailableException when the backend is not available in this phase
     */
    public function createBackend(BackendType $type): Backend
    {
        return match ($type) {
            BackendType::Onnx => new OnnxBackend(),
            BackendType::Llama => throw new BackendNotAvailableException(
                $type->value,
                'the llama-backend package is introduced in Phase 2',
            ),
            BackendType::CpuNative => throw new BackendNotAvailableException(
                $type->value,
                'the cpu-backend package is introduced in Phase 3',
            ),
        };
    }

    public function createTokenizer(string $modelName): Tokenizer
    {
        throw new \RuntimeException('Tokenizers are introduced in Phase 2 (tokenizer package).');
    }

    public function createVectorStore(string $collection, int $dimension): VectorStore
    {
        throw new \RuntimeException('Vector stores are introduced in Phase 3 (vector package).');
    }

    public function createModelHub(): ModelHub
    {
        throw new \RuntimeException('The model hub is introduced in Phase 3 (model-hub package).');
    }

    public function createPipeline(): Pipeline
    {
        throw new \RuntimeException('Pipelines are introduced in Phase 3 (pipeline package).');
    }

    public function createEmbedder(string $modelName): Embedder
    {
        throw new \RuntimeException('Embedders are introduced in Phase 3 (embedding package).');
    }
}
