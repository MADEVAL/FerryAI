<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer;

use FerryAI\Core\Enums\TokenizerType;
use FerryAI\Core\Exception\TokenizerException;

/**
 * Loads and inspects `tokenizer.json` files.
 */
final class TokenizerLoader
{
    /**
     * @throws TokenizerException   when the file is missing or not valid JSON
     * @return array<string, mixed>
     */
    public function loadFromFile(string $path): array
    {
        if (!is_file($path)) {
            throw new TokenizerException(\sprintf("tokenizer file not found: '%s'", $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new TokenizerException(\sprintf("could not read tokenizer file: '%s'", $path));
        }

        try {
            $decoded = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new TokenizerException('invalid tokenizer JSON: ' . $exception->getMessage());
        }

        if (!\is_array($decoded)) {
            throw new TokenizerException('tokenizer JSON must decode to an object');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @throws TokenizerException   always in Phase 2 (remote loading needs the model-hub package)
     * @return array<string, mixed>
     */
    public function loadFromModel(string $modelId): array
    {
        throw new TokenizerException(\sprintf(
            "cannot load tokenizer for '%s': remote loading requires the model-hub package (Phase 3)",
            $modelId,
        ));
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws TokenizerException when the type cannot be determined
     */
    public function detectType(array $config): TokenizerType
    {
        $model = \is_array($config['model'] ?? null) ? $config['model'] : [];
        $type = \is_string($model['type'] ?? null) ? strtolower($model['type']) : '';

        return match ($type) {
            'bpe' => TokenizerType::BPE,
            'wordpiece' => TokenizerType::WordPiece,
            'unigram' => TokenizerType::Unigram,
            'sentencepiece' => TokenizerType::SentencePiece,
            default => throw new TokenizerException(\sprintf("unknown tokenizer type: '%s'", $type)),
        };
    }
}
