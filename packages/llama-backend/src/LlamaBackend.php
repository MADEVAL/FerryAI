<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Core\Exception\ModelLoadException;
use FerryAI\Core\Exception\ModelNotFoundException;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\LlamaBackend\Runtime\LlamaRuntimeInterface;
use FerryAI\LlamaBackend\Runtime\NativeLlamaRuntime;

/**
 * llama.cpp backend. All native interaction is delegated to a {@see LlamaRuntimeInterface}.
 */
final class LlamaBackend implements Backend
{
    public function __construct(
        private readonly LlamaRuntimeInterface $runtime = new NativeLlamaRuntime(),
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

        return $this->runtime->supportsGpu() ? [Device::CUDA, Device::CPU] : [Device::CPU];
    }

    #[\Override]
    public function load(string $source, ?Device $device = null): Model
    {
        if (preg_match('#^(https?://|hf://)#i', $source) === 1) {
            throw new ModelLoadException(
                $source,
                'remote model loading requires the model-hub package (Phase 3); download the .gguf file locally first',
            );
        }

        if (!$this->runtime->isAvailable()) {
            throw new BackendNotAvailableException('llama', 'the llama.cpp shared library could not be loaded');
        }

        if (!is_file($source)) {
            throw new ModelNotFoundException($source);
        }

        $target = Device::resolve($device ?? Device::AUTO, $this->availableDevices());
        $gpuLayers = $target === Device::CPU ? 0 : 999;

        $session = $this->runtime->createSession(
            $source,
            new LlamaModelParams(nGpuLayers: $gpuLayers),
            new LlamaContextParams(nGpuLayers: $gpuLayers),
        );

        $name = pathinfo($source, \PATHINFO_FILENAME);
        $fileSize = filesize($source);

        $metadata = new ModelMetadata(
            name: $name,
            version: '1.0',
            author: '',
            license: '',
            tags: [],
            sizeBytes: $fileSize === false ? 0 : $fileSize,
        );

        return new LlamaModel(
            $session,
            $this->runtime,
            new ChatFormatter(ChatFormatter::detectFormat($name)),
            null,
            $metadata,
            $target,
        );
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
