<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\FerryAIException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FerryAIException::class)]
final class FerryAIExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $exception = new FerryAIException('boom');

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testErrorCodeReturnsMachineReadableString(): void
    {
        $exception = new FerryAIException('boom');

        self::assertSame('FERRY_AI_ERROR', $exception->errorCode());
    }

    public function testMessageIsPreserved(): void
    {
        $exception = new FerryAIException('boom');

        self::assertSame('boom', $exception->getMessage());
    }
}
