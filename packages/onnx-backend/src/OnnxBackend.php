<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\GraphOptimizationLevel;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Core\Exception\ModelLoadException;
use FerryAI\Core\Exception\ModelNotFoundException;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\OnnxBackend\Runtime\NativeOnnxRuntime;
use FerryAI\OnnxBackend\Runtime\OnnxRuntimeInterface;

/**
 * ONNX Runtime backend. All native interaction is delegated to an {@see OnnxRuntimeInterface}.
 */
final class OnnxBackend implements Backend
{
    public function __construct(
        private readonly OnnxRuntimeInterface $runtime = new NativeOnnxRuntime(),
    ) {}

    /**
     * @return Device[]
     */
    #[\Override]
    public function availableDevices(): array
    {
        if (!$this->runtime->isAvailable()) {
            return [];
        }

        $devices = [];

        foreach ($this->runtime->availableProviders() as $provider) {
            $device = OnnxTypeMapper::providerToDevice($provider);

            if ($device !== null) {
                $devices[$device->value] = $device;
            }
        }

        if ($devices !== []) {
            $devices[Device::CPU->value] = Device::CPU;
        }

        $list = array_values($devices);
        usort($list, static fn(Device $a, Device $b): int => $b->priority() <=> $a->priority());

        return $list;
    }

    #[\Override]
    public function load(string $source, ?Device $device = null): Model
    {
        if (preg_match('#^(https?://|hf://)#i', $source) === 1) {
            throw new ModelLoadException(
                $source,
                'remote model loading requires the model-hub package (Phase 3); download the file locally first',
            );
        }

        if (!$this->runtime->isAvailable()) {
            throw new BackendNotAvailableException('onnx', 'the ONNX Runtime shared library could not be loaded');
        }

        if (!is_file($source)) {
            throw new ModelNotFoundException($source);
        }

        $target = Device::resolve($device ?? Device::AUTO, $this->availableDevices());
        $providerNames = OnnxTypeMapper::providerNamesForDevice($target);

        try {
            $session = $this->runtime->createSession($source, $providerNames, GraphOptimizationLevel::ALL);
        } catch (\Throwable $e) {
            // A GPU build can advertise a provider (e.g. CUDA) that fails to load at session time
            // because its native runtime is incomplete (missing CUDA/cuDNN/math libraries).
            // Fall back to CPU-only execution so inference still works.
            if ($target === Device::CPU || $providerNames === ['CPUExecutionProvider']) {
                throw new ModelLoadException($source, $e->getMessage());
            }

            $target = Device::CPU;
            $session = $this->runtime->createSession($source, ['CPUExecutionProvider'], GraphOptimizationLevel::ALL);
        }

        $fileSize = filesize($source);

        $metadata = new ModelMetadata(
            name: pathinfo($source, \PATHINFO_FILENAME),
            version: '1.0',
            author: '',
            license: '',
            tags: [],
            sizeBytes: $fileSize === false ? 0 : $fileSize,
        );

        return new OnnxModel($session, $this->runtime, $metadata, $target);
    }

    #[\Override]
    public function version(): string
    {
        return $this->runtime->version();
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return $this->runtime->isAvailable();
    }
}
