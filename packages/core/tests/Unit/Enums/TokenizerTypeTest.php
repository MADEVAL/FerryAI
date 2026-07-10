<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Enums;

use FerryAI\Core\Enums\TokenizerType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenizerType::class)]
final class TokenizerTypeTest extends TestCase
{
    public function testAllFourCasesAreDefined(): void
    {
        self::assertCount(4, TokenizerType::cases());
    }

    public function testBackingValues(): void
    {
        self::assertSame('bpe', TokenizerType::BPE->value);
        self::assertSame('wordpiece', TokenizerType::WordPiece->value);
        self::assertSame('sentencepiece', TokenizerType::SentencePiece->value);
        self::assertSame('unigram', TokenizerType::Unigram->value);
    }
}
