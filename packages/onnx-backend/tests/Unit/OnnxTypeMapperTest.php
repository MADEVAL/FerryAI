<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Tests\Unit;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\OnnxBackend\OnnxTypeMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnnxTypeMapper::class)]
final class OnnxTypeMapperTest extends TestCase
{
    public function testToDTypeMapsFloat(): void
    {
        self::assertSame(DType::Float32, OnnxTypeMapper::toDType('float'));
        self::assertSame(DType::Float32, OnnxTypeMapper::toDType('tensor(float)'));
    }

    public function testToDTypeMapsInt64(): void
    {
        self::assertSame(DType::Int64, OnnxTypeMapper::toDType('int64'));
    }

    public function testToDTypeMapsString(): void
    {
        self::assertSame(DType::String, OnnxTypeMapper::toDType('string'));
    }

    public function testToDTypeMapsFloat16(): void
    {
        self::assertSame(DType::Float16, OnnxTypeMapper::toDType('float16'));
    }

    public function testUnknownTypeReturnsInt32(): void
    {
        self::assertSame(DType::Int32, OnnxTypeMapper::toDType('unknown_type'));
    }

    public function testProviderToDeviceMapsCpu(): void
    {
        self::assertSame(Device::CPU, OnnxTypeMapper::providerToDevice('CPUExecutionProvider'));
    }

    public function testProviderToDeviceMapsCuda(): void
    {
        self::assertSame(Device::CUDA, OnnxTypeMapper::providerToDevice('CUDAExecutionProvider'));
    }

    public function testUnknownProviderReturnsNull(): void
    {
        self::assertNull(OnnxTypeMapper::providerToDevice('UnknownProvider'));
    }

    public function testProviderNamesForDeviceReturnsCpuForCpu(): void
    {
        $providers = OnnxTypeMapper::providerNamesForDevice(Device::CPU);

        self::assertSame(['CPUExecutionProvider'], $providers);
    }

    public function testProviderNamesForDeviceReturnsCudaForCuda(): void
    {
        $providers = OnnxTypeMapper::providerNamesForDevice(Device::CUDA);

        self::assertContains('CUDAExecutionProvider', $providers);
        self::assertContains('CPUExecutionProvider', $providers);
    }
}
