<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\FerryAIException;
use FerryAI\Core\Exception\InvalidStateException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidStateException::class)]
final class InvalidStateExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        self::assertInstanceOf(FerryAIException::class, new InvalidStateException('not configured'));
    }

    public function testErrorCode(): void
    {
        self::assertSame('FERRY_AI_INVALID_STATE', (new InvalidStateException('not configured'))->errorCode());
    }

    public function testMessageIsPreserved(): void
    {
        self::assertSame('not configured', (new InvalidStateException('not configured'))->getMessage());
    }
}
