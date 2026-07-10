<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Tests\Unit;

use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Enums\TokenizerType;
use FerryAI\Pipeline\Stages\TokenizeStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenizeStage::class)]
final class TokenizeStageTest extends TestCase
{
    public function testName(): void
    {
        $tokenizer = new StubTokenizerForPipelineStage();
        $stage = new TokenizeStage($tokenizer);
        self::assertSame('tokenize', $stage->name());
    }

    public function testProcessReturnsTokenIds(): void
    {
        $tokenizer = new StubTokenizerForPipelineStage();
        $stage = new TokenizeStage($tokenizer);
        $result = $stage->process('hello');
        self::assertSame([101, 2023, 102], $result);
    }
}

final class StubTokenizerForPipelineStage implements Tokenizer
{
    public function encode(string $text, bool $addSpecialTokens = true): array
    {
        return [101, 2023, 102];
    }
    public function decode(array $ids): string
    {
        return '';
    }
    public function encodeBatch(array $texts, bool $padToMaxLength = true): array
    {
        return ['input_ids' => [[101, 2023, 102]], 'attention_mask' => [[1, 1, 1]]];
    }
    public function vocabSize(): int
    {
        return 30000;
    }
    public function type(): TokenizerType
    {
        return TokenizerType::WordPiece;
    }
    public function specialTokenId(string $tokenName): ?int
    {
        return null;
    }
    public function specialTokens(): array
    {
        return [];
    }
    public function countTokens(string $text): int
    {
        return 3;
    }
    public function chunk(string $text, int $maxTokens = 512, int $overlap = 64): array
    {
        return [$text];
    }
}
