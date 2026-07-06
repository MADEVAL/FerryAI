<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\AIConfig;
use FerryAI\Core\Contracts\Embedder;
use FerryAI\Core\Contracts\ModelHub;
use FerryAI\Core\Contracts\Pipeline;
use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Core\Enums\BackendType;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Core\Exception\ConfigurationException;
use FerryAI\Core\Exception\InvalidStateException;
use FerryAI\Core\ValueObjects\ClassificationResult;
use FerryAI\Core\ValueObjects\EmbeddingResult;
use FerryAI\Core\ValueObjects\GenerationResult;
use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use FerryAI\LlamaBackend\LlamaModel;
use FerryAI\LlamaBackend\Sampling\GrammarSampler;
use FerryAI\LlamaBackend\Sampling\Sampler;
use FerryAI\LlamaBackend\Sampling\SamplerFactory;
use Psr\Http\Message\ResponseInterface;

/**
 * Unified entry point to FerryAI.
 *
 * Phase 1 provides configuration, backend registration/selection and task routing on top of the
 * ONNX backend. High-level NLP helpers (chat/embed/classify/...) are gated to later phases with
 * actionable messages, since they depend on the tokenizer/embedding/llama packages.
 */
final class AI
{
    private static ?AIConfig $config = null;

    private static ?AIFactory $factory = null;

    private static ?BackendRegistry $registry = null;

    private static ?BackendType $activeBackend = null;

    private static ?Device $activeDevice = null;

    private static ?Observability $observability = null;

    private static ?ModelPool $modelPool = null;

    /**
     * @param array<string, mixed> $config
     */
    public static function config(array $config): void
    {
        self::$config = AIConfig::fromArray($config);
        self::$factory = new AIFactory(self::$config);
        self::$registry = new BackendRegistry();
        self::$registry->register(BackendType::Onnx, self::$factory->createBackend(BackendType::Onnx));
        self::$registry->register(BackendType::Llama, self::$factory->createBackend(BackendType::Llama));
        self::$registry->register(BackendType::CpuNative, self::$factory->createBackend(BackendType::CpuNative));
        self::$activeBackend = self::$config->backend();
        self::$activeDevice = self::$config->device();
        self::$observability = Observability::fromConfig(self::$config);
        $sharedMemory = (bool) self::$config->get('model_pool.shared_memory', false) ? new SharedMemoryManager() : null;
        self::$modelPool = new ModelPool(self::poolMemoryLimit(self::$config), $sharedMemory);
    }

    /**
     * @param string[] $modelIds
     */
    public static function warmup(array $modelIds): void
    {
        self::ensureConfigured();

        $backend = self::registry()->get(self::activeBackend());
        self::modelPool()->warmup($modelIds, static fn(string $id): \FerryAI\Core\Contracts\Model => $backend->load($id));
    }

    public static function reset(): void
    {
        self::$config = null;
        self::$factory = null;
        self::$registry = null;
        self::$activeBackend = null;
        self::$activeDevice = null;
        self::$observability = null;
        self::$modelPool = null;
    }

    public static function resetBackend(string $name): void
    {
        self::ensureConfigured();
        self::resolveBackendType($name);
    }

    public static function backend(string $name): void
    {
        $type = self::resolveBackendType($name);

        if (!self::registry()->has($type)) {
            throw new BackendNotAvailableException($type->value, 'backend is not registered in Phase 1');
        }

        self::$activeBackend = $type;
    }

    public static function device(string $device): void
    {
        self::ensureConfigured();
        self::$activeDevice = Device::tryFrom($device)
            ?? throw new ConfigurationException('device', \sprintf("unknown device '%s'", $device));
    }

    /**
     * Returns the currently active backend type.
     */
    public static function activeBackend(): BackendType
    {
        if (self::$activeBackend === null) {
            throw new InvalidStateException('AI::config() must be called before using the facade.');
        }

        return self::$activeBackend;
    }

    /**
     * Returns the currently active device.
     */
    public static function activeDevice(): Device
    {
        if (self::$activeDevice === null) {
            throw new InvalidStateException('AI::config() must be called before using the facade.');
        }

        return self::$activeDevice;
    }

    /**
     * @param array<int, mixed>         $messages
     * @param array<string, mixed>|null $options
     */
    public static function chat(array $messages, ?array $options = null): GenerationResult
    {
        self::ensureConfigured();

        return self::observability()->measure('chat', static function () use ($messages, $options): GenerationResult {
            $model = self::chatModel();

            return $model->runComplete($messages, self::samplingParams($options), self::samplerFor($options));
        });
    }

    /**
     * @param array<int, mixed>         $messages
     * @param array<string, mixed>|null $options
     *
     * @return \Generator<int, string>
     */
    public static function stream(array $messages, ?array $options = null): \Generator
    {
        return self::chatModel()->runStream($messages, self::samplingParams($options), self::samplerFor($options));
    }

    /**
     * @param string|string[] $input
     *
     * @return EmbeddingResult|EmbeddingResult[]
     */
    public static function embed(string|array $input): EmbeddingResult|array
    {
        self::ensureConfigured();

        return self::observability()->measure('embed', static function () use ($input): EmbeddingResult|array {
            $embedder = self::embedder();

            if (\is_string($input)) {
                $vector = $embedder->embed($input);

                return new EmbeddingResult($vector, $embedder->dimension(), $embedder->modelName());
            }

            $vectors = $embedder->embedBatch($input);
            $results = [];

            foreach ($vectors as $vector) {
                $results[] = new EmbeddingResult($vector, $embedder->dimension(), $embedder->modelName());
            }

            return $results;
        });
    }

    public static function similarity(string $a, string $b): float
    {
        self::ensureConfigured();

        return self::observability()->measure('similarity', static function () use ($a, $b): float {
            $embedder = self::embedder();

            return $embedder->cosineSimilarity(
                $embedder->embed($a),
                $embedder->embed($b),
            );
        });
    }

    public static function classify(mixed $input): ClassificationResult
    {
        self::ensureConfigured();

        return self::observability()->measure('classify', static function () use ($input): ClassificationResult {
            $backend = self::registry()->get(self::activeBackend());
            $config = self::configuration();
            $modelPath = $config->get('backends.classify.model_path');

            if (!\is_string($modelPath) || $modelPath === '') {
                throw new ConfigurationException('backends.classify.model_path', 'a classification model path must be configured');
            }

            $model = self::loadPooled($backend, $modelPath);
            $outputs = $model->run(['input' => $input]);
            $scores = $outputs['output'] ?? \reset($outputs);

            if (\is_array($scores) && isset($scores[0]) && \is_numeric($scores[0])) {
                $maxScore = (float) \max($scores);
                $label = (string) \array_search($maxScore, $scores, true);

                return new ClassificationResult($label, $maxScore);
            }

            return new ClassificationResult('unknown', 0.0);
        });
    }

    /**
     * @return array{categories: array<string, float>, flagged: bool}
     */
    public static function moderate(string $text): array
    {
        self::ensureConfigured();

        return self::observability()->measure('moderate', static function () use ($text): array {
            $backend = self::registry()->get(self::activeBackend());
            $config = self::configuration();
            $modelPath = $config->get('backends.moderate.model_path');

            if (!\is_string($modelPath) || $modelPath === '') {
                throw new ConfigurationException('backends.moderate.model_path', 'a moderation model path must be configured');
            }

            $model = self::loadPooled($backend, $modelPath);
            $outputs = $model->run(['input' => $text]);
            $scores = $outputs['output'] ?? \reset($outputs);

            if (!\is_array($scores)) {
                return ['categories' => [], 'flagged' => false];
            }

            $maxScore = $scores !== [] ? (float) \max($scores) : 0.0;

            return [
                'categories' => $scores,
                'flagged' => $maxScore > 0.5,
            ];
        });
    }

    /**
     * @param array<string, float|int|string> $features
     */
    public static function predict(array $features): mixed
    {
        self::ensureConfigured();

        return self::observability()->measure('predict', static function () use ($features): mixed {
            $factory = self::factory();
            $backend = $factory->createBackend(BackendType::CpuNative);
            $config = self::configuration();
            $modelPath = $config->get('backends.predict.model_path');

            if (!\is_string($modelPath) || $modelPath === '') {
                throw new ConfigurationException('backends.predict.model_path', 'a prediction model path must be configured');
            }

            $model = self::loadPooled($backend, $modelPath);

            return $model->run($features);
        });
    }

    public static function pipeline(): Pipeline
    {
        return self::factory()->createPipeline();
    }

    public static function vector(string $collection): VectorStore
    {
        self::ensureConfigured();
        $dimension = (int) self::configuration()->get('vector.dimension', 0);

        return self::factory()->createVectorStore($collection, $dimension);
    }

    public static function hub(): ModelHub
    {
        return self::factory()->createModelHub();
    }

    public static function tokenizer(string $modelName): Tokenizer
    {
        return self::factory()->createTokenizer($modelName);
    }

    /**
     * Resolves an available llama chat model from configuration.
     *
     * @throws BackendNotAvailableException when no chat-capable backend is available
     * @throws ConfigurationException       when the llama model path is not configured
     */
    private static function embedder(): Embedder
    {
        $config = self::configuration();
        $modelPath = $config->get('backends.embedding.model_path');
        $modelName = \is_string($modelPath) && $modelPath !== ''
            ? $modelPath
            : (string) $config->get('embedding.model', 'all-MiniLM-L6-v2');

        return self::factory()->createEmbedder($modelName);
    }

    private static function chatModel(): LlamaModel
    {
        self::ensureConfigured();
        $registry = self::registry();

        if (!$registry->has(BackendType::Llama) || !$registry->get(BackendType::Llama)->isAvailable()) {
            throw new BackendNotAvailableException(
                'llama',
                'AI::chat()/stream() require the llama-backend with an available GGUF model (Phase 2)',
            );
        }

        $modelPath = self::configuration()->get('backends.llama.model_path');

        if (!\is_string($modelPath) || $modelPath === '') {
            throw new ConfigurationException('backends.llama.model_path', 'a GGUF model path must be configured');
        }

        $model = self::loadPooled($registry->get(BackendType::Llama), $modelPath, self::$activeDevice);

        if (!$model instanceof LlamaModel) {
            throw new BackendNotAvailableException('llama', 'the llama backend did not return a llama model');
        }

        return $model;
    }

    /**
     * @param array<string, mixed>|null $options
     */
    private static function samplingParams(?array $options): SamplingParams
    {
        $config = self::configuration();
        $options ??= [];

        $temperature = \is_numeric($options['temperature'] ?? null)
            ? (float) $options['temperature']
            : $config->temperature();
        $topP = \is_numeric($options['top_p'] ?? null) ? (float) $options['top_p'] : $config->topP();
        $maxTokens = \is_numeric($options['max_tokens'] ?? null)
            ? (int) $options['max_tokens']
            : $config->maxTokens();

        return new SamplingParams(temperature: $temperature, topP: $topP, maxTokens: $maxTokens);
    }

    /**
     * Builds an explicit llama sampler from chat options, or null to let the model pick one
     * from the sampling parameters. `grammar` (a GBNF string or a JSON-Schema array) forces
     * grammar-constrained sampling; `sampler` selects `greedy|top_k|top_p|grammar` by name.
     *
     * @param array<string, mixed>|null $options
     */
    private static function samplerFor(?array $options): ?Sampler
    {
        $options ??= [];
        $grammar = $options['grammar'] ?? null;

        if ($grammar !== null) {
            $gbnf = \is_array($grammar) ? GbnfGrammar::fromJsonSchema($grammar) : GbnfGrammar::fromString((string) $grammar);

            return new GrammarSampler($gbnf);
        }

        $type = $options['sampler'] ?? null;

        if (\is_string($type) && $type !== '') {
            return (new SamplerFactory())->create($type);
        }

        return null;
    }

    /**
     * @param array<int, mixed>         $messages
     * @param array<string, mixed>|null $options
     */
    public static function streamResponse(array $messages, ?array $options = null): ResponseInterface
    {
        self::ensureConfigured();

        return StreamResponse::create(self::stream($messages, $options));
    }

    private static function ensureConfigured(): void
    {
        if (self::$config === null) {
            throw new InvalidStateException('AI::config() must be called before using the facade.');
        }
    }

    private static function observability(): Observability
    {
        return self::$observability ??= new Observability();
    }

    private static function modelPool(): ModelPool
    {
        return self::$modelPool ??= new ModelPool();
    }

    /**
     * Loads a model through the shared pool: reuse the cached instance when present,
     * otherwise load once and cache it under a backend+path+device key.
     */
    private static function loadPooled(\FerryAI\Core\Contracts\Backend $backend, string $modelPath, ?Device $device = null): \FerryAI\Core\Contracts\Model
    {
        $key = $backend::class . '|' . $modelPath . '|' . ($device === null ? 'auto' : $device->value);
        $pool = self::modelPool();

        $cached = $pool->acquire($key);

        if ($cached !== null) {
            return $cached;
        }

        $model = $backend->load($modelPath, $device);
        $pool->put($key, $model, $model->metadata()->sizeBytes);

        return $model;
    }

    private static function poolMemoryLimit(AIConfig $config): ?int
    {
        $limit = $config->get('model_pool.max_memory_bytes');

        return \is_int($limit) ? $limit : null;
    }

    private static function registry(): BackendRegistry
    {
        if (self::$registry === null) {
            throw new InvalidStateException('AI::config() must be called before using the facade.');
        }

        return self::$registry;
    }

    private static function factory(): AIFactory
    {
        if (self::$factory === null) {
            throw new InvalidStateException('AI::config() must be called before using the facade.');
        }

        return self::$factory;
    }

    private static function configuration(): AIConfig
    {
        if (self::$config === null) {
            throw new InvalidStateException('AI::config() must be called before using the facade.');
        }

        return self::$config;
    }

    private static function resolveBackendType(string $name): BackendType
    {
        return match ($name) {
            'onnx' => BackendType::Onnx,
            'llama' => BackendType::Llama,
            'cpu', 'cpu_native' => BackendType::CpuNative,
            'auto' => self::registry()->autoDetect(),
            default => throw new ConfigurationException('backend', \sprintf("unknown backend '%s'", $name)),
        };
    }
}
