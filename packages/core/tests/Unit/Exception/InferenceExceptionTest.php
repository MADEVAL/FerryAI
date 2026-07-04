<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\FerryAIException;
use FerryAI\Core\Exception\InferenceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InferenceException::class)]
final class InferenceExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        self::assertInstanceOf(FerryAIException::class, new InferenceException('out of memory'));
    }

    public function testErrorCode(): void
    {
        self::assertSame('FERRY_AI_INFERENCE', (new InferenceException('out of memory'))->errorCode());
    }

    public function testMessageIsPreserved(): void
    {
        self::assertSame('out of memory', (new InferenceException('out of memory'))->getMessage());
    }
}
