<?php

declare(strict_types=1);

namespace FerryAI\Tensor;

use FerryAI\Core\Contracts\Tensor;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\ValueObjects\Shape;
use Random\Randomizer;

final class TensorFactory
{
    /**
     * Creates a tensor from a nested PHP array, inferring its shape.
     *
     * @param array<mixed> $data
     */
    public function fromArray(array $data, ?DType $dtype = null, ?Device $device = null): Tensor
    {
        $tensor = ArrayTensor::fromNested($data, $dtype ?? DType::Float32);

        return $this->onDevice($tensor, $device);
    }

    /**
     * Creates a tensor of the given shape filled with zeros.
     */
    public function zeros(Shape $shape, DType $dtype = DType::Float32, ?Device $device = null): Tensor
    {
        return $this->filled($shape, $this->isFloat($dtype) ? 0.0 : 0, $dtype, $device);
    }

    /**
     * Creates a tensor of the given shape filled with ones.
     */
    public function ones(Shape $shape, DType $dtype = DType::Float32, ?Device $device = null): Tensor
    {
        return $this->filled($shape, $this->isFloat($dtype) ? 1.0 : 1, $dtype, $device);
    }

    /**
     * Creates a tensor of the given shape filled with random values in [0, 1).
     */
    public function random(Shape $shape, DType $dtype = DType::Float32, ?Device $device = null): Tensor
    {
        $randomizer = new Randomizer();
        $size = max(0, $shape->size());
        $data = [];

        for ($i = 0; $i < $size; ++$i) {
            $data[] = $randomizer->getFloat(0.0, 1.0, \Random\IntervalBoundary::ClosedOpen);
        }

        return $this->onDevice(new ArrayTensor($data, $shape, $dtype), $device);
    }

    private function filled(Shape $shape, int|float $value, DType $dtype, ?Device $device): Tensor
    {
        $data = array_fill(0, max(0, $shape->size()), $value);

        return $this->onDevice(new ArrayTensor($data, $shape, $dtype), $device);
    }

    private function onDevice(Tensor $tensor, ?Device $device): Tensor
    {
        return $device === null ? $tensor : $tensor->to($device);
    }

    private function isFloat(DType $dtype): bool
    {
        return $dtype === DType::Float32 || $dtype === DType::Float16;
    }
}
