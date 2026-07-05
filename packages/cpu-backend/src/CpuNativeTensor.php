<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend;

use FerryAI\Core\Contracts\Tensor;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\ValueObjects\Shape;

final class CpuNativeTensor implements Tensor
{
    /** @var array<int, float> */
    private array $data;

    /** @var int[] */
    private array $dimensions;

    /**
     * @param array<int, float> $data
     * @param int[]             $dimensions
     */
    public function __construct(array $data, array $dimensions)
    {
        $this->data = $data;
        $this->dimensions = $dimensions;
    }

    #[\Override]
    public function shape(): Shape
    {
        return new Shape($this->dimensions);
    }

    #[\Override]
    public function dtype(): DType
    {
        return DType::Float32;
    }

    #[\Override]
    public function to(Device $device): self
    {
        if ($device === Device::CPU || $device === Device::AUTO) {
            return $this;
        }

        throw new \FerryAI\Core\Exception\DeviceNotAvailableException($device);
    }

    #[\Override]
    public function device(): Device
    {
        return Device::CPU;
    }

    /**
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement
     */
    #[\Override]
    public function toArray(): array
    {
        if (\count($this->dimensions) <= 1) {
            /** @psalm-suppress InvalidReturnStatement */
            return $this->data;
        }

        /** @psalm-suppress InvalidReturnStatement */
        return $this->buildNested([...$this->data], $this->dimensions);
    }

    #[\Override]
    public function data(): mixed
    {
        return $this->data;
    }

    #[\Override]
    public function add(Tensor $other): Tensor
    {
        throw new \RuntimeException('Not implemented in Phase 3.');
    }

    #[\Override]
    public function sub(Tensor $other): Tensor
    {
        throw new \RuntimeException('Not implemented in Phase 3.');
    }

    #[\Override]
    public function mul(Tensor $other): Tensor
    {
        throw new \RuntimeException('Not implemented in Phase 3.');
    }

    #[\Override]
    public function matmul(Tensor $other): Tensor
    {
        throw new \RuntimeException('Not implemented in Phase 3.');
    }

    #[\Override]
    public function transpose(?array $axes = null): Tensor
    {
        throw new \RuntimeException('Not implemented in Phase 3.');
    }

    #[\Override]
    public function reshape(Shape $newShape): Tensor
    {
        throw new \RuntimeException('Not implemented in Phase 3.');
    }

    #[\Override]
    public function slice(array $slices): Tensor
    {
        throw new \RuntimeException('Not implemented in Phase 3.');
    }

    #[\Override]
    public function __clone() {}

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
        if (\is_int($offset)) {
            $this->data[$offset] = (float) $value;
        }
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->data);
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    #[\Override]
    public function __serialize(): array
    {
        return [
            'data' => $this->data,
            'dimensions' => $this->dimensions,
        ];
    }

    #[\Override]
    public function __unserialize(array $data): void
    {
        $this->data = $data['data'];
        $this->dimensions = $data['dimensions'];
    }

    /**
     * @param  array<int, float>        $flat
     * @param  int[]                    $shape
     * @return array<int|string, mixed>
     */
    private function buildNested(array $flat, array $shape): array
    {
        [$result, $offset] = $this->buildNestedOffset($flat, $shape, 0);

        return $result;
    }

    /**
     * @param  array<int, float>                          $flat
     * @param  int[]                                      $shape
     * @return array{0: array<int|string, mixed>, 1: int}
     */
    private function buildNestedOffset(array $flat, array $shape, int $offset): array
    {
        if (\count($shape) === 1) {
            $slice = \array_slice($flat, $offset, $shape[0]);

            return [$slice, $offset + $shape[0]];
        }

        $size = $shape[0];
        $rest = \array_slice($shape, 1);
        $result = [];

        for ($i = 0; $i < $size; $i++) {
            [$nested, $offset] = $this->buildNestedOffset($flat, $rest, $offset);
            $result[] = $nested;
        }

        return [$result, $offset];
    }
}
