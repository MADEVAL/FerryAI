<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Runtime;

use FerryAI\Core\Enums\GraphOptimizationLevel;

/**
 * Thin seam over the native ONNX Runtime.
 *
 * This is the single FFI boundary of the package: production code uses
 * `NativeOnnxRuntime` (backed by ankane/onnxruntime), unit tests inject a mock.
 * All values crossing this boundary are plain PHP data so the seam is fully mockable.
 */
interface OnnxRuntimeInterface
{
    /**
     * Whether the native ONNX Runtime shared library can be loaded.
     */
    public function isAvailable(): bool;

    /**
     * Native engine version string, e.g. "1.20.0".
     */
    public function version(): string;

    /**
     * Execution provider names available in the current environment.
     *
     * @return list<string>
     */
    public function availableProviders(): array;

    /**
     * Creates an inference session for the given local model file.
     *
     * @param list<string> $providerNames ordered execution provider names (most preferred first)
     */
    public function createSession(
        string $path,
        array $providerNames,
        GraphOptimizationLevel $optimization = GraphOptimizationLevel::ALL,
    ): OnnxSession;

    /**
     * Input metadata keyed by input name.
     *
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public function sessionInputs(OnnxSession $session): array;

    /**
     * Output metadata keyed by output name.
     *
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public function sessionOutputs(OnnxSession $session): array;

    /**
     * Runs inference.
     *
     * @param array<string, array<mixed>|string> $inputs input name => nested PHP array or string
     *
     * @return array<string, array{data: array<mixed>, shape: int[], dtype: string}>
     *                                                                               output name => materialised result
     */
    public function run(OnnxSession $session, array $inputs): array;
}
