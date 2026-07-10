<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer\Tests\Unit;

use FerryAI\Core\Enums\TokenizerType;
use FerryAI\Tokenizer\PureBpeTokenizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PureBpeTokenizer::class)]
final class PureBpeTokenizerTest extends TestCase
{
    /**
     * @param list<string>       $merges
     * @param array<string, int> $special
     */
    private function tokenizer(array $merges = [], array $special = []): PureBpeTokenizer
    {
        $vocab = [
            'H' => 0, 'e' => 1, 'l' => 2, 'o' => 3, '</w>' => 4,
            'w' => 5, 'r' => 6, 'd' => 7, 'll' => 8,
            '<s>' => 9, '</s>' => 10, '<unk>' => 11,
        ];

        return new PureBpeTokenizer($vocab, $merges, $special);
    }

    public function testType(): void
    {
        self::assertSame(TokenizerType::BPE, $this->tokenizer()->type());
    }

    public function testVocabSize(): void
    {
        self::assertSame(12, $this->tokenizer()->vocabSize());
    }

    public function testEncodeReturnsIntIds(): void
    {
        $ids = $this->tokenizer()->encode('Hello world', false);

        self::assertNotEmpty($ids);
        self::assertContainsOnlyInt($ids);
    }

    public function testRoundTrip(): void
    {
        $tokenizer = $this->tokenizer();

        self::assertSame('Hello world', $tokenizer->decode($tokenizer->encode('Hello world', false)));
    }

    public function testMergeReducesTokenCount(): void
    {
        $withoutMerge = $this->tokenizer();
        $withMerge = $this->tokenizer(['l l']);

        self::assertLessThan(
            \count($withoutMerge->encode('Hello', false)),
            \count($withMerge->encode('Hello', false)),
        );
    }

    public function testSpecialTokens(): void
    {
        $tokenizer = $this->tokenizer(special: ['bos' => 9, 'eos' => 10, 'unk' => 11]);

        self::assertSame(9, $tokenizer->specialTokenId('bos'));
        self::assertNull($tokenizer->specialTokenId('nope'));

        $ids = $tokenizer->encode('Hello', true);

        self::assertSame(9, $ids[0]);
        self::assertSame(10, $ids[array_key_last($ids)]);
    }

    public function testDecodeStripsFusedEndOfWordMarker(): void
    {
        $vocab = ['h' => 0, 'i' => 1, 'hi</w>' => 2];
        $merges = ['h i', 'hi </w>'];
        $tokenizer = new PureBpeTokenizer($vocab, $merges);

        $decoded = $tokenizer->decode($tokenizer->encode('hi', false));

        self::assertStringNotContainsString('</w>', $decoded);
        self::assertSame('hi', $decoded);
    }

    public function testCountTokens(): void
    {
        $tokenizer = $this->tokenizer();

        self::assertSame(\count($tokenizer->encode('Hello world', false)), $tokenizer->countTokens('Hello world'));
    }

    public function testChunkWithOverlap(): void
    {
        $chunks = $this->tokenizer()->chunk('Hello world Hello world Hello world', 4, 1);

        self::assertGreaterThan(1, \count($chunks));
        self::assertContainsOnlyString($chunks);
    }
}
