<?php

declare(strict_types=1);

namespace FerryAI\Symfony\DependencyInjection;

final class Configuration
{
    /**
     * @return array<string, mixed>
     */
    public function getConfigTree(): array
    {
        return [
            'ferry_ai' => [
                'backend' => 'auto',
                'device' => 'auto',
                'model_cache' => '%kernel.project_dir%/var/models',
                'max_tokens' => 2048,
                'temperature' => 0.7,
                'top_p' => 1.0,
                'verify_signatures' => true,
                'backends' => [
                    'onnx' => [
                        'providers' => ['CUDA', 'CPU'],
                        'graph_optimization' => 'ALL',
                    ],
                    'llama' => [
                        'model_path' => null,
                        'n_ctx' => 2048,
                        'n_gpu_layers' => 0,
                    ],
                ],
                'warmup' => [],
                'log_channel' => 'stack',
            ],
        ];
    }
}
