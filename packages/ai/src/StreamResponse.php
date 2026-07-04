<?php

declare(strict_types=1);

namespace FerryAI;

use Psr\Http\Message\ResponseInterface;

/**
 * HTTP streaming response helper.
 *
 * Phase 1 stub: real SSE/NDJSON streaming requires the llama-backend (Phase 2) and is
 * finalised in Phase 4. Every method throws with an actionable message until then.
 */
final class StreamResponse
{
    private const string MESSAGE = 'StreamResponse requires the llama-backend package (Phase 2); '
        . 'streaming HTTP responses are finalised in Phase 4.';

    /**
     * @param iterable<int, string> $tokens the token stream to render (used from Phase 2 onwards)
     */
    public function __construct(iterable $tokens = [])
    {
        // Phase 1 stub: the token stream is accepted for API shape but not yet consumed.
        unset($tokens);
    }

    /**
     * @param iterable<int, string> $tokens
     */
    public static function create(iterable $tokens = []): ResponseInterface
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function toSse(): string
    {
        throw new \RuntimeException(self::MESSAGE);
    }

    public function toNdjson(): string
    {
        throw new \RuntimeException(self::MESSAGE);
    }
}
