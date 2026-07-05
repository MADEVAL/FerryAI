<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer;

use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Enums\TokenizerType;
use FerryAI\Core\Exception\TokenizerException;

/**
 * Builds tokenizers from a model name or a `tokenizer.json` file.
 */
final class TokenizerFactory
{
    public function __construct(private readonly TokenizerLoader $loader = new TokenizerLoader()) {}

    /**
     * @throws TokenizerException when the tokenizer cannot be resolved or is unsupported
     */
    public function create(string $modelName): Tokenizer
    {
        if (is_file($modelName)) {
            return $this->createFromFile($modelName);
        }

        throw new TokenizerException(\sprintf(
            "cannot resolve tokenizer '%s' by name: pass a tokenizer.json path, or use the "
            . 'model-hub package (Phase 3) to download it.',
            $modelName,
        ));
    }

    /**
     * @throws TokenizerException when the tokenizer type is unsupported by the pure-PHP tokenizers
     */
    public function createFromFile(string $tokenizerJsonPath): Tokenizer
    {
        $config = $this->loader->loadFromFile($tokenizerJsonPath);
        $type = $this->loader->detectType($config);

        if (HuggingFaceTokenizer::isAvailable()) {
            return new HuggingFaceTokenizer($tokenizerJsonPath, $type);
        }

        return match ($type) {
            TokenizerType::BPE => PureBpeTokenizer::fromConfig($config),
            TokenizerType::WordPiece => PureWordPieceTokenizer::fromConfig($config),
            default => throw new TokenizerException(\sprintf(
                "tokenizer type '%s' has no pure-PHP implementation; install the native tokenizers binding",
                $type->value,
            )),
        };
    }
}
