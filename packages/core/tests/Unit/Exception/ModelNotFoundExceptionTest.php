<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\FerryAIException;
use FerryAI\Core\Exception\ModelNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelNotFoundException::class)]
final class ModelNotFoundExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        self::assertInstanceOf(FerryAIException::class, new ModelNotFoundException('bert.onnx'));
    }

    public function testExposesSource(): void
    {
        self::assertSame('bert.onnx', (new ModelNotFoundException('bert.onnx'))->source());
    }

    public function testErrorCode(): void
    {
        self::assertSame('FERRY_AI_MODEL_NOT_FOUND', (new ModelNotFoundException('bert.onnx'))->errorCode());
    }

    public function testMessageMentionsSource(): void
    {
        self::assertStringContainsString('bert.onnx', (new ModelNotFoundException('bert.onnx'))->getMessage());
    }
}
