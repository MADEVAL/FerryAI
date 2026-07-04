<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer;

use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Enums\TokenizerType;

/**
 * Pure-PHP byte-pair-encoding tokenizer (CPU fallback).
 *
 * Self-consistent for its own vocabulary/merges; for production-grade, model-accurate tokenization
 * prefer the native HuggingFace tokenizer. Words are split on whitespace, encoded as characters plus
 * an end-of-word marker, then merged greedily by merge rank.
 */
final class PureBpeTokenizer implements Tokenizer
{
    /** @var array<string, int> */
    private readonly array $vocab;

    /** @var array<int, string> */
    private readonly array $idToToken;

    /** @var array<string, int> */
    private readonly array $mergeRanks;

    /** @var array<string, int> */
    private readonly array $special;

    /**
     * @param array<string, int> $vocab   token => id
     * @param list<string>       $merges  merge rules, each "left right"
     * @param array<string, int> $special role => id (bos, eos, unk, pad, ...)
     */
    public function __construct(
        array $vocab,
        array $merges = [],
        array $special = [],
        private readonly string $endOfWord = '</w>',
    ) {
        $this->vocab = $vocab;
        $this->idToToken = array_flip($vocab);
        $this->special = $special;

        $ranks = [];

        foreach ($merges as $rank => $merge) {
            $ranks[$merge] = $rank;
        }

        $this->mergeRanks = $ranks;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        $model = \is_array($config['model'] ?? null) ? $config['model'] : [];

        $vocab = [];

        if (\is_array($model['vocab'] ?? null)) {
            foreach ($model['vocab'] as $token => $id) {
                if (\is_string($token) && \is_int($id)) {
                    $vocab[$token] = $id;
                }
            }
        }

        $merges = [];

        if (\is_array($model['merges'] ?? null)) {
            foreach ($model['merges'] as $merge) {
                if (\is_string($merge)) {
                    $merges[] = $merge;
                } elseif (\is_array($merge) && isset($merge[0], $merge[1])) {
                    $merges[] = $merge[0] . ' ' . $merge[1];
                }
            }
        }

        return new self($vocab, $merges, SpecialTokens::extract($config));
    }

    /**
     * @return int[]
     */
    #[\Override]
    public function encode(string $text, bool $addSpecialTokens = true): array
    {
        $ids = [];

        if ($addSpecialTokens && isset($this->special['bos'])) {
            $ids[] = $this->special['bos'];
        }

        foreach ($this->words($text) as $word) {
            $symbols = mb_str_split($word);
            $symbols[] = $this->endOfWord;

            foreach ($this->applyMerges($symbols) as $symbol) {
                $ids[] = $this->vocab[$symbol] ?? $this->unkId();
            }
        }

        if ($addSpecialTokens && isset($this->special['eos'])) {
            $ids[] = $this->special['eos'];
        }

        return $ids;
    }

    /**
     * @param int[] $ids
     */
    #[\Override]
    public function decode(array $ids): string
    {
        $specialIds = array_values($this->special);
        $text = '';

        foreach ($ids as $id) {
            if (\in_array($id, $specialIds, true)) {
                continue;
            }

            $token = $this->idToToken[$id] ?? '';
            $text .= $token === $this->endOfWord ? ' ' : $token;
        }

        return rtrim($text);
    }

    /**
     * @param string[] $texts
     *
     * @return array{input_ids: int[][], attention_mask: int[][]}
     */
    #[\Override]
    public function encodeBatch(array $texts, bool $padToMaxLength = true): array
    {
        $encoded = array_map(fn(string $text): array => $this->encode($text), $texts);
        $maxLength = 0;

        foreach ($encoded as $row) {
            $maxLength = max($maxLength, \count($row));
        }

        $padId = $this->special['pad'] ?? 0;
        $inputIds = [];
        $attentionMask = [];

        foreach ($encoded as $row) {
            $length = \count($row);

            if ($padToMaxLength && $length < $maxLength) {
                $inputIds[] = array_merge($row, array_fill(0, $maxLength - $length, $padId));
                $attentionMask[] = array_merge(array_fill(0, $length, 1), array_fill(0, $maxLength - $length, 0));
            } else {
                $inputIds[] = $row;
                $attentionMask[] = array_fill(0, $length, 1);
            }
        }

        return ['input_ids' => $inputIds, 'attention_mask' => $attentionMask];
    }

    #[\Override]
    public function vocabSize(): int
    {
        return \count($this->vocab);
    }

    #[\Override]
    public function type(): TokenizerType
    {
        return TokenizerType::BPE;
    }

    #[\Override]
    public function specialTokenId(string $tokenName): ?int
    {
        return $this->special[$tokenName] ?? null;
    }

    /**
     * @return array<string, int>
     */
    #[\Override]
    public function specialTokens(): array
    {
        return $this->special;
    }

    #[\Override]
    public function countTokens(string $text): int
    {
        return \count($this->encode($text, false));
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function chunk(string $text, int $maxTokens = 512, int $overlap = 64): array
    {
        $ids = $this->encode($text, false);

        if ($ids === []) {
            return [];
        }

        $step = max(1, $maxTokens - $overlap);
        $chunks = [];
        $count = \count($ids);

        for ($start = 0; $start < $count; $start += $step) {
            $window = \array_slice($ids, $start, $maxTokens);

            if ($window === []) {
                break;
            }

            $chunks[] = $this->decode($window);

            if ($start + $maxTokens >= $count) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        $words = preg_split('/\s+/', trim($text), -1, \PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
    }

    /**
     * @param list<string> $symbols
     *
     * @return list<string>
     */
    private function applyMerges(array $symbols): array
    {
        while (\count($symbols) >= 2) {
            $bestRank = null;
            $bestPosition = -1;

            for ($i = 0, $n = \count($symbols) - 1; $i < $n; ++$i) {
                $rank = $this->mergeRanks[$symbols[$i] . ' ' . $symbols[$i + 1]] ?? null;

                if ($rank !== null && ($bestRank === null || $rank < $bestRank)) {
                    $bestRank = $rank;
                    $bestPosition = $i;
                }
            }

            if ($bestPosition < 0) {
                break;
            }

            array_splice($symbols, $bestPosition, 2, [$symbols[$bestPosition] . $symbols[$bestPosition + 1]]);
        }

        return $symbols;
    }

    private function unkId(): int
    {
        return $this->special['unk'] ?? 0;
    }
}
