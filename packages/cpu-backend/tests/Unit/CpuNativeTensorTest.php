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

    public function testAddElementwise(): void
    {
        $a = new CpuNativeTensor([1.0, 2.0, 3.0], [3]);
        $b = new CpuNativeTensor([4.0, 5.0, 6.0], [3]);

        self::assertSame([5.0, 7.0, 9.0], $a->add($b)->toArray());
    }

    public function testSubElementwise(): void
    {
        $a = new CpuNativeTensor([4.0, 5.0, 6.0], [3]);
        $b = new CpuNativeTensor([1.0, 2.0, 3.0], [3]);

        self::assertSame([3.0, 3.0, 3.0], $a->sub($b)->toArray());
    }

    public function testMulElementwise(): void
    {
        $a = new CpuNativeTensor([1.0, 2.0, 3.0], [3]);
        $b = new CpuNativeTensor([4.0, 5.0, 6.0], [3]);

        self::assertSame([4.0, 10.0, 18.0], $a->mul($b)->toArray());
    }

    public function testElementwiseThrowsOnShapeMismatch(): void
    {
        $a = new CpuNativeTensor([1.0, 2.0, 3.0], [3]);
        $b = new CpuNativeTensor([1.0, 2.0], [2]);

        $this->expectException(\FerryAI\Core\Exception\ShapeMismatchException::class);
        $a->add($b);
    }

    public function testMatmul(): void
    {
        $a = new CpuNativeTensor([1.0, 2.0, 3.0, 4.0], [2, 2]);
        $b = new CpuNativeTensor([5.0, 6.0, 7.0, 8.0], [2, 2]);

        self::assertSame([[19.0, 22.0], [43.0, 50.0]], $a->matmul($b)->toArray());
    }

    public function testMatmulThrowsOnInnerDimMismatch(): void
    {
        $a = new CpuNativeTensor([1.0, 2.0, 3.0, 4.0], [2, 2]);
        $b = new CpuNativeTensor([1.0, 2.0, 3.0], [3, 1]);

        $this->expectException(\FerryAI\Core\Exception\ShapeMismatchException::class);
        $a->matmul($b);
    }

    public function testTranspose2D(): void
    {
        $a = new CpuNativeTensor([1.0, 2.0, 3.0, 4.0, 5.0, 6.0], [2, 3]);

        $t = $a->transpose();

        self::assertSame([3, 2], $t->shape()->toArray());
        self::assertSame([[1.0, 4.0], [2.0, 5.0], [3.0, 6.0]], $t->toArray());
    }

    public function testReshape(): void
    {
        $a = new CpuNativeTensor([1.0, 2.0, 3.0, 4.0], [4]);

        $r = $a->reshape(new \FerryAI\Core\ValueObjects\Shape([2, 2]));

        self::assertSame([[1.0, 2.0], [3.0, 4.0]], $r->toArray());
    }

    public function testReshapeThrowsOnSizeMismatch(): void
    {
        $a = new CpuNativeTensor([1.0, 2.0, 3.0, 4.0], [4]);

        $this->expectException(\FerryAI\Core\Exception\ShapeMismatchException::class);
        $a->reshape(new \FerryAI\Core\ValueObjects\Shape([3]));
    }

    public function testSlice(): void
    {
        $a = new CpuNativeTensor([1.0, 2.0, 3.0, 4.0], [2, 2]);

        $s = $a->slice([[0, 1]]);

        self::assertSame([[1.0, 2.0]], $s->toArray());
    }
}
