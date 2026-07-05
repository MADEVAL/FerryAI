<?php

declare(strict_types=1);

namespace FerryAI\Symfony;

use FerryAI\AI;

final class AIBundle
{
    public function boot(): void
    {
        $config = $this->getDefaultConfig();
        AI::config($config);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        return [
            'backend' => \getenv('FERRY_AI_BACKEND') ?: 'auto',
            'device' => \getenv('FERRY_AI_DEVICE') ?: 'auto',
            'model_cache' => \getenv('FERRY_AI_MODEL_CACHE') ?: \sys_get_temp_dir() . '/ferry-ai-models',
            'max_tokens' => 2048,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'verify_signatures' => true,
            'backends' => [
                'onnx' => [
                    'providers' => ['CUDA', 'CPU'],
                ],
                'llama' => [
                    'model_path' => '',
                ],
            ],
            'warmup' => [],
        ];
    }
}
