<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer\Tests\Unit;

use FerryAI\Core\Enums\TokenizerType;
use FerryAI\Tokenizer\PureWordPieceTokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PureWordPieceTokenizer::class)]
final class PureWordPieceTokenizerTest extends TestCase
{
    private function tokenizer(): PureWordPieceTokenizer
    {
        $vocab = [
            'Hello' => 0, 'world' => 1, 'wor' => 2, '##ld' => 3, '##s' => 4,
            '[UNK]' => 5, '[CLS]' => 6, '[SEP]' => 7,
        ];

        return new PureWordPieceTokenizer($vocab, ['unk' => 5, 'cls' => 6, 'sep' => 7]);
    }

    public function testType(): void
    {
        self::assertSame(TokenizerType::WordPiece, $this->tokenizer()->type());
    }

    public function testEncodeKnownWords(): void
    {
        self::assertSame([0, 1], $this->tokenizer()->encode('Hello world', false));
    }

    public function testContinuationSubwords(): void
    {
        self::assertSame([2, 4], $this->tokenizer()->encode('wors', false));
    }

    public function testRoundTrip(): void
    {
        $tokenizer = $this->tokenizer();

        self::assertSame('Hello world', $tokenizer->decode($tokenizer->encode('Hello world', false)));
    }

    public function testUnknownWord(): void
    {
        self::assertSame([5], $this->tokenizer()->encode('xyz', false));
    }
}
