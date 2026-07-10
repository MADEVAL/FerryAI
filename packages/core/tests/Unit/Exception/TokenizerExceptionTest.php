<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\FerryAIException;
use FerryAI\Core\Exception\TokenizerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenizerException::class)]
final class TokenizerExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        self::assertInstanceOf(FerryAIException::class, new TokenizerException('broken tokenizer.json'));
    }

    public function testErrorCode(): void
    {
        self::assertSame('FERRY_AI_TOKENIZER', (new TokenizerException('broken tokenizer.json'))->errorCode());
    }

    public function testMessageMentionsReason(): void
    {
        self::assertStringContainsString(
            'broken tokenizer.json',
            (new TokenizerException('broken tokenizer.json'))->getMessage(),
        );
    }
}
