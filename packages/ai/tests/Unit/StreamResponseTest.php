<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\StreamResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamResponse::class)]
final class StreamResponseTest extends TestCase
{
    public function testCreateReturnsPsr7ResponseWhenFactoryAvailable(): void
    {
        if (!\class_exists('Nyholm\Psr7\Factory\Psr17Factory') && !\class_exists('GuzzleHttp\Psr7\HttpFactory')) {
            self::markTestSkipped('No PSR-17 factory (nyholm/psr7 or guzzlehttp/psr7) installed.');
        }

        $response = StreamResponse::create(['Hello', 'World']);

        self::assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('data: Hello', (string) $response->getBody());
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

    public function testToSseHandlesTokensWithNewlines(): void
    {
        $tokens = ["line1\nline2", 'plain'];
        $response = new StreamResponse($tokens);

        $sse = $response->toSse();

        self::assertStringContainsString(
            'data: line1',
            $sse,
            'SSE must still contain "data: line1" prefix for the first line.',
        );
        self::assertStringNotContainsString(
            "\nline2\n",
            $sse,
            'Raw newline in token must not appear as un-prefixed SSE line.',
        );
    }
}
