<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\DeviceNotAvailableException;
use FerryAI\Core\Exception\FerryAIException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviceNotAvailableException::class)]
final class DeviceNotAvailableExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        $exception = new DeviceNotAvailableException(Device::CUDA);

        self::assertInstanceOf(FerryAIException::class, $exception);
    }

    public function testRequestedDeviceIsExposed(): void
    {
        $exception = new DeviceNotAvailableException(Device::CUDA);

        self::assertSame(Device::CUDA, $exception->requestedDevice());
    }

    public function testErrorCode(): void
    {
        $exception = new DeviceNotAvailableException(Device::AUTO);

        self::assertSame('FERRY_AI_DEVICE_NOT_AVAILABLE', $exception->errorCode());
    }

    public function testMessageMentionsRequestedDevice(): void
    {
        $exception = new DeviceNotAvailableException(Device::CUDA);

        self::assertStringContainsString('cuda', $exception->getMessage());
    }
}
