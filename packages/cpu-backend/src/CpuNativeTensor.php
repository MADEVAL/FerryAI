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
        return \array_values($this->buildNested([...$this->data], $this->dimensions));
    }

    #[\Override]
    public function data(): mixed
    {
        return $this->data;
    }

    #[\Override]
    public function add(Tensor $other): Tensor
    {
        return $this->elementwise($other, 'add');
    }

    #[\Override]
    public function sub(Tensor $other): Tensor
    {
        return $this->elementwise($other, 'sub');
    }

    #[\Override]
    public function mul(Tensor $other): Tensor
    {
        return $this->elementwise($other, 'mul');
    }

    #[\Override]
    public function matmul(Tensor $other): Tensor
    {
        $a = $this->dimensions;
        $b = $other->shape()->toArray();

        if (\count($a) !== 2 || \count($b) !== 2 || $a[1] !== $b[0]) {
            throw new \FerryAI\Core\Exception\ShapeMismatchException($this->shape(), $other->shape());
        }

        [$m, $k] = $a;
        $n = $b[1];
        $right = self::flatten($other->toArray());
        $result = [];

        for ($i = 0; $i < $m; ++$i) {
            for ($j = 0; $j < $n; ++$j) {
                $sum = 0.0;

                for ($p = 0; $p < $k; ++$p) {
                    $sum += $this->data[$i * $k + $p] * $right[$p * $n + $j];
                }

                $result[$i * $n + $j] = $sum;
            }
        }

        return new self($result, [$m, $n]);
    }

    #[\Override]
    public function transpose(?array $axes = null): Tensor
    {
        $dims = $this->dimensions;
        $rank = \count($dims);

        if ($rank < 2) {
            return new self($this->data, $dims);
        }

        $axes ??= \array_reverse(\range(0, $rank - 1));

        $newDims = [];

        foreach ($axes as $axis) {
            $newDims[] = $dims[$axis];
        }

        $strides = self::strides($dims);
        $result = [];
        $total = \count($this->data);

        for ($i = 0; $i < $total; ++$i) {
            $multi = self::unravel($i, $newDims);
            $sourceIndex = 0;

            foreach ($axes as $position => $axis) {
                $sourceIndex += $multi[$position] * $strides[$axis];
            }

            $result[$i] = $this->data[$sourceIndex];
        }

        return new self($result, $newDims);
    }

    #[\Override]
    public function reshape(Shape $newShape): Tensor
    {
        $dims = $newShape->toArray();

        if (!$newShape->isStatic() || (int) \array_product($dims) !== \count($this->data)) {
            throw new \FerryAI\Core\Exception\ShapeMismatchException($newShape, $this->shape());
        }

        return new self($this->data, $dims);
    }

    #[\Override]
    public function slice(array $slices): Tensor
    {
        $sliced = self::applySlice($this->toArray(), $slices, 0);

        if (\is_array($sliced)) {
            $flat = self::flatten($sliced);

            return new self($flat, self::inferShape($sliced));
        }

        return new self([(float) $sliced], []);
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
     * @param 'add'|'sub'|'mul' $operation
     */
    private function elementwise(Tensor $other, string $operation): self
    {
        if ($this->dimensions !== $other->shape()->toArray()) {
            throw new \FerryAI\Core\Exception\ShapeMismatchException($this->shape(), $other->shape());
        }

        $right = self::flatten($other->toArray());
        $result = [];

        foreach ($this->data as $index => $value) {
            $operand = (float) $right[$index];
            $result[$index] = match ($operation) {
                'add' => $value + $operand,
                'sub' => $value - $operand,
                'mul' => $value * $operand,
            };
        }

        return new self($result, $this->dimensions);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<int, float>
     */
    private static function flatten(array $data): array
    {
        $flat = [];

        \array_walk_recursive($data, static function (mixed $value) use (&$flat): void {
            $flat[] = (float) $value;
        });

        return $flat;
    }

    /**
     * @param array<mixed> $data
     *
     * @return int[]
     */
    private static function inferShape(array $data): array
    {
        $dims = [];
        $node = $data;

        while (\is_array($node)) {
            $dims[] = \count($node);
            $key = \array_key_first($node);
            $node = $key === null ? null : ($node[$key] ?? null);
        }

        return $dims;
    }

    /**
     * @param  int[] $dims
     * @return int[]
     */
    private static function strides(array $dims): array
    {
        $strides = [];
        $stride = 1;

        for ($i = \count($dims) - 1; $i >= 0; --$i) {
            $strides[$i] = $stride;
            $stride *= $dims[$i];
        }

        \ksort($strides);

        return $strides;
    }

    /**
     * @param  int[] $dims
     * @return int[]
     */
    private static function unravel(int $index, array $dims): array
    {
        $multi = [];

        for ($i = \count($dims) - 1; $i >= 0; --$i) {
            $multi[$i] = $index % $dims[$i];
            $index = \intdiv($index, $dims[$i]);
        }

        \ksort($multi);

        return $multi;
    }

    /**
     * @param array<int, mixed>               $data
     * @param array<int, int|array{int, int}> $slices
     */
    private static function applySlice(array $data, array $slices, int $axis): mixed
    {
        $spec = $slices[$axis] ?? null;

        if ($spec === null) {
            return \array_map(
                static fn(mixed $child): mixed => \is_array($child)
                    ? self::applySlice($child, $slices, $axis + 1)
                    : $child,
                $data,
            );
        }

        if (\is_int($spec)) {
            $selected = $data[$spec];

            return \is_array($selected) ? self::applySlice($selected, $slices, $axis + 1) : $selected;
        }

        [$start, $length] = $spec;
        $window = \array_slice($data, $start, $length);

        return \array_map(
            static fn(mixed $child): mixed => \is_array($child)
                ? self::applySlice($child, $slices, $axis + 1)
                : $child,
            $window,
        );
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
