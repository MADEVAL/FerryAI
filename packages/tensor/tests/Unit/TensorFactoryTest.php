<?php

declare(strict_types=1);

namespace FerryAI\Tensor\Tests\Unit;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\DeviceNotAvailableException;
use FerryAI\Core\ValueObjects\Shape;
use FerryAI\Tensor\TensorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TensorFactory::class)]
final class TensorFactoryTest extends TestCase
{
    private TensorFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new TensorFactory();
    }

    public function testFromArrayInfersShape(): void
    {
        self::assertEquals(new Shape([2, 2]), $this->factory->fromArray([[1, 2], [3, 4]])->shape());
    }

    public function testZeros(): void
    {
        $tensor = $this->factory->zeros(new Shape([3, 3]));

        self::assertEquals(new Shape([3, 3]), $tensor->shape());
        self::assertCount(9, $tensor);
        self::assertSame(array_fill(0, 9, 0.0), $tensor->data());
    }

    public function testOnes(): void
    {
        $tensor = $this->factory->ones(new Shape([2, 2]));

        self::assertSame([[1.0, 1.0], [1.0, 1.0]], $tensor->toArray());
    }

    public function testRandomValuesAreWithinUnitInterval(): void
    {
        $tensor = $this->factory->random(new Shape([2, 3]));

        self::assertEquals(new Shape([2, 3]), $tensor->shape());
        self::assertCount(6, $tensor);

        /** @var array<int, float> $values */
        $values = $tensor->data();

        foreach ($values as $value) {
            self::assertGreaterThanOrEqual(0.0, $value);
            self::assertLessThan(1.0, $value);
        }
    }

    public function testFromArrayRejectsUnsupportedDevice(): void
    {
        $this->expectException(DeviceNotAvailableException::class);

        $this->factory->fromArray([1, 2, 3], null, Device::CUDA);
    }
}
