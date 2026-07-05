<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend\Tests\Unit;

use FerryAI\Core\Contracts\Tensor;
use FerryAI\CpuBackend\CpuNativeTensor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CpuNativeTensor::class)]
final class CpuNativeTensorTest extends TestCase
{
    public function testImplementsTensor(): void
    {
        $tensor = new CpuNativeTensor([1.0, 2.0, 3.0], [3]);

        self::assertInstanceOf(Tensor::class, $tensor);
    }

    public function testDeviceReturnsCpu(): void
    {
        $tensor = new CpuNativeTensor([1.0, 2.0], [2]);

        self::assertSame(\FerryAI\Core\Enums\Device::CPU, $tensor->device());
    }

    public function testToReturnsSelfForCpu(): void
    {
        $tensor = new CpuNativeTensor([1.0], [1]);

        $result = $tensor->to(\FerryAI\Core\Enums\Device::CPU);

        self::assertSame($tensor, $result);
    }

    public function testToThrowsForOtherDevice(): void
    {
        $tensor = new CpuNativeTensor([1.0], [1]);

        $this->expectException(\FerryAI\Core\Exception\DeviceNotAvailableException::class);
        $tensor->to(\FerryAI\Core\Enums\Device::CUDA);
    }

    public function testToArray(): void
    {
        $tensor = new CpuNativeTensor([1.0, 2.0, 3.0], [3]);

        self::assertSame([1.0, 2.0, 3.0], $tensor->toArray());
    }

    public function testToArrayWith2DShape(): void
    {
        $tensor = new CpuNativeTensor([1.0, 2.0, 3.0, 4.0], [2, 2]);

        self::assertSame([[1.0, 2.0], [3.0, 4.0]], $tensor->toArray());
    }

    public function testCount(): void
    {
        $tensor = new CpuNativeTensor([1.0, 2.0, 3.0], [3]);

        self::assertSame(3, $tensor->count());
    }
}
