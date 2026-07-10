<?php

declare(strict_types=1);

namespace FerryAI\Core\Contracts;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\ValueObjects\Shape;

/**
 * @extends \ArrayAccess<int, mixed>
 */
interface Tensor extends \ArrayAccess, \Countable, \JsonSerializable
{
    /**
     * Returns the tensor shape.
     */
    public function shape(): Shape;

    /**
     * Returns the element data type.
     */
    public function dtype(): DType;

    /**
     * Transfers the tensor to the target device, returning a NEW tensor.
     *
     * @throws \FerryAI\Core\Exception\DeviceNotAvailableException
     */
    public function to(Device $device): self;

    /**
     * Returns the device the tensor currently resides on.
     */
    public function device(): Device;

    /**
     * Exports the tensor data to a PHP array. EXPENSIVE — copies all data from native memory.
     *
     * @return array<int, mixed>
     */
    public function toArray(): array;

    /**
     * Returns the raw FFI buffer (pointer to native memory) for zero-copy transfer.
     *
     * @return mixed FFI\CData pointer
     */
    public function data(): mixed;

    /**
     * Element-wise addition; returns a new tensor.
     *
     * @throws \FerryAI\Core\Exception\ShapeMismatchException
     */
    public function add(self $other): self;

    /**
     * Element-wise subtraction; returns a new tensor.
     */
    public function sub(self $other): self;

    /**
     * Element-wise multiplication; returns a new tensor.
     */
    public function mul(self $other): self;

    /**
     * Matrix multiplication.
     *
     * @throws \FerryAI\Core\Exception\ShapeMismatchException
     */
    public function matmul(self $other): self;

    /**
     * Transposes the tensor.
     *
     * @param int[]|null $axes axis order; null reverses the axes
     */
    public function transpose(?array $axes = null): self;

    /**
     * Reshapes the tensor; the total number of elements must be preserved.
     *
     * @throws \FerryAI\Core\Exception\ShapeMismatchException
     */
    public function reshape(Shape $newShape): self;

    /**
     * Slices the tensor along axes.
     *
     * @param array<int, int|array{int, int}> $slices axis => index (int) or [start, length]
     */
    public function slice(array $slices): self;

    /**
     * Clone hook (PHP 8.5 clone-with device transfer) without mutating the original.
     */
    public function __clone();

    /**
     * @return array<int, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array;

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array;

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void;
}
