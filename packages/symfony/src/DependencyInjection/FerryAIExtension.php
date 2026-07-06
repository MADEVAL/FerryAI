<?php

declare(strict_types=1);

namespace FerryAI\Symfony\DependencyInjection;

final class FerryAIExtension
{
    /** @var array<string, mixed> */
    private array $config = [];

    /**
     * Merges the given configuration layers over the defaults. Deep merge so nested keys
     * (e.g. backends.llama.model_path) can be overridden individually.
     *
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs): void
    {
        $this->config = \array_replace_recursive($this->getDefaultConfig(), ...$configs);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            'backend' => self::env('FERRY_AI_BACKEND', 'auto'),
            'device' => self::env('FERRY_AI_DEVICE', 'auto'),
            'model_cache' => self::env('FERRY_AI_MODEL_CACHE', \sys_get_temp_dir() . '/ferry-ai-models'),
            'max_tokens' => (int) self::env('FERRY_AI_MAX_TOKENS', '2048'),
            'temperature' => (float) self::env('FERRY_AI_TEMPERATURE', '0.7'),
            'top_p' => (float) self::env('FERRY_AI_TOP_P', '1.0'),
            'verify_signatures' => \filter_var(self::env('FERRY_AI_VERIFY_SIGNATURES', 'true'), FILTER_VALIDATE_BOOLEAN),
            'log_level' => self::env('FERRY_AI_LOG_LEVEL', 'warning'),
            'backends' => [
                'onnx' => [
                    'providers' => \explode(',', self::env('FERRY_AI_ONNX_PROVIDERS', 'CUDA,CPU')),
                    'graph_optimization' => self::env('FERRY_AI_ONNX_OPTIMIZATION', 'ALL'),
                ],
                'llama' => [
                    'model_path' => self::env('FERRY_AI_LLAMA_MODEL_PATH', ''),
                    'n_ctx' => (int) self::env('FERRY_AI_LLAMA_N_CTX', '2048'),
                    'n_gpu_layers' => (int) self::env('FERRY_AI_LLAMA_GPU_LAYERS', '0'),
                ],
            ],
            'warmup' => \array_filter(\explode(',', self::env('FERRY_AI_WARMUP', ''))),
        ];
    }

    /**
     * Reads an environment variable, returning $default only when it is unset (preserves "0").
     */
    private static function env(string $name, string $default): string
    {
        $value = \getenv($name);

        return $value === false ? $default : $value;
    }
}
