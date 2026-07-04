<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Core\Exception\FerryAIException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackendNotAvailableException::class)]
final class BackendNotAvailableExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        self::assertInstanceOf(FerryAIException::class, new BackendNotAvailableException('onnx'));
    }

    public function testExposesBackendType(): void
    {
        self::assertSame('onnx', (new BackendNotAvailableException('onnx'))->backendType());
    }

    public function testReasonDefaultsToNull(): void
    {
        self::assertNull((new BackendNotAvailableException('onnx'))->reason());
    }

    public function testExposesReasonWhenProvided(): void
    {
        $exception = new BackendNotAvailableException('onnx', 'shared library missing');

        self::assertSame('shared library missing', $exception->reason());
    }

    public function testErrorCode(): void
    {
        self::assertSame(
            'FERRY_AI_BACKEND_NOT_AVAILABLE',
            (new BackendNotAvailableException('onnx'))->errorCode(),
        );
    }

    public function testMessageMentionsBackendAndReason(): void
    {
        $message = (new BackendNotAvailableException('onnx', 'shared library missing'))->getMessage();

        self::assertStringContainsString('onnx', $message);
        self::assertStringContainsString('shared library missing', $message);
    }
}
