<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Tests\Unit;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\Exception\DeviceNotAvailableException;
use FerryAI\Core\ValueObjects\Shape;
use FerryAI\OnnxBackend\OnnxTensor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnnxTensor::class)]
final class OnnxTensorTest extends TestCase
{
    private function tensor(): OnnxTensor
    {
        return new OnnxTensor([[1.0, 2.0, 3.0]], new Shape([1, 3]), DType::Float32);
    }

    public function testShapeDtypeDevice(): void
    {
        $tensor = $this->tensor();

        self::assertEquals(new Shape([1, 3]), $tensor->shape());
        self::assertSame(DType::Float32, $tensor->dtype());
        self::assertSame(Device::CPU, $tensor->device());
    }

    public function testToArrayAndData(): void
    {
        self::assertSame([[1.0, 2.0, 3.0]], $this->tensor()->toArray());
        self::assertSame([[1.0, 2.0, 3.0]], $this->tensor()->data());
    }

    public function testCountIsElementCount(): void
    {
        self::assertCount(3, $this->tensor());
    }

    public function testToSameDeviceReturnsSelf(): void
    {
        $tensor = $this->tensor();

        self::assertSame($tensor, $tensor->to(Device::CPU));
    }

    public function testToUnsupportedDeviceThrows(): void
    {
        $this->expectException(DeviceNotAvailableException::class);

        $this->tensor()->to(Device::CUDA);
    }

    public function testArithmeticIsRejected(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $this->tensor()->add($this->tensor());
    }

    public function testTransposeIsRejected(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $this->tensor()->transpose();
    }

    public function testArrayAccessAndJsonSerialize(): void
    {
        $tensor = $this->tensor();

        self::assertTrue(isset($tensor[0]));
        self::assertSame([1.0, 2.0, 3.0], $tensor[0]);
        self::assertSame($tensor->toArray(), $tensor->jsonSerialize());
    }

    public function testSerializeRoundTrip(): void
    {
        $restored = unserialize(serialize($this->tensor()));

        self::assertInstanceOf(OnnxTensor::class, $restored);
        self::assertSame([[1.0, 2.0, 3.0]], $restored->toArray());
        self::assertSame(DType::Float32, $restored->dtype());
        self::assertSame(Device::CPU, $restored->device());
    }
}
