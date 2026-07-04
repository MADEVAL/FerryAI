<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\Enums\BackendType;

/**
 * Routes high-level tasks to the most appropriate backend.
 */
final class TaskRouter
{
    public function __construct(private readonly BackendRegistry $registry) {}

    public function routeForChat(): BackendType
    {
        return $this->isAvailable(BackendType::Llama) ? BackendType::Llama : BackendType::Onnx;
    }

    public function routeForEmbedding(): BackendType
    {
        return BackendType::Onnx;
    }

    public function routeForClassification(): BackendType
    {
        return $this->isAvailable(BackendType::Onnx) ? BackendType::Onnx : BackendType::CpuNative;
    }

    public function routeForPrediction(): BackendType
    {
        return BackendType::CpuNative;
    }

    public function routeFor(string $task): BackendType
    {
        return match ($task) {
            'chat', 'stream' => $this->routeForChat(),
            'embed', 'similarity' => $this->routeForEmbedding(),
            'classify', 'moderate' => $this->routeForClassification(),
            'predict' => $this->routeForPrediction(),
            default => $this->registry->autoDetect(),
        };
    }

    private function isAvailable(BackendType $type): bool
    {
        return $this->registry->has($type) && $this->registry->get($type)->isAvailable();
    }
}
