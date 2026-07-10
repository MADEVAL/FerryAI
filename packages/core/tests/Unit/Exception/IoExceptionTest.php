<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\FerryAIException;
use FerryAI\Core\Exception\IoException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IoException::class)]
final class IoExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        self::assertInstanceOf(FerryAIException::class, new IoException('cannot open file'));
    }

    public function testErrorCode(): void
    {
        self::assertSame('FERRY_AI_IO', (new IoException('cannot open file'))->errorCode());
    }

    public function testMessageIsPreserved(): void
    {
        self::assertSame('cannot open file', (new IoException('cannot open file'))->getMessage());
    }
}
