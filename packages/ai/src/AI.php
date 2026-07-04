<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\AIConfig;
use FerryAI\Core\Contracts\ModelHub;
use FerryAI\Core\Contracts\Pipeline;
use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Core\Enums\BackendType;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Core\Exception\ConfigurationException;
use FerryAI\Core\ValueObjects\ClassificationResult;
use FerryAI\Core\ValueObjects\EmbeddingResult;
use FerryAI\Core\ValueObjects\GenerationResult;
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

    /**
     * @param array<string, mixed> $config
     */
    public static function config(array $config): void
    {
        self::$config = AIConfig::fromArray($config);
        self::$factory = new AIFactory(self::$config);
        self::$registry = new BackendRegistry();
        self::$registry->register(BackendType::Onnx, self::$factory->createBackend(BackendType::Onnx));
        self::$activeBackend = BackendType::Onnx;
        self::$activeDevice = self::$config->device();
    }

    /**
     * @param string[] $modelIds
     */
    public static function warmup(array $modelIds): void
    {
        self::ensureConfigured();
        // Phase 1: model preloading requires the model-hub package (Phase 3); intentional no-op.
    }

    public static function reset(): void
    {
        self::$config = null;
        self::$factory = null;
        self::$registry = null;
        self::$activeBackend = null;
        self::$activeDevice = null;
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
            throw new \RuntimeException('AI::config() must be called before using the facade.');
        }

        return self::$activeBackend;
    }

    /**
     * Returns the currently active device.
     */
    public static function activeDevice(): Device
    {
        if (self::$activeDevice === null) {
            throw new \RuntimeException('AI::config() must be called before using the facade.');
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

        throw new \RuntimeException('AI::chat() requires the llama-backend package (Phase 2).');
    }

    /**
     * @param array<int, mixed>         $messages
     * @param array<string, mixed>|null $options
     *
     * @return \Generator<int, string>
     *
     * @psalm-suppress InvalidReturnType Phase 1 stub always throws; the Generator type is the Phase 2 contract.
     */
    public static function stream(array $messages, ?array $options = null): \Generator
    {
        self::ensureConfigured();

        throw new \RuntimeException('AI::stream() requires the llama-backend package (Phase 2).');
    }

    /**
     * @param string|string[] $input
     *
     * @return EmbeddingResult|EmbeddingResult[]
     */
    public static function embed(string|array $input): EmbeddingResult|array
    {
        self::ensureConfigured();

        throw new \RuntimeException(
            'AI::embed() requires the tokenizer (Phase 2) and embedding (Phase 3) packages.',
        );
    }

    public static function similarity(string $a, string $b): float
    {
        self::ensureConfigured();

        throw new \RuntimeException(
            'AI::similarity() requires the tokenizer (Phase 2) and embedding (Phase 3) packages.',
        );
    }

    public static function classify(mixed $input): ClassificationResult
    {
        self::ensureConfigured();

        throw new \RuntimeException(
            'AI::classify() requires the tokenizer (Phase 2) and embedding (Phase 3) packages.',
        );
    }

    /**
     * @return array{categories: array<string, float>, flagged: bool}
     */
    public static function moderate(string $text): array
    {
        self::ensureConfigured();

        throw new \RuntimeException(
            'AI::moderate() requires the tokenizer (Phase 2) and embedding (Phase 3) packages.',
        );
    }

    /**
     * @param array<string, float|int|string> $features
     */
    public static function predict(array $features): mixed
    {
        self::ensureConfigured();

        throw new \RuntimeException('AI::predict() requires the cpu-backend package (Phase 3).');
    }

    public static function pipeline(): Pipeline
    {
        return self::factory()->createPipeline();
    }

    public static function vector(string $collection): VectorStore
    {
        return self::factory()->createVectorStore($collection, 0);
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
     * @param array<int, mixed>         $messages
     * @param array<string, mixed>|null $options
     */
    public static function streamResponse(array $messages, ?array $options = null): ResponseInterface
    {
        self::ensureConfigured();

        return StreamResponse::create([]);
    }

    private static function ensureConfigured(): void
    {
        if (self::$config === null) {
            throw new \RuntimeException('AI::config() must be called before using the facade.');
        }
    }

    private static function registry(): BackendRegistry
    {
        if (self::$registry === null) {
            throw new \RuntimeException('AI::config() must be called before using the facade.');
        }

        return self::$registry;
    }

    private static function factory(): AIFactory
    {
        if (self::$factory === null) {
            throw new \RuntimeException('AI::config() must be called before using the facade.');
        }

        return self::$factory;
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
