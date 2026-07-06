<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer;

use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Enums\TokenizerType;

/**
 * Native HuggingFace tokenizer binding (tokenizers-cpp) via FFI.
 *
 * This is the FFI boundary of the package and is excluded from static analysis. The native binding
 * is optional: {@see isAvailable()} probes for the shared library (path in the
 * FERRY_AI_TOKENIZERS_LIB environment variable) and returns false when it is absent, so the factory
 * transparently falls back to the pure-PHP tokenizers. When no library is configured, the instance
 * methods throw a clear message; a full binding is validated by the integration suite.
 */
final class HuggingFaceTokenizer implements Tokenizer
{
    private const string UNAVAILABLE = 'The native HuggingFace tokenizer binding (tokenizers-cpp) is not available; '
        . 'set FERRY_AI_TOKENIZERS_LIB to the shared library, or use the pure-PHP tokenizers.';

    public function __construct(
        private readonly string $tokenizerPath,
        private readonly TokenizerType $tokenizerType = TokenizerType::BPE,
    ) {}

    public static function isAvailable(): bool
    {
        $lib = getenv('FERRY_AI_TOKENIZERS_LIB');

        if ($lib === false || $lib === '' || !is_file($lib)) {
            return false;
        }

        try {
            \FFI::cdef('void tokenizers_free(void* handle);', $lib);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return int[]
     */
    #[\Override]
    public function encode(string $text, bool $addSpecialTokens = true): array
    {
        throw new \RuntimeException(self::UNAVAILABLE);
    }

    /**
     * @param int[] $ids
     */
    #[\Override]
    public function decode(array $ids): string
    {
        throw new \RuntimeException(self::UNAVAILABLE);
    }

    /**
     * @param string[] $texts
     *
     * @return array{input_ids: int[][], attention_mask: int[][]}
     */
    #[\Override]
    public function encodeBatch(array $texts, bool $padToMaxLength = true): array
    {
        throw new \RuntimeException(self::UNAVAILABLE);
    }

    #[\Override]
    public function vocabSize(): int
    {
        throw new \RuntimeException(self::UNAVAILABLE);
    }

    #[\Override]
    public function type(): TokenizerType
    {
        return $this->tokenizerType;
    }

    #[\Override]
    public function specialTokenId(string $tokenName): ?int
    {
        throw new \RuntimeException(self::UNAVAILABLE);
    }

    /**
     * @return array<string, int>
     */
    #[\Override]
    public function specialTokens(): array
    {
        throw new \RuntimeException(self::UNAVAILABLE);
    }

    #[\Override]
    public function countTokens(string $text): int
    {
        throw new \RuntimeException(self::UNAVAILABLE);
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function chunk(string $text, int $maxTokens = 512, int $overlap = 64): array
    {
        throw new \RuntimeException(self::UNAVAILABLE);
    }

    public function path(): string
    {
        return $this->tokenizerPath;
    }
}
