<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\FerryAIException;
use FerryAI\Core\Exception\ModelLoadException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelLoadException::class)]
final class ModelLoadExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        self::assertInstanceOf(FerryAIException::class, new ModelLoadException('m.onnx', 'corrupt'));
    }

    public function testExposesPathAndReason(): void
    {
        $exception = new ModelLoadException('m.onnx', 'corrupt file');

        self::assertSame('m.onnx', $exception->path());
        self::assertSame('corrupt file', $exception->reason());
    }

    public function testErrorCode(): void
    {
        self::assertSame('FERRY_AI_MODEL_LOAD', (new ModelLoadException('m.onnx', 'corrupt'))->errorCode());
    }

    public function testMessageMentionsPathAndReason(): void
    {
        $message = (new ModelLoadException('m.onnx', 'corrupt file'))->getMessage();

        self::assertStringContainsString('m.onnx', $message);
        self::assertStringContainsString('corrupt file', $message);
    }
}
