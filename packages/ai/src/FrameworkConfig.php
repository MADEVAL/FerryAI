<?php

declare(strict_types=1);

namespace FerryAI;

/**
 * Shared configuration defaults for the framework adapters (Laravel service provider, Symfony DI
 * extension). Both read the same FERRY_AI_* environment variables, so the defaults and the env
 * reader live here instead of being copied byte-for-byte into each adapter.
 */
final class FrameworkConfig
{
    /**
     * The common default configuration, resolved from FERRY_AI_* environment variables.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
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
     * Reads an environment variable, returning $default only when the variable is unset.
     * Unlike `getenv(...) ?: $default`, this preserves falsy-but-valid values such as "0".
     */
    public static function env(string $name, string $default): string
    {
        $value = \getenv($name);

        return $value === false ? $default : $value;
    }
}
