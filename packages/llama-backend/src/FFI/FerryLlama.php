<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\FFI;

use FerryAI\Core\PlatformDetector;

/**
 * PHP-FFI binding to the FerryAI flat llama.cpp wrapper (`ferry_llama.dll`).
 *
 * The wrapper (native/llama-wrapper) exposes a flat API — no C structs cross the
 * FFI boundary — which sidesteps the struct-by-value ABI crash that breaks a
 * direct llama.dll binding (docs/DEBT_REPORT.md §12).
 *
 * Excluded from static analysis (untyped FFI boundary). Standalone-process only:
 * loading the DLL runs ggml's global constructors, which conflict with PHPUnit.
 */
final class FerryLlama
{
    private const CDEF = <<<'CDEF'
        void ferry_llama_backend_init(void);
        void ferry_load_backends(const char* dir);
        void ferry_llama_backend_free(void);
        int ferry_supports_gpu_offload(void);
        void* ferry_load_model(const char* path, int n_gpu_layers);
        void ferry_free_model(void* model);
        void* ferry_new_context(void* model, int n_ctx, int n_threads);
        void ferry_free_context(void* ctx);
        int ferry_n_vocab(void* model);
        int ferry_n_embd(void* model);
        int ferry_n_ctx(void* ctx);
        int ferry_eos_token(void* model);
        int ferry_tokenize(void* model, const char* text, int* out_tokens, int max_tokens, int add_bos);
        int ferry_token_to_piece(void* model, int token, char* buf, int buf_size);
        int ferry_eval(void* ctx, void* model, const int* tokens, int n_tokens, float* out, int out_size);
        int ferry_eval_topk(void* ctx, void* model, const int* tokens, int n_tokens, int k, int* out_ids, float* out_logits);
        void ferry_reset(void* ctx);
        CDEF;

    private \FFI $ffi;

    public function __construct(string $wrapperPath, string $backendDir)
    {
        // Help the loader resolve the dependent libs (llama, ggml*) next to the wrapper.
        // On Windows that means PATH; on Linux/macOS the wrapper is built with an rpath of
        // $ORIGIN, and we also export LD_LIBRARY_PATH / DYLD_LIBRARY_PATH as a best effort.
        $var = match (PlatformDetector::os()) {
            'windows' => 'PATH',
            'macos' => 'DYLD_LIBRARY_PATH',
            default => 'LD_LIBRARY_PATH',
        };
        $current = \getenv($var) ?: '';

        if (!\str_contains($current, $backendDir)) {
            \putenv($var . '=' . $backendDir . \PATH_SEPARATOR . $current);
        }

        $this->ffi = \FFI::cdef(self::CDEF, $wrapperPath);
        $this->ffi->ferry_load_backends($backendDir);
        $this->ffi->ferry_llama_backend_init();
    }

    public static function resolveWrapperPath(): ?string
    {
        $explicit = \getenv('FERRY_AI_LLAMA_WRAPPER');

        if (\is_string($explicit) && $explicit !== '') {
            return \is_file($explicit) ? $explicit : null;
        }

        $lib = \getenv('FERRY_AI_LLAMA_LIB');

        if (\is_string($lib) && $lib !== '') {
            $candidate = \dirname($lib) . \DIRECTORY_SEPARATOR . 'ferry_llama.' . PlatformDetector::libExtension();

            return \is_file($candidate) ? $candidate : null;
        }

        return null;
    }

    public function supportsGpu(): bool
    {
        return $this->ffi->ferry_supports_gpu_offload() === 1;
    }

    public function loadModel(string $path, int $nGpuLayers): \FFI\CData
    {
        $model = $this->ffi->ferry_load_model($path, $nGpuLayers);

        if ($model === null || \FFI::isNull($model)) {
            throw new \RuntimeException(\sprintf('ferry_load_model failed for %s', $path));
        }

        return $model;
    }

    public function newContext(\FFI\CData $model, int $nCtx, int $nThreads): \FFI\CData
    {
        $ctx = $this->ffi->ferry_new_context($model, $nCtx, $nThreads);

        if ($ctx === null || \FFI::isNull($ctx)) {
            throw new \RuntimeException('ferry_new_context failed');
        }

        return $ctx;
    }

    public function nVocab(\FFI\CData $model): int
    {
        return $this->ffi->ferry_n_vocab($model);
    }

    public function nEmbd(\FFI\CData $model): int
    {
        return $this->ffi->ferry_n_embd($model);
    }

    public function nCtx(\FFI\CData $ctx): int
    {
        return $this->ffi->ferry_n_ctx($ctx);
    }

    public function eosToken(\FFI\CData $model): int
    {
        return $this->ffi->ferry_eos_token($model);
    }

    /**
     * @return list<int>
     */
    public function tokenize(\FFI\CData $model, string $text, bool $addBos): array
    {
        $max = \strlen($text) + 16;
        $buf = $this->ffi->new("int[$max]");
        $n = $this->ffi->ferry_tokenize($model, $text, $buf, $max, $addBos ? 1 : 0);

        $tokens = [];

        for ($i = 0; $i < $n; $i++) {
            $tokens[] = $buf[$i];
        }

        return $tokens;
    }

    public function tokenToPiece(\FFI\CData $model, int $token): string
    {
        $buf = $this->ffi->new('char[512]');
        $len = $this->ffi->ferry_token_to_piece($model, $token, $buf, 512);

        if ($len <= 0) {
            return '';
        }

        return \FFI::string($buf, $len);
    }

    /**
     * @param  list<int>   $tokens
     * @return list<float>
     */
    public function eval(\FFI\CData $ctx, \FFI\CData $model, array $tokens, int $nVocab): array
    {
        $n = \count($tokens);
        $in = $this->ffi->new("int[$n]");

        foreach ($tokens as $i => $token) {
            $in[$i] = $token;
        }

        $out = $this->ffi->new("float[$nVocab]");
        $written = $this->ffi->ferry_eval($ctx, $model, $in, $n, $out, $nVocab);

        if ($written < 0) {
            throw new \RuntimeException('ferry_eval failed (llama_decode error)');
        }

        $logits = [];

        for ($i = 0; $i < $written; $i++) {
            $logits[] = $out[$i];
        }

        return $logits;
    }

    public function reset(\FFI\CData $ctx): void
    {
        $this->ffi->ferry_reset($ctx);
    }

    /**
     * Top-k logits by token id (descending), computed natively over the full vocab.
     *
     * @param  list<int>        $tokens
     * @return array<int, float> token id => logit
     */
    public function evalTopK(\FFI\CData $ctx, \FFI\CData $model, array $tokens, int $k): array
    {
        $n = \count($tokens);
        $in = $this->ffi->new("int[$n]");

        foreach ($tokens as $i => $token) {
            $in[$i] = $token;
        }

        $ids = $this->ffi->new("int[$k]");
        $vals = $this->ffi->new("float[$k]");
        $written = $this->ffi->ferry_eval_topk($ctx, $model, $in, $n, $k, $ids, $vals);

        if ($written < 0) {
            throw new \RuntimeException('ferry_eval_topk failed (llama_decode error)');
        }

        $logits = [];

        for ($i = 0; $i < $written; $i++) {
            $logits[$ids[$i]] = $vals[$i];
        }

        return $logits;
    }

    public function freeContext(\FFI\CData $ctx): void
    {
        $this->ffi->ferry_free_context($ctx);
    }

    public function freeModel(\FFI\CData $model): void
    {
        $this->ffi->ferry_free_model($model);
    }
}
