<?php

declare(strict_types=1);

namespace FerryAI\Core\ValueObjects;

use FerryAI\Core\Exception\ValidationException;

readonly class Shape implements \JsonSerializable, \Stringable
{
    /**
     * @param int[] $dimensions all dimensions >= 0; -1 is allowed for dynamic axes
     *
     * @throws ValidationException when a dimension is negative (other than -1)
     */
    public function __construct(public array $dimensions)
    {
        foreach ($dimensions as $dimension) {
            if ($dimension < -1) {
                throw new ValidationException(\sprintf(
                    'Invalid shape dimension %d: dimensions must be >= 0, or -1 for a dynamic axis.',
                    $dimension,
                ));
            }
        }
    }

    /**
     * Number of axes (rank).
     */
    public function rank(): int
    {
        return \count($this->dimensions);
    }

    /**
     * Total number of elements (product of dimensions); -1 when any axis is dynamic.
     */
    public function size(): int
    {
        if (!$this->isStatic()) {
            return -1;
        }

        $size = 1;

        foreach ($this->dimensions as $dimension) {
            $size *= $dimension;
        }

        return $size;
    }

    /**
     * Size along the given axis.
     *
     * @throws \OutOfBoundsException when the axis does not exist
     */
    public function dimension(int $axis): int
    {
        if (!\array_key_exists($axis, $this->dimensions)) {
            throw new \OutOfBoundsException(\sprintf('Axis %d does not exist in shape of rank %d.', $axis, $this->rank()));
        }

        return $this->dimensions[$axis];
    }

    /**
     * Whether the shape is fully static (no dynamic axes).
     */
    public function isStatic(): bool
    {
        return !\in_array(-1, $this->dimensions, true);
    }

    /**
     * @return int[]
     */
    public function toArray(): array
    {
        return $this->dimensions;
    }

    /**
     * Checks broadcasting compatibility with another shape (NumPy right-to-left rules).
     */
    public function compatibleWith(self $other): bool
    {
        $a = array_reverse($this->dimensions);
        $b = array_reverse($other->dimensions);
        $length = max(\count($a), \count($b));

        for ($i = 0; $i < $length; ++$i) {
            $dimA = $a[$i] ?? 1;
            $dimB = $b[$i] ?? 1;

            if ($dimA === $dimB || $dimA === 1 || $dimB === 1 || $dimA === -1 || $dimB === -1) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Creates a Shape from a comma-separated string such as "1,3,224,224".
     */
    public static function fromString(string $shape): self
    {
        $dimensions = array_map(
            static fn(string $part): int => (int) trim($part),
            explode(',', $shape),
        );

        return new self($dimensions);
    }

    /**
     * @return int[]
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->dimensions;
    }

    #[\Override]
    public function __toString(): string
    {
        return implode(',', $this->dimensions);
    }
}
