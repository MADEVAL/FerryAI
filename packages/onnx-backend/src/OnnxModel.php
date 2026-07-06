<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend;

use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Contracts\Tensor;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\InferenceException;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\Core\ValueObjects\Shape;
use FerryAI\OnnxBackend\Runtime\OnnxRuntimeInterface;
use FerryAI\OnnxBackend\Runtime\OnnxSession;

/**
 * A loaded ONNX model. Delegates execution to the runtime seam and wraps outputs as {@see OnnxTensor}.
 */
final class OnnxModel implements Model
{
    /** @var array<string, array{name: string, shape: int[], dtype: string}> */
    private readonly array $inputInfo;

    /** @var array<string, array{name: string, shape: int[], dtype: string}> */
    private readonly array $outputInfo;

    private bool $unloaded = false;

    public function __construct(
        private ?OnnxSession $session,
        private readonly OnnxRuntimeInterface $runtime,
        private readonly ModelMetadata $metadata,
        private readonly Device $deviceType,
    ) {
        $this->inputInfo = $session === null ? [] : $runtime->sessionInputs($session);
        $this->outputInfo = $session === null ? [] : $runtime->sessionOutputs($session);
    }

    /**
     * @param array<string, mixed> $inputs
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function run(array $inputs): array
    {
        if ($this->unloaded || $this->session === null) {
            throw new InferenceException('Model has been unloaded; load the model again before running inference.');
        }

        $feed = [];

        foreach ($inputs as $name => $value) {
            if ($value instanceof Tensor) {
                $feed[$name] = $value->toArray();
            } elseif (\is_array($value) || \is_string($value)) {
                $feed[$name] = $value;
            } else {
                throw new InferenceException(\sprintf(
                    "Input '%s' must be a Tensor, a PHP array or a string; got %s.",
                    $name,
                    get_debug_type($value),
                ));
            }
        }

        $outputs = $this->runtime->run($this->session, $feed);
        $result = [];

        foreach ($outputs as $name => $output) {
            $result[$name] = new OnnxTensor(
                $output['data'],
                new Shape($output['shape']),
                OnnxTypeMapper::toDType($output['dtype']),
                $this->deviceType,
            );
        }

        return $result;
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    #[\Override]
    public function inputs(): array
    {
        return $this->inputInfo;
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    #[\Override]
    public function outputs(): array
    {
        return $this->outputInfo;
    }

    #[\Override]
    public function metadata(): ModelMetadata
    {
        return $this->metadata;
    }

    #[\Override]
    public function device(): Device
    {
        return $this->deviceType;
    }

    #[\Override]
    public function unload(): void
    {
        $this->session = null;
        $this->unloaded = true;
    }
}
