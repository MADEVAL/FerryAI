<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend;

final class RubixMLAdapter
{
    private bool $available;

    public function __construct()
    {
        $this->available = \class_exists('Rubix\ML\Estimator');
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * @return mixed
     */
    public function loadModel(string $path)
    {
        if (!$this->available) {
            throw new \RuntimeException('RubixML is not installed');
        }

        if (!\file_exists($path)) {
            throw new \FerryAI\Core\Exception\ModelNotFoundException($path);
        }

        $content = \file_get_contents($path);

        if ($content === false) {
            throw new \FerryAI\Core\Exception\ModelLoadException($path, 'Cannot read model file');
        }

        $data = \unserialize($content);

        if ($data === false) {
            throw new \FerryAI\Core\Exception\ModelLoadException($path, 'Cannot unserialize RubixML model');
        }

        return $data;
    }

    /**
     * @param  mixed                         $model
     * @param  array<int, array<int, float>> $samples
     * @return array<int, mixed>
     */
    public function predict(mixed $model, array $samples): array
    {
        if (!$this->available) {
            throw new \RuntimeException('RubixML is not installed');
        }

        throw new \RuntimeException('RubixML adapter not fully implemented');
    }

    /**
     * @param  mixed                         $model
     * @param  array<int, array<int, float>> $samples
     * @return array<int, array<float>>
     */
    public function proba(mixed $model, array $samples): array
    {
        if (!$this->available) {
            throw new \RuntimeException('RubixML is not installed');
        }

        throw new \RuntimeException('RubixML adapter not fully implemented');
    }
}
