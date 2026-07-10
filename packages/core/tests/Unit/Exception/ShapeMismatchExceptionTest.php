<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\FerryAIException;
use FerryAI\Core\Exception\ShapeMismatchException;
use FerryAI\Core\ValueObjects\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShapeMismatchException::class)]
final class ShapeMismatchExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        $exception = new ShapeMismatchException(new Shape([1, 3]), new Shape([1, 4]));

        self::assertInstanceOf(FerryAIException::class, $exception);
    }

    public function testExposesExpectedAndActual(): void
    {
        $expected = new Shape([1, 3]);
        $actual = new Shape([1, 4]);
        $exception = new ShapeMismatchException($expected, $actual);

        self::assertSame($expected, $exception->expected());
        self::assertSame($actual, $exception->actual());
    }

    public function testErrorCode(): void
    {
        $exception = new ShapeMismatchException(new Shape([1, 3]), new Shape([1, 4]));

        self::assertSame('FERRY_AI_SHAPE_MISMATCH', $exception->errorCode());
    }

    public function testMessageMentionsBothShapes(): void
    {
        $exception = new ShapeMismatchException(new Shape([1, 3]), new Shape([1, 4]));

        self::assertStringContainsString('1,3', $exception->getMessage());
        self::assertStringContainsString('1,4', $exception->getMessage());
    }
}
