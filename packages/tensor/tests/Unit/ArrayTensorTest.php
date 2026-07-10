<?php

declare(strict_types=1);

namespace FerryAI\Tensor\Tests\Unit;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\Exception\DeviceNotAvailableException;
use FerryAI\Core\Exception\ShapeMismatchException;
use FerryAI\Core\ValueObjects\Shape;
use FerryAI\Tensor\ArrayTensor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayTensor::class)]
final class ArrayTensorTest extends TestCase
{
    public function testShapeAndDtypeAndDevice(): void
    {
        $tensor = ArrayTensor::fromNested([[1, 2], [3, 4]], DType::Int32);

        self::assertEquals(new Shape([2, 2]), $tensor->shape());
        self::assertSame(DType::Int32, $tensor->dtype());
        self::assertSame(Device::CPU, $tensor->device());
    }

    public function testToArrayRebuildsNestedStructure(): void
    {
        self::assertSame([[1, 2, 3], [4, 5, 6]], ArrayTensor::fromNested([[1, 2, 3], [4, 5, 6]])->toArray());
    }

    public function testCountReturnsTotalElements(): void
    {
        self::assertCount(6, ArrayTensor::fromNested([[1, 2, 3], [4, 5, 6]]));
    }

    public function testArrayAccessIsFlat(): void
    {
        $tensor = ArrayTensor::fromNested([[1, 2], [3, 4]]);

        self::assertTrue(isset($tensor[0]));
        self::assertSame(1, $tensor[0]);
        self::assertSame(4, $tensor[3]);
    }

    public function testTransposeRejectsDuplicateAxes(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);
        ArrayTensor::fromNested([[1, 2], [3, 4]])->transpose([0, 0]);
    }

    public function testTransposeRejectsOutOfRangeAxis(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);
        ArrayTensor::fromNested([[1, 2], [3, 4]])->transpose([0, 2]);
    }

    public function testTransposeRejectsWrongAxisCount(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);
        ArrayTensor::fromNested([[1, 2], [3, 4]])->transpose([0]);
    }

    public function testAppendViaArrayAccessIsRejected(): void
    {
        $tensor = ArrayTensor::fromNested([[1, 2], [3, 4]]);

        $this->expectException(\BadMethodCallException::class);
        $tensor[] = 9;
    }

    public function testOffsetUnsetIsRejected(): void
    {
        $tensor = ArrayTensor::fromNested([[1, 2], [3, 4]]);

        $this->expectException(\BadMethodCallException::class);
        unset($tensor[0]);
    }

    public function testAdd(): void
    {
        $a = ArrayTensor::fromNested([[1, 2], [3, 4]]);
        $b = ArrayTensor::fromNested([[5, 6], [7, 8]]);

        self::assertSame([[6, 8], [10, 12]], $a->add($b)->toArray());
    }

    public function testSubAndMul(): void
    {
        $a = ArrayTensor::fromNested([[5, 6], [7, 8]]);
        $b = ArrayTensor::fromNested([[1, 2], [3, 4]]);

        self::assertSame([[4, 4], [4, 4]], $a->sub($b)->toArray());
        self::assertSame([[5, 12], [21, 32]], $a->mul($b)->toArray());
    }

    public function testAddWithMismatchedShapeThrows(): void
    {
        $this->expectException(ShapeMismatchException::class);

        ArrayTensor::fromNested([[1, 2], [3, 4]])->add(ArrayTensor::fromNested([1, 2, 3]));
    }

    public function testMatmul(): void
    {
        $a = ArrayTensor::fromNested([[1, 2, 3], [4, 5, 6]]);
        $b = ArrayTensor::fromNested([[7, 8], [9, 10], [11, 12]]);

        $result = $a->matmul($b);

        self::assertEquals(new Shape([2, 2]), $result->shape());
        self::assertSame([[58, 64], [139, 154]], $result->toArray());
    }

    public function testMatmulWithIncompatibleShapesThrows(): void
    {
        $this->expectException(ShapeMismatchException::class);

        ArrayTensor::fromNested([[1, 2], [3, 4]])->matmul(ArrayTensor::fromNested([[1, 2, 3]]));
    }

    public function testTranspose(): void
    {
        self::assertSame(
            [[1, 4], [2, 5], [3, 6]],
            ArrayTensor::fromNested([[1, 2, 3], [4, 5, 6]])->transpose()->toArray(),
        );
    }

    public function testReshape(): void
    {
        $reshaped = ArrayTensor::fromNested([[1, 2, 3], [4, 5, 6]])->reshape(new Shape([3, 2]));

        self::assertSame([[1, 2], [3, 4], [5, 6]], $reshaped->toArray());
    }

    public function testReshapeWithIncompatibleSizeThrows(): void
    {
        $this->expectException(ShapeMismatchException::class);

        ArrayTensor::fromNested([[1, 2], [3, 4]])->reshape(new Shape([3, 3]));
    }

    public function testSliceRange(): void
    {
        $tensor = ArrayTensor::fromNested([[1, 2], [3, 4], [5, 6]]);

        self::assertSame([[1, 2], [3, 4]], $tensor->slice([0 => [0, 2]])->toArray());
    }

    public function testSliceIndex(): void
    {
        $tensor = ArrayTensor::fromNested([[1, 2], [3, 4], [5, 6]]);

        self::assertSame([3, 4], $tensor->slice([0 => 1])->toArray());
    }

    public function testToSameDeviceReturnsSelf(): void
    {
        $tensor = ArrayTensor::fromNested([1, 2, 3]);

        self::assertSame($tensor, $tensor->to(Device::CPU));
    }

    public function testToUnsupportedDeviceThrows(): void
    {
        $this->expectException(DeviceNotAvailableException::class);

        ArrayTensor::fromNested([1, 2, 3])->to(Device::CUDA);
    }

    public function testJsonSerializeEqualsToArray(): void
    {
        $tensor = ArrayTensor::fromNested([[1, 2], [3, 4]]);

        self::assertSame($tensor->toArray(), $tensor->jsonSerialize());
    }

    public function testSerializeRoundTrip(): void
    {
        $tensor = ArrayTensor::fromNested([[1, 2], [3, 4]], DType::Int32);
        $restored = unserialize(serialize($tensor));

        self::assertInstanceOf(ArrayTensor::class, $restored);
        self::assertEquals($tensor->shape(), $restored->shape());
        self::assertSame($tensor->toArray(), $restored->toArray());
        self::assertSame($tensor->dtype(), $restored->dtype());
    }
}
