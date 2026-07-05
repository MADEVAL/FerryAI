<?php

declare(strict_types=1);

namespace FerryAI\Laravel;

use FerryAI\AI;

final class AIServiceProvider
{
    private mixed $app;

    public function __construct(mixed $app = null)
    {
        $this->app = $app;
    }

    public function register(): void
    {
        $config = $this->getConfig();

        AI::config($config);
    }

    public function boot(): void
    {
        $warmup = $this->getConfig()['warmup'] ?? [];

        if ($warmup !== []) {
            AI::warmup($warmup);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'backend' => \getenv('FERRY_AI_BACKEND') ?: 'auto',
            'device' => \getenv('FERRY_AI_DEVICE') ?: 'auto',
            'model_cache' => \getenv('FERRY_AI_MODEL_CACHE') ?: \sys_get_temp_dir() . '/ferry-ai-models',
            'max_tokens' => (int) (\getenv('FERRY_AI_MAX_TOKENS') ?: 2048),
            'temperature' => (float) (\getenv('FERRY_AI_TEMPERATURE') ?: 0.7),
            'top_p' => (float) (\getenv('FERRY_AI_TOP_P') ?: 1.0),
            'verify_signatures' => \filter_var(\getenv('FERRY_AI_VERIFY_SIGNATURES') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'backends' => [
                'onnx' => [
                    'providers' => \explode(',', \getenv('FERRY_AI_ONNX_PROVIDERS') ?: 'CUDA,CPU'),
                    'graph_optimization' => \getenv('FERRY_AI_ONNX_OPTIMIZATION') ?: 'ALL',
                ],
                'llama' => [
                    'model_path' => \getenv('FERRY_AI_LLAMA_MODEL_PATH') ?: '',
                    'n_ctx' => (int) (\getenv('FERRY_AI_LLAMA_N_CTX') ?: 2048),
                    'n_gpu_layers' => (int) (\getenv('FERRY_AI_LLAMA_GPU_LAYERS') ?: 0),
                ],
            ],
            'warmup' => \array_filter(\explode(',', \getenv('FERRY_AI_WARMUP') ?: '')),
            'log_channel' => \getenv('FERRY_AI_LOG_CHANNEL') ?: 'stack',
        ];
    }
}
