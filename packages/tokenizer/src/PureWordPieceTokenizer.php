<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer;

use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Enums\TokenizerType;

/**
 * Pure-PHP WordPiece tokenizer (CPU fallback), using greedy longest-match subwords.
 */
final class PureWordPieceTokenizer implements Tokenizer
{
    /** @var array<string, int> */
    private readonly array $vocab;

    /** @var array<int, string> */
    private readonly array $idToToken;

    /** @var array<string, int> */
    private readonly array $special;

    /**
     * @param array<string, int> $vocab   token => id
     * @param array<string, int> $special role => id (unk, cls, sep, pad, ...)
     */
    public function __construct(
        array $vocab,
        array $special = [],
        private readonly string $continuationPrefix = '##',
    ) {
        $this->vocab = $vocab;
        $this->idToToken = array_flip($vocab);
        $this->special = $special;
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

        $prefix = \is_string($model['continuing_subword_prefix'] ?? null)
            ? $model['continuing_subword_prefix']
            : '##';

        return new self($vocab, SpecialTokens::extract($config), $prefix);
    }

    /**
     * @return int[]
     */
    #[\Override]
    public function encode(string $text, bool $addSpecialTokens = true): array
    {
        $ids = [];

        if ($addSpecialTokens && isset($this->special['cls'])) {
            $ids[] = $this->special['cls'];
        }

        foreach ($this->words($text) as $word) {
            foreach ($this->encodeWord($word) as $id) {
                $ids[] = $id;
            }
        }

        if ($addSpecialTokens && isset($this->special['sep'])) {
            $ids[] = $this->special['sep'];
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

            if (str_starts_with($token, $this->continuationPrefix)) {
                $text .= substr($token, \strlen($this->continuationPrefix));
            } else {
                $text .= ($text === '' ? '' : ' ') . $token;
            }
        }

        return $text;
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
        return TokenizerType::WordPiece;
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
     * @return int[]
     */
    private function encodeWord(string $word): array
    {
        $length = mb_strlen($word);
        $start = 0;
        $subwords = [];

        while ($start < $length) {
            $end = $length;
            $found = null;

            while ($end > $start) {
                $piece = mb_substr($word, $start, $end - $start);

                if ($start > 0) {
                    $piece = $this->continuationPrefix . $piece;
                }

                if (isset($this->vocab[$piece])) {
                    $found = $piece;

                    break;
                }

                --$end;
            }

            if ($found === null) {
                return [$this->unkId()];
            }

            $subwords[] = $this->vocab[$found];
            $start = $end;
        }

        return $subwords;
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        $words = preg_split('/\s+/', trim($text), -1, \PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
    }

    private function unkId(): int
    {
        return $this->special['unk'] ?? 0;
    }
}
