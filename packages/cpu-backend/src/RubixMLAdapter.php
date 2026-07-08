<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend;

/**
 * RubixML boundary: loads serialized estimators and runs inference.
 *
 * All RubixML access is dynamic (class names as strings), so this file needs no
 * compile-time dependency on `rubix/ml` — which is an optional, `suggest`-only
 * package (it cannot share the dev toolchain's `amphp/parallel ^2`). When RubixML
 * is not installed every method degrades gracefully by throwing a clear error.
 *
 * Excluded from PHPStan/Psalm like the other external-library boundaries.
 */
final class RubixMLAdapter implements Predictor
{
    private bool $available;

    public function __construct()
    {
        $this->available = \interface_exists('Rubix\ML\Estimator');
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * Loads a persisted estimator. Prefers RubixML's RBX format; falls back to a
     * plain PHP-serialized payload (legacy tabular models).
     */
    public function loadModel(string $path): mixed
    {
        if (!$this->available) {
            throw new \FerryAI\Core\Exception\BackendNotAvailableException('rubixml', 'RubixML is not installed');
        }

        if (!\file_exists($path)) {
            throw new \FerryAI\Core\Exception\ModelNotFoundException($path);
        }

        try {
            $filesystem = 'Rubix\ML\Persisters\Filesystem';
            $serializer = 'Rubix\ML\Serializers\RBX';
            $persistentModel = 'Rubix\ML\PersistentModel';

            return $persistentModel::load(new $filesystem($path, true, new $serializer()));
        } catch (\Throwable) {
            // Not an RBX archive — try a plain serialized payload.
        }

        $content = \file_get_contents($path);

        if ($content === false) {
            throw new \FerryAI\Core\Exception\ModelLoadException($path, 'Cannot read model file');
        }

        // Object injection guard: the raw fallback only accepts plain tabular payloads
        // (arrays/scalars). Real RubixML estimators must use the RBX format handled above,
        // so no arbitrary classes are ever instantiated from an untrusted model file.
        $data = \unserialize($content, ['allowed_classes' => false]);

        if ($data === false) {
            throw new \FerryAI\Core\Exception\ModelLoadException($path, 'Cannot unserialize RubixML model');
        }

        return $data;
    }

    /**
     * @param array<int, array<int, float|int|string>> $samples
     *
     * @return array<int, mixed>
     */
    #[\Override]
    public function predict(mixed $model, array $samples): array
    {
        if (!$this->available) {
            throw new \FerryAI\Core\Exception\BackendNotAvailableException('rubixml', 'RubixML is not installed');
        }

        $unlabeled = 'Rubix\ML\Datasets\Unlabeled';
        $dataset = new $unlabeled($samples);

        return \array_values($model->predict($dataset));
    }

    /**
     * @param array<int, array<int, float|int|string>> $samples
     *
     * @return array<int, array<string, float>>
     */
    #[\Override]
    public function proba(mixed $model, array $samples): array
    {
        if (!$this->available) {
            throw new \FerryAI\Core\Exception\BackendNotAvailableException('rubixml', 'RubixML is not installed');
        }

        if (!$model instanceof \Rubix\ML\Probabilistic) {
            throw new \FerryAI\Core\Exception\InferenceException('The estimator does not support probability estimates');
        }

        $unlabeled = 'Rubix\ML\Datasets\Unlabeled';
        $dataset = new $unlabeled($samples);

        return \array_values($model->proba($dataset));
    }
}
