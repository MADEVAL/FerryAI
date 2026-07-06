<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer;

use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Enums\TokenizerType;

final class HuggingFaceTokenizer implements Tokenizer
{
    /** @var string[] */
    private const array KNOWN_SPECIAL = ['bos', 'eos', 'unk', 'pad', 'cls', 'sep', 'mask'];

    private ?\FFI $ffi = null;

    /** @var mixed native tokenizer handle (void*) */
    private mixed $handle = null;

    public function __construct(
        private readonly string $tokenizerPath,
        private readonly TokenizerType $tokenizerType = TokenizerType::BPE,
    ) {
        if (self::isAvailable()) {
            $json = \file_get_contents($tokenizerPath);

            if (!\is_string($json)) {
                return;
            }

            $this->ffi = self::ffi();
            $this->handle = $this->ffi->tokenizers_new_from_str($json, \strlen($json));
        }
    }

    public function __destruct()
    {
        if ($this->handle !== null && $this->ffi !== null) {
            $this->ffi->tokenizers_free($this->handle);
        }
    }

    public static function isAvailable(): bool
    {
        try {
            self::ffi();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    #[\Override]
    public function encode(string $text, bool $addSpecialTokens = true): array
    {
        $this->assertLoaded();
        $r = $this->ffi->new('TokenizerEncodeResult');
        $this->ffi->tokenizers_encode($this->handle, $text, \strlen($text), $addSpecialTokens ? 1 : 0, \FFI::addr($r));
        $ids = [];

        for ($i = 0; $i < $r->len; ++$i) {
            $ids[] = $r->token_ids[$i];
        }

        $this->ffi->tokenizers_free_encode_results(\FFI::addr($r), 1);

        // tokenizers-cpp always writes 128 elements (fixed buffer); trim trailing zeros.
        while ($ids !== [] && \end($ids) === 0) {
            \array_pop($ids);
        }

        return $ids;
    }

    /**
     * @param int[] $ids
     */
    #[\Override]
    public function decode(array $ids): string
    {
        $this->assertLoaded();
        $count = \count($ids);

        if ($count === 0) {
            return '';
        }

        $data = $this->ffi->new(\sprintf('uint32_t[%d]', $count));

        foreach ($ids as $i => $id) {
            $data[$i] = $id;
        }

        $this->ffi->tokenizers_decode($this->handle, $data, $count, 0);

        $str = $this->ffi->new('char*');
        $len = $this->ffi->new('size_t');
        $this->ffi->tokenizers_get_decode_str($this->handle, \FFI::addr($str), \FFI::addr($len));

        if ($len->cdata === 0) {
            return '';
        }

        return \FFI::string($str, (int) $len->cdata);
    }

    /**
     * @param string[] $texts
     *
     * @return array{input_ids: int[][], attention_mask: int[][]}
     */
    #[\Override]
    public function encodeBatch(array $texts, bool $padToMaxLength = true): array
    {
        $this->assertLoaded();
        $inputIds = [];

        foreach ($texts as $text) {
            $inputIds[] = $this->encode($text, true);
        }

        $maxLen = 0;

        foreach ($inputIds as $row) {
            $maxLen = \max($maxLen, \count($row));
        }

        $padId = $this->padTokenId();
        $attentionMask = [];

        foreach ($inputIds as $i => $row) {
            $len = \count($row);

            if ($padToMaxLength && $len < $maxLen) {
                $inputIds[$i] = \array_merge($row, \array_fill(0, $maxLen - $len, $padId));
                $attentionMask[] = \array_merge(\array_fill(0, $len, 1), \array_fill(0, $maxLen - $len, 0));
            } else {
                $attentionMask[] = \array_fill(0, $len, 1);
            }
        }

        return ['input_ids' => $inputIds, 'attention_mask' => $attentionMask];
    }

    #[\Override]
    public function vocabSize(): int
    {
        $this->assertLoaded();
        $size = $this->ffi->new('size_t');
        $this->ffi->tokenizers_get_vocab_size($this->handle, \FFI::addr($size));

        return (int) $size->cdata;
    }

    #[\Override]
    public function type(): TokenizerType
    {
        return $this->tokenizerType;
    }

    #[\Override]
    public function specialTokenId(string $tokenName): ?int
    {
        $this->assertLoaded();
        $id = $this->ffi->new('int32_t');
        $this->ffi->tokenizers_token_to_id($this->handle, $tokenName, \strlen($tokenName), \FFI::addr($id));

        return $id->cdata === -1 ? null : $id->cdata;
    }

    /**
     * @return array<string, int>
     */
    #[\Override]
    public function specialTokens(): array
    {
        $this->assertLoaded();
        $result = [];

        foreach (self::KNOWN_SPECIAL as $name) {
            $id = $this->ffi->new('int32_t');
            $this->ffi->tokenizers_token_to_id($this->handle, $name, \strlen($name), \FFI::addr($id));

            if ($id->cdata !== -1) {
                $result[$name] = $id->cdata;
            }
        }

        return $result;
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

        $step = \max(1, $maxTokens - $overlap);
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

    public function path(): string
    {
        return $this->tokenizerPath;
    }

    private function assertLoaded(): void
    {
        if ($this->handle === null || $this->ffi === null) {
            throw new \RuntimeException(
                'The native tokenizer binding is not loaded. Set FERRY_AI_TOKENIZERS_LIB and ensure the shared library is available.',
            );
        }
    }

    private function padTokenId(): int
    {
        $id = $this->ffi->new('int32_t');
        $this->ffi->tokenizers_token_to_id($this->handle, 'pad', 3, \FFI::addr($id));

        return $id->cdata !== -1 ? $id->cdata : 0;
    }

    private static function ffi(): \FFI
    {
        static $ffi = null;

        if ($ffi === null) {
            $lib = \getenv('FERRY_AI_TOKENIZERS_LIB');

            if ($lib === false || $lib === '' || !\is_file($lib)) {
                throw new \RuntimeException('FERRY_AI_TOKENIZERS_LIB is not set or the file does not exist.');
            }

            $ffi = \FFI::cdef(self::CDEF, $lib);
        }

        return $ffi;
    }

    private const string CDEF = <<<'CDEF'
        typedef void* TokenizerHandle;

        typedef struct {
            int* token_ids;
            size_t len;
        } TokenizerEncodeResult;

        TokenizerHandle tokenizers_new_from_str(const char* json, size_t len);
        void tokenizers_encode(TokenizerHandle handle, const char* data, size_t len, int add_special_token, TokenizerEncodeResult* result);
        void tokenizers_decode(TokenizerHandle handle, const uint32_t* data, size_t len, int skip_special_token);
        void tokenizers_get_decode_str(TokenizerHandle handle, const char** data, size_t* len);
        void tokenizers_free_encode_results(TokenizerEncodeResult* results, size_t num_seqs);
        void tokenizers_get_vocab_size(TokenizerHandle handle, size_t* size);
        void tokenizers_token_to_id(TokenizerHandle handle, const char* token, size_t len, int32_t* id);
        void tokenizers_free(TokenizerHandle handle);
        CDEF;
}
