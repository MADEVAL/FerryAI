<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\Tokenizer;

use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Enums\TokenizerType;
use FerryAI\Tokenizer\TokenizerFactory;
use FerryAI\Tokenizer\TokenizerLoader;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tokenizer tests with a real tokenizer.json file.
 * Does NOT require ONNX Runtime — uses pure-PHP tokenizer fallbacks.
 */
#[Group('integration')]
#[CoversNothing]
final class TokenizerIntegrationTest extends TestCase
{
    private string $tokenizerPath = '';

    protected function setUp(): void
    {
        $this->tokenizerPath = getenv('FERRY_AI_TOKENIZER_JSON_PATH')
            ?: 'D:\\FerryAI\\all-MiniLM-L6-v2-onnx\\tokenizer.json';

        if (!\is_file($this->tokenizerPath)) {
            self::markTestSkipped('tokenizer.json not found: ' . $this->tokenizerPath);
        }
    }

    private function createTokenizer(): Tokenizer
    {
        $factory = new TokenizerFactory(new TokenizerLoader());

        return $factory->create($this->tokenizerPath);
    }

    public function testDetectsKnownTypeFromConfig(): void
    {
        $loader = new TokenizerLoader();
        $config = $loader->loadFromFile($this->tokenizerPath);
        $type = $loader->detectType($config);

        self::assertContains($type, [TokenizerType::BPE, TokenizerType::WordPiece]);
    }

    public function testVocabSizeIsPositive(): void
    {
        $tokenizer = $this->createTokenizer();

        self::assertGreaterThan(0, $tokenizer->vocabSize());
    }

    public function testSpecialTokensPresent(): void
    {
        $tokenizer = $this->createTokenizer();

        self::assertGreaterThan(0, \count($tokenizer->specialTokens()));
    }

    public function testSpecialTokenIdsAreNullForNonexistentToken(): void
    {
        $tokenizer = $this->createTokenizer();

        self::assertNull($tokenizer->specialTokenId('nonexistent_token_name_xyz'));
    }

    public function testEncodeDecodeRoundtripPreservesContent(): void
    {
        $tokenizer = $this->createTokenizer();
        $text = 'hello world';

        $ids = $tokenizer->encode($text);
        $decoded = $tokenizer->decode($ids);

        self::assertStringContainsStringIgnoringCase('hello', $decoded);
        self::assertStringContainsStringIgnoringCase('world', $decoded);
    }

    public function testEncodeWithoutSpecialTokensShorterThanWith(): void
    {
        $tokenizer = $this->createTokenizer();
        $text = 'test';

        $withSpecial = $tokenizer->encode($text, true);
        $withoutSpecial = $tokenizer->encode($text, false);

        self::assertGreaterThan(\count($withoutSpecial), \count($withSpecial));
    }

    public function testEncodeBatchProducesCorrectShapes(): void
    {
        $tokenizer = $this->createTokenizer();
        $texts = ['hello', 'hello world', 'a'];

        $result = $tokenizer->encodeBatch($texts);

        self::assertArrayHasKey('input_ids', $result);
        self::assertArrayHasKey('attention_mask', $result);
        self::assertCount(3, $result['input_ids']);
        self::assertCount(3, $result['attention_mask']);

        $maxLen = \count($result['input_ids'][0]);

        foreach ($result['input_ids'] as $ids) {
            self::assertCount($maxLen, $ids);
        }

        foreach ($result['attention_mask'] as $mask) {
            self::assertCount($maxLen, $mask);
        }
    }

    public function testAttentionMaskCorrectForPaddedBatch(): void
    {
        $tokenizer = $this->createTokenizer();
        $texts = ['hello', 'a'];

        $result = $tokenizer->encodeBatch($texts, true);
        $maxLen = \count($result['input_ids'][0]);

        foreach ($result['attention_mask'] as $i => $mask) {
            $realTokens = \count($result['input_ids'][$i]);
            $ones = \count(\array_filter($mask, static fn(int $v): bool => $v === 1));

            self::assertSame($realTokens, $ones);
            self::assertSame($maxLen, $realTokens);
        }
    }

    public function testAttentionMaskDoesNotPadWithFalseFlag(): void
    {
        $tokenizer = $this->createTokenizer();
        $texts = ['hello', 'hello world'];

        $result = $tokenizer->encodeBatch($texts, false);

        foreach ($result['attention_mask'] as $mask) {
            self::assertCount(\count($mask), \array_filter($mask, static fn(int $v): bool => $v === 1));
        }
    }

    public function testCountTokensIncreaseWithLongerText(): void
    {
        $tokenizer = $this->createTokenizer();

        $short = $tokenizer->countTokens('a');
        $long = $tokenizer->countTokens('the quick brown fox jumps over the lazy dog');

        self::assertGreaterThan($short, $long);
    }

    public function testChunkSplitsLongTextIntoMultipleChunks(): void
    {
        $tokenizer = $this->createTokenizer();
        $text = str_repeat('the quick brown fox jumps over the lazy dog. ', 50);

        $chunks = $tokenizer->chunk($text, 32, 8);

        self::assertGreaterThan(1, \count($chunks));

        foreach ($chunks as $chunk) {
            self::assertNotEmpty($chunk);
        }
    }

    public function testChunkReturnsEmptyForEmptyInput(): void
    {
        $tokenizer = $this->createTokenizer();

        self::assertSame([], $tokenizer->chunk(''));
    }

    public function testTokenizerReturnsConsistentType(): void
    {
        $tokenizer = $this->createTokenizer();
        $type = $tokenizer->type();

        self::assertContains($type, [TokenizerType::BPE, TokenizerType::WordPiece]);
    }
}
