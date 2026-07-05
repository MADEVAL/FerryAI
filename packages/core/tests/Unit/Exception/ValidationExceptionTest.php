<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\FerryAIException;
use FerryAI\Core\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationException::class)]
final class ValidationExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        self::assertInstanceOf(FerryAIException::class, new ValidationException('bad value'));
    }

    public function testErrorCode(): void
    {
        self::assertSame('FERRY_AI_VALIDATION', (new ValidationException('bad value'))->errorCode());
    }

    public function testMessageIsPreserved(): void
    {
        self::assertSame('bad value', (new ValidationException('bad value'))->getMessage());
    }
}
