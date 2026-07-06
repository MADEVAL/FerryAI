<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\Exception\InvalidStateException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class StreamResponse
{
    /** @var iterable<int, string> */
    private iterable $tokens;

    /**
     * @param iterable<int, string> $tokens
     */
    public function __construct(iterable $tokens = [])
    {
        $this->tokens = $tokens;
    }

    /**
     * Builds a Server-Sent Events PSR-7 response from the tokens.
     *
     * Auto-detects an installed PSR-17 factory (nyholm/psr7 or guzzlehttp/psr7).
     *
     * @param iterable<int, string> $tokens
     */
    public static function create(iterable $tokens): ResponseInterface
    {
        $factory = self::psr17Factory();

        if ($factory === null) {
            throw new InvalidStateException(
                'StreamResponse::create() requires a PSR-17 factory (install nyholm/psr7 or '
                . 'guzzlehttp/psr7). Use toSse() or toNdjson() for raw string output.',
            );
        }

        $body = (new self($tokens))->toSse();

        return $factory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withBody($factory->createStream($body));
    }

    /**
     * @return (ResponseFactoryInterface&StreamFactoryInterface)|null
     */
    private static function psr17Factory(): (ResponseFactoryInterface&StreamFactoryInterface)|null
    {
        foreach (['Nyholm\Psr7\Factory\Psr17Factory', 'GuzzleHttp\Psr7\HttpFactory'] as $candidate) {
            if (!\class_exists($candidate)) {
                continue;
            }

            $factory = new $candidate();

            /** @phpstan-ignore instanceof.alwaysTrue, instanceof.alwaysTrue, booleanAnd.alwaysTrue */
            if ($factory instanceof ResponseFactoryInterface && $factory instanceof StreamFactoryInterface) {
                return $factory;
            }
        }

        return null;
    }

    public function toSse(): string
    {
        $lines = [];

        foreach ($this->tokens as $token) {
            if (\str_contains($token, "\n")) {
                foreach (\explode("\n", $token) as $tokenLine) {
                    $lines[] = 'data: ' . $tokenLine;
                }
            } else {
                $lines[] = 'data: ' . $token;
            }
        }

        return \implode("\n\n", $lines) . "\n\n";
    }

    public function toNdjson(): string
    {
        $lines = [];

        foreach ($this->tokens as $token) {
            $lines[] = \json_encode(['token' => $token], JSON_UNESCAPED_UNICODE);
        }

        return \implode("\n", $lines) . "\n";
    }
}
