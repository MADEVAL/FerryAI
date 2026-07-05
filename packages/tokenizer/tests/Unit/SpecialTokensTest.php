<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer\Tests\Unit;

use FerryAI\Tokenizer\SpecialTokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpecialTokens::class)]
final class SpecialTokensTest extends TestCase
{
    public function testExtractsRoleTokensFromConfig(): void
    {
        $config = [
            'added_tokens' => [
                ['content' => '<s>', 'id' => 0],
                ['content' => '</s>', 'id' => 1],
                ['content' => '<pad>', 'id' => 2],
                ['content' => '<unk>', 'id' => 3],
            ],
        ];

        $tokens = SpecialTokens::extract($config);

        self::assertSame(0, $tokens['bos']);
        self::assertSame(1, $tokens['eos']);
        self::assertSame(2, $tokens['pad']);
        self::assertSame(3, $tokens['unk']);
    }

    public function testEmptyConfigReturnsEmpty(): void
    {
        $tokens = SpecialTokens::extract([]);

        self::assertSame([], $tokens);
    }

    public function testConfigWithoutAddedTokensReturnsEmpty(): void
    {
        $tokens = SpecialTokens::extract(['model' => []]);

        self::assertSame([], $tokens);
    }

    public function testSkipsTokensWithoutId(): void
    {
        $config = [
            'added_tokens' => [
                ['content' => '<s>'],
            ],
        ];

        $tokens = SpecialTokens::extract($config);

        self::assertArrayNotHasKey('bos', $tokens);
    }
}
