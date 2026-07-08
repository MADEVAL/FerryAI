<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Runtime;

use FerryAI\Core\Enums\GraphOptimizationLevel;
use FerryAI\OnnxBackend\OnnxRuntimeFactory;
use OnnxRuntime\Model as OrtModel;

/**
 * Production {@see OnnxRuntimeInterface} backed by ankane/onnxruntime.
 *
 * Excluded from static analysis (bridges to an untyped FFI library); covered by the
 * integration test suite, which runs only when the native ONNX Runtime library is present.
 */
final class NativeOnnxRuntime implements OnnxRuntimeInterface
{
    private readonly OnnxRuntimeFactory $factory;

    public function __construct(?OnnxRuntimeFactory $factory = null)
    {
        $this->factory = $factory ?? new OnnxRuntimeFactory();
    }

    public function isAvailable(): bool
    {
        return $this->factory->isAvailable();
    }

    public function version(): string
    {
        return $this->factory->version();
    }

    /**
     * @return list<string>
     */
    public function availableProviders(): array
    {
        return $this->factory->availableProviders();
    }

    /**
     * @param list<string> $providerNames
     */
    public function createSession(
        string $path,
        array $providerNames,
        GraphOptimizationLevel $optimization = GraphOptimizationLevel::ALL,
    ): OnnxSession {
        return new NativeOnnxSession($this->factory->createModel($path, $providerNames, $optimization));
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public function sessionInputs(OnnxSession $session): array
    {
        return $this->transformNodes($this->model($session)->inputs());
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public function sessionOutputs(OnnxSession $session): array
    {
        return $this->transformNodes($this->model($session)->outputs());
    }

    /**
     * @param array<string, array<mixed>|string> $inputs
     *
     * @return array<string, array{data: array<mixed>, shape: int[], dtype: string}>
     */
    public function run(OnnxSession $session, array $inputs): array
    {
        $model = $this->model($session);
        $outputMeta = $this->transformNodes($model->outputs());

        /** @var array<string, array<mixed>> $predictions */
        $predictions = $model->predict($inputs);

        $result = [];

        foreach ($predictions as $name => $data) {
            $result[$name] = [
                'data' => $data,
                'shape' => $this->inferShape($data),
                'dtype' => $outputMeta[$name]['dtype'] ?? 'float',
            ];
        }

        return $result;
    }

    private function model(OnnxSession $session): OrtModel
    {
        if (!$session instanceof NativeOnnxSession) {
            throw new \FerryAI\Core\Exception\InvalidStateException('NativeOnnxRuntime requires a NativeOnnxSession.');
        }

        return $session->model();
    }

    /**
     * @param array<int, array{name: string, type?: string, shape?: array<int, int|string>}> $nodes
     *
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    private function transformNodes(array $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $name = (string) $node['name'];
            $result[$name] = [
                'name' => $name,
                'shape' => $this->normalizeShape($node['shape'] ?? []),
                'dtype' => $this->elementType((string) ($node['type'] ?? '')),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, int|string> $shape
     *
     * @return int[]
     */
    private function normalizeShape(array $shape): array
    {
        return array_map(static fn(int|string $dim): int => \is_int($dim) ? $dim : -1, $shape);
    }

    private function elementType(string $type): string
    {
        if (preg_match('/^tensor\((.+)\)$/', $type, $matches) === 1) {
            return $matches[1];
        }

        return $type;
    }

    /**
     * @return int[]
     */
    private function inferShape(mixed $data): array
    {
        $dims = [];
        $node = $data;

        while (\is_array($node)) {
            $dims[] = \count($node);
            $key = array_key_first($node);
            $node = $key === null ? null : ($node[$key] ?? null);
        }

        return $dims;
    }
}
