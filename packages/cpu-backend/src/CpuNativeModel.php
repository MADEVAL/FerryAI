<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend;

use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\ValueObjects\ModelMetadata;

final class CpuNativeModel implements Model
{
    /** @var array<string, mixed> */
    private array $modelData;

    private bool $unloaded = false;

    /**
     * @param array<string, mixed> $modelData
     */
    public function __construct(
        private string $modelPath,
        array $modelData,
        private readonly ?object $estimator = null,
        private readonly ?Predictor $predictor = null,
    ) {
        $this->modelData = $modelData;
    }

    #[\Override]
    public function run(array $inputs): array
    {
        if ($this->unloaded) {
            throw new \RuntimeException('Model is unloaded');
        }

        if ($this->estimator !== null && $this->predictor !== null) {
            $predictions = $this->predictor->predict($this->estimator, self::toSamples($inputs));

            return ['output' => $predictions];
        }

        return [
            'output' => [0.5, 0.3, 0.2],
        ];
    }

    /**
     * @param  array<string, mixed>                     $inputs
     * @return array<int, array<int, float|int|string>>
     */
    private static function toSamples(array $inputs): array
    {
        if (isset($inputs['samples']) && \is_array($inputs['samples'])) {
            /** @var array<int, array<int, float|int|string>> $samples */
            $samples = \array_values($inputs['samples']);

            return $samples;
        }

        if (isset($inputs['input']) && \is_array($inputs['input'])) {
            /** @var array<int, float|int|string> $row */
            $row = \array_values($inputs['input']);

            return [$row];
        }

        /** @var array<int, float|int|string> $row */
        $row = \array_values($inputs);

        return [$row];
    }

    #[\Override]
    public function inputs(): array
    {
        $features = $this->modelData['features'] ?? [];

        return [
            'input' => [
                'name' => 'input',
                'shape' => [\count($features)],
                'dtype' => 'float32',
            ],
        ];
    }

    #[\Override]
    public function outputs(): array
    {
        return [
            'output' => [
                'name' => 'output',
                'shape' => [-1],
                'dtype' => 'float32',
            ],
        ];
    }

    #[\Override]
    public function metadata(): ModelMetadata
    {
        return new ModelMetadata(
            name: \basename($this->modelPath),
            version: '1.0',
            author: 'unknown',
            license: 'unknown',
            tags: [],
            sizeBytes: \file_exists($this->modelPath) ? (int) \filesize($this->modelPath) : 0,
        );
    }

    #[\Override]
    public function device(): Device
    {
        return Device::CPU;
    }

    #[\Override]
    public function unload(): void
    {
        $this->unloaded = true;
        $this->modelData = [];
    }
}
