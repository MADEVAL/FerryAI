<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\StreamResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamResponse::class)]
final class StreamResponseTest extends TestCase
{
    public function testCreateThrowsWithoutPsr7Implementation(): void
    {
        $this->expectException(\RuntimeException::class);

        StreamResponse::create(['hello', 'world']);
    }

    public function testToSseFormatsTokens(): void
    {
        $tokens = ['Hello', 'World'];
        $response = new StreamResponse($tokens);

        $sse = $response->toSse();

        self::assertStringContainsString('data: Hello', $sse);
        self::assertStringContainsString('data: World', $sse);
    }

    public function testToNdjsonFormatsTokens(): void
    {
        $tokens = ['Hello', 'World'];
        $response = new StreamResponse($tokens);

        $ndjson = $response->toNdjson();

        self::assertStringContainsString('"token":"Hello"', $ndjson);
        self::assertStringContainsString('"token":"World"', $ndjson);
    }

    public function testEmptyTokensProducesOutput(): void
    {
        $response = new StreamResponse([]);

        self::assertSame("\n\n", $response->toSse());
        self::assertSame("\n", $response->toNdjson());
    }
}
