<?php

declare(strict_types=1);

namespace FerryAI;

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
     * @param iterable<int, string> $tokens
     */
    public static function create(iterable $tokens): \Psr\Http\Message\ResponseInterface
    {
        throw new \RuntimeException(
            'StreamResponse::create() requires psr/http-message implementation (e.g. nyholm/psr7, guzzlehttp/psr7). '
            . 'Use toSse() or toNdjson() for raw string output.',
        );
    }

    public function toSse(): string
    {
        $lines = [];

        foreach ($this->tokens as $token) {
            $lines[] = 'data: ' . $token;
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
