<?php

declare(strict_types=1);

namespace FerryAI\Tensor\Tests\Unit;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Tensor\ArrayTensor;
use FerryAI\Tensor\BackedTensor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackedTensor::class)]
final class BackedTensorTest extends TestCase
{
    private function backend(): Backend
    {
        return new class implements Backend {
            public function availableDevices(): array
            {
                return [Device::CPU];
            }

            public function load(string $source, ?Device $device = null): Model
            {
                throw new \RuntimeException('not used');
            }

            public function version(): string
            {
                return 'test';
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };
    }

    private function tensor(): BackedTensor
    {
        return new BackedTensor(ArrayTensor::fromNested([[1, 2], [3, 4]], DType::Int32), $this->backend());
    }

    public function testDelegatesShapeDtypeDevice(): void
    {
        $tensor = $this->tensor();

        self::assertSame([2, 2], $tensor->shape()->toArray());
        self::assertSame(DType::Int32, $tensor->dtype());
        self::assertSame(Device::CPU, $tensor->device());
    }

    public function testDelegatesToArrayAndData(): void
    {
        self::assertSame([[1, 2], [3, 4]], $this->tensor()->toArray());
        self::assertSame([1, 2, 3, 4], $this->tensor()->data());
    }

    public function testDelegatesCountAndArrayAccess(): void
    {
        $tensor = $this->tensor();

        self::assertCount(4, $tensor);
        self::assertSame(1, $tensor[0]);
        self::assertTrue(isset($tensor[3]));
    }

    public function testToReturnsBackedTensor(): void
    {
        $moved = $this->tensor()->to(Device::CPU);

        self::assertInstanceOf(BackedTensor::class, $moved);
        self::assertSame(Device::CPU, $moved->device());
    }

    public function testArithmeticIsNotImplementedInPhase1(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->tensor()->add($this->tensor());
    }

    public function testMatmulIsNotImplementedInPhase1(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->tensor()->matmul($this->tensor());
    }
}
