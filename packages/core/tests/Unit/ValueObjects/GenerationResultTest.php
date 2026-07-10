<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\ValueObjects;

use FerryAI\Core\ValueObjects\GenerationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenerationResult::class)]
final class GenerationResultTest extends TestCase
{
    public function testFieldsAreReadable(): void
    {
        $result = new GenerationResult(
            text: 'hello world',
            tokensGenerated: 2,
            tokensPrompt: 5,
            tokensTotal: 7,
            durationMs: 12.5,
        );

        self::assertSame('hello world', $result->text);
        self::assertSame(2, $result->tokensGenerated);
        self::assertSame(7, $result->tokensTotal);
        self::assertSame(12.5, $result->durationMs);
        self::assertNull($result->logprobs);
    }
}
