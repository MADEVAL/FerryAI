<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend;

/**
 * Bridges the CPU backend to an external tabular-ML estimator (RubixML).
 *
 * Implemented by {@see RubixMLAdapter}. Kept as an interface so {@see CpuNativeModel}
 * can delegate inference without binding to RubixML types, and so tests can supply
 * a fake without the optional `rubix/ml` dependency installed.
 */
interface Predictor
{
    public function isAvailable(): bool;

    /**
     * @param array<int, array<int, float|int|string>> $samples
     *
     * @return array<int, mixed>
     */
    public function predict(mixed $model, array $samples): array;

    /**
     * @param array<int, array<int, float|int|string>> $samples
     *
     * @return array<int, array<string, float>>
     */
    public function proba(mixed $model, array $samples): array;
}
