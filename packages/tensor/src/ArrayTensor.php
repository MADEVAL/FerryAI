<?php

declare(strict_types=1);

namespace FerryAI\Tensor;

use FerryAI\Core\Contracts\Tensor;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\Exception\DeviceNotAvailableException;
use FerryAI\Core\Exception\ShapeMismatchException;
use FerryAI\Core\Tensor\CommonTensorOps;
use FerryAI\Core\ValueObjects\Shape;

/**
 * Pure-PHP Tensor implementation used as the CPU fallback.
 *
 * Data is stored as a flat row-major list; the shape describes its layout.
 */
final class ArrayTensor implements Tensor
{
    use CommonTensorOps;
    /**
     * @param array<int, int|float> $data flat row-major values
     */
    public function __construct(
        private array $data,
        private Shape $shape,
        private DType $dtype = DType::Float32,
    ) {}

    /**
     * Builds a tensor from a nested PHP array, inferring the shape.
     *
     * @param array<mixed> $nested
     */
    public static function fromNested(array $nested, DType $dtype = DType::Float32): self
    {
        return new self(self::flatten($nested), new Shape(self::inferShape($nested)), $dtype);
    }

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
        if ($device === Device::CPU || $device === Device::AUTO) {
            return $this;
        }

        throw new DeviceNotAvailableException($device);
    }

    #[\Override]
    public function device(): Device
    {
        return Device::CPU;
    }

    /**
     * @return array<int, mixed>
     */
    #[\Override]
    public function toArray(): array
    {
        return self::buildNested($this->data, $this->shape->toArray());
    }

    #[\Override]
    public function data(): mixed
    {
        return $this->data;
    }

    #[\Override]
    public function add(Tensor $other): self
    {
        return $this->elementwise($other, 'add');
    }

    #[\Override]
    public function sub(Tensor $other): self
    {
        return $this->elementwise($other, 'sub');
    }

    #[\Override]
    public function mul(Tensor $other): self
    {
        return $this->elementwise($other, 'mul');
    }

    #[\Override]
    public function matmul(Tensor $other): self
    {
        $a = $this->shape->toArray();
        $b = $other->shape()->toArray();

        if (\count($a) !== 2 || \count($b) !== 2 || $a[1] !== $b[0]) {
            throw new ShapeMismatchException($this->shape, $other->shape());
        }

        [$m, $k] = $a;
        $n = $b[1];
        $right = self::asFlat($other);
        $result = [];

        for ($i = 0; $i < $m; ++$i) {
            for ($j = 0; $j < $n; ++$j) {
                $sum = 0;

                for ($p = 0; $p < $k; ++$p) {
                    /** @psalm-suppress InvalidOperand int/float tensor arithmetic */
                    $sum += $this->data[$i * $k + $p] * $right[$p * $n + $j];
                }

                $result[$i * $n + $j] = $sum;
            }
        }

        return new self($result, new Shape([$m, $n]), $this->dtype);
    }

    /**
     * @param int[]|null $axes
     */
    #[\Override]
    public function transpose(?array $axes = null): self
    {
        $dims = $this->shape->toArray();
        $rank = \count($dims);

        if ($rank < 2) {
            return new self($this->data, $this->shape, $this->dtype);
        }

        $axes ??= array_reverse(range(0, $rank - 1));

        self::assertAxesPermutation($axes, $rank);

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

        return new self($result, new Shape($newDims), $this->dtype);
    }

    #[\Override]
    public function reshape(Shape $newShape): self
    {
        if (!$newShape->isStatic() || $newShape->size() !== \count($this->data)) {
            throw new ShapeMismatchException($newShape, $this->shape);
        }

        return new self($this->data, $newShape, $this->dtype);
    }

    /**
     * @param array<int, int|array{int, int}> $slices
     */
    #[\Override]
    public function slice(array $slices): self
    {
        $sliced = self::applySlice($this->toArray(), $slices, 0);

        if (\is_array($sliced)) {
            return self::fromNested($sliced, $this->dtype);
        }

        return new self([$sliced], new Shape([]), $this->dtype);
    }

    #[\Override]
    public function __clone()
    {
        // Flat data is a value-type array and is copied on clone; nothing to do.
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
        return \count($this->data);
    }

    /**
     * @return array<int, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array{data: array<int, int|float>, dims: int[], dtype: string}
     */
    #[\Override]
    public function __serialize(): array
    {
        return [
            'data' => $this->data,
            'dims' => $this->shape->toArray(),
            'dtype' => $this->dtype->value,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\Override]
    public function __unserialize(array $data): void
    {
        /** @var array<int, int|float> $flat */
        $flat = $data['data'];
        /** @var int[] $dims */
        $dims = $data['dims'];
        $this->data = $flat;
        $this->shape = new Shape($dims);

        $dtypeValue = (string) ($data['dtype'] ?? '');
        $this->dtype = DType::tryFrom($dtypeValue)
            ?? throw new \FerryAI\Core\Exception\InvalidStateException(
                \sprintf("Cannot unserialize ArrayTensor: unknown dtype '%s'.", $dtypeValue),
            );
    }

    /**
     * @param 'add'|'sub'|'mul' $operation
     */
    private function elementwise(Tensor $other, string $operation): self
    {
        if ($this->shape->toArray() !== $other->shape()->toArray()) {
            throw new ShapeMismatchException($this->shape, $other->shape());
        }

        $right = self::asFlat($other);
        $result = [];

        foreach ($this->data as $index => $value) {
            $operand = $right[$index];
            /** @psalm-suppress InvalidOperand int/float tensor arithmetic */
            $result[$index] = match ($operation) {
                'add' => $value + $operand,
                'sub' => $value - $operand,
                'mul' => $value * $operand,
            };
        }

        return new self($result, $this->shape, $this->dtype);
    }

    /**
     * @return array<int, int|float>
     */
    private static function asFlat(Tensor $tensor): array
    {
        if ($tensor instanceof self) {
            return $tensor->data;
        }

        /** @var array<int, int|float> $flat */
        $flat = self::flatten($tensor->toArray());

        return $flat;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<int, int|float>
     */
    private static function flatten(array $data): array
    {
        $flat = [];

        array_walk_recursive($data, static function (mixed $value) use (&$flat): void {
            /** @var int|float $value */
            $flat[] = $value;
        });

        return $flat;
    }

    /**
     * @param array<mixed> $data
     *
     * @return int[]
     */

    private static function buildNested(array $flat, array $dims): array
    {
        if (\count($dims) <= 1) {
            return array_values($flat);
        }

        $childSize = max(1, (int) array_product(\array_slice($dims, 1)));
        $result = [];

        foreach (array_chunk($flat, $childSize) as $chunk) {
            $result[] = self::buildNested($chunk, \array_slice($dims, 1));
        }

        return $result;
    }

    /**
     * @param int[] $axes
     *
     * @throws \FerryAI\Core\Exception\ValidationException when $axes is not a permutation of 0..rank-1
     */
    private static function assertAxesPermutation(array $axes, int $rank): void
    {
        $expected = range(0, $rank - 1);
        $sorted = $axes;
        sort($sorted);

        if (\count($axes) !== $rank || $sorted !== $expected) {
            throw new \FerryAI\Core\Exception\ValidationException(\sprintf(
                'transpose() axes must be a permutation of [0..%d]; got [%s].',
                $rank - 1,
                implode(', ', $axes),
            ));
        }
    }
}
