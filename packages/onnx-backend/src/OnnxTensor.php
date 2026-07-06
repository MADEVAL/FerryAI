<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend;

use FerryAI\Core\Contracts\Tensor;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\Exception\DeviceNotAvailableException;
use FerryAI\Core\ValueObjects\Shape;

/**
 * Tensor produced by the ONNX backend.
 *
 * It is a "bare" value wrapper: it exposes shape/dtype/data but does not implement
 * arithmetic. Tensor math belongs to the `tensor` package (which delegates to a backend).
 */
final class OnnxTensor implements Tensor
{
    private const string NO_ARITHMETIC = 'Use the backend/tensor package for tensor operations; OnnxTensor is a value wrapper.';

    /**
     * @param array<int, mixed> $data nested row-major values
     */
    public function __construct(
        private array $data,
        private Shape $shape,
        private DType $dtype,
        private Device $deviceType = Device::CPU,
    ) {}

    #[\Override]
    public function shape(): Shape
    {
        return $this->shape;
    }

    #[\Override]
    public function dtype(): DType
    {
        return $this->dtype;
    }

    #[\Override]
    public function to(Device $device): self
    {
        if ($device === $this->deviceType || $device === Device::AUTO) {
            return $this;
        }

        throw new DeviceNotAvailableException($device);
    }

    #[\Override]
    public function device(): Device
    {
        return $this->deviceType;
    }

    /**
     * @return array<int, mixed>
     */
    #[\Override]
    public function toArray(): array
    {
        return $this->data;
    }

    #[\Override]
    public function data(): mixed
    {
        return $this->data;
    }

    #[\Override]
    public function add(Tensor $other): self
    {
        throw new \BadMethodCallException(self::NO_ARITHMETIC);
    }

    #[\Override]
    public function sub(Tensor $other): self
    {
        throw new \BadMethodCallException(self::NO_ARITHMETIC);
    }

    #[\Override]
    public function mul(Tensor $other): self
    {
        throw new \BadMethodCallException(self::NO_ARITHMETIC);
    }

    #[\Override]
    public function matmul(Tensor $other): self
    {
        throw new \BadMethodCallException(self::NO_ARITHMETIC);
    }

    /**
     * @param int[]|null $axes
     */
    #[\Override]
    public function transpose(?array $axes = null): self
    {
        throw new \BadMethodCallException(self::NO_ARITHMETIC);
    }

    #[\Override]
    public function reshape(Shape $newShape): self
    {
        throw new \BadMethodCallException(self::NO_ARITHMETIC);
    }

    /**
     * @param array<int, int|array{int, int}> $slices
     */
    #[\Override]
    public function slice(array $slices): self
    {
        throw new \BadMethodCallException(self::NO_ARITHMETIC);
    }

    #[\Override]
    public function __clone()
    {
        // Nested data is a value-type array copied on clone; nothing to do.
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            throw new \BadMethodCallException('A tensor has a fixed shape; appending via [] is not supported.');
        }

        $this->data[$offset] = $value;
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('A tensor has a fixed shape; unsetting elements is not supported.');
    }

    #[\Override]
    public function count(): int
    {
        return max(0, $this->shape->size());
    }

    /**
     * @return array<int, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * @return array{data: array<int, mixed>, dims: int[], dtype: string, device: string}
     */
    #[\Override]
    public function __serialize(): array
    {
        return [
            'data' => $this->data,
            'dims' => $this->shape->toArray(),
            'dtype' => $this->dtype->value,
            'device' => $this->deviceType->value,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\Override]
    public function __unserialize(array $data): void
    {
        /** @var array<int, mixed> $values */
        $values = $data['data'];
        /** @var int[] $dims */
        $dims = $data['dims'];
        $this->data = $values;
        $this->shape = new Shape($dims);
        $this->dtype = DType::from((string) $data['dtype']);
        $this->deviceType = Device::from((string) $data['device']);
    }
}
