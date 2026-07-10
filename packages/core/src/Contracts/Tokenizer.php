<?php

declare(strict_types=1);

namespace FerryAI\Core\Contracts;

use FerryAI\Core\Enums\TokenizerType;

interface Tokenizer
{
    /**
     * Encodes text into an array of token ids.
     *
     *
     * @throws \FerryAI\Core\Exception\TokenizerException
     * @return int[]
     */
    public function encode(string $text, bool $addSpecialTokens = true): array;

    /**
     * Decodes token ids back into text.
     *
     * @param int[] $ids
     *
     * @throws \FerryAI\Core\Exception\TokenizerException
     */
    public function decode(array $ids): string;

    /**
     * Batch-encodes multiple texts.
     *
     * @param string[] $texts
     *
     * @return array{input_ids: int[][], attention_mask: int[][]}
     */
    public function encodeBatch(array $texts, bool $padToMaxLength = true): array;

    /**
     * Returns the vocabulary size.
     */
    public function vocabSize(): int;

    /**
     * Returns the tokenizer type.
     */
    public function type(): TokenizerType;

    /**
     * Returns the id of a special token.
     *
     * @param string $tokenName one of: bos, eos, pad, unk, sep, cls, mask
     */
    public function specialTokenId(string $tokenName): ?int;

    /**
     * Returns all special tokens and their ids.
     *
     * @return array<string, int>
     */
    public function specialTokens(): array;

    /**
     * Estimates the token count without a full encode.
     */
    public function countTokens(string $text): int;

    /**
     * Splits long text into fixed-size chunks with overlap.
     *
     * @return string[]
     */
    public function chunk(string $text, int $maxTokens = 512, int $overlap = 64): array;
}
