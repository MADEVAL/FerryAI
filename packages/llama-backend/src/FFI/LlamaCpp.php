<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\FFI;

/**
 * FFI definitions for llama.cpp C API — build 9873 (a4107133a).
 *
 * Full inference CDEF with model/context structs.
 * If your build differs, diff your llama.h against this.
 */
final class LlamaCpp
{
    private const string CDEF = <<<'C'
        typedef struct llama_model   llama_model;
        typedef struct llama_context llama_context;
        typedef struct llama_vocab   llama_vocab;
        typedef struct llama_batch   llama_batch;
        typedef int32_t              llama_token;
        typedef int32_t              llama_pos;
        typedef int32_t              llama_seq_id;

        enum llama_split_mode       { LLAMA_SPLIT_MODE_NONE=0, LLAMA_SPLIT_MODE_LAYER=1, LLAMA_SPLIT_MODE_ROW=2, LLAMA_SPLIT_MODE_TENSOR=3 };
        enum llama_context_type     { LLAMA_CONTEXT_TYPE_DEFAULT=0 };
        enum llama_rope_scaling_type { LLAMA_ROPE_SCALING_TYPE_UNSPECIFIED=-1, LLAMA_ROPE_SCALING_TYPE_NONE=0 };
        enum llama_pooling_type     { LLAMA_POOLING_TYPE_UNSPECIFIED=-1, LLAMA_POOLING_TYPE_NONE=0, LLAMA_POOLING_TYPE_MEAN=1 };
        enum llama_attention_type   { LLAMA_ATTENTION_TYPE_UNSPECIFIED=-1, LLAMA_ATTENTION_TYPE_CAUSAL=0 };
        enum llama_flash_attn_type  { LLAMA_FLASH_ATTN_TYPE_AUTO=-1 };

        typedef bool (*llama_progress_callback)(float progress, void * user_data);

        struct llama_model_params {
            void *          devices;
            void *          tensor_buft_overrides;
            int32_t         n_gpu_layers;
            int32_t         split_mode;
            int32_t         main_gpu;
            int32_t         _pad;
            float *         tensor_split;
            llama_progress_callback progress_callback;
            void *          progress_callback_user_data;
            void *          kv_overrides;
        };

        struct llama_context_params {
            uint32_t n_ctx;
            uint32_t n_batch;
            uint32_t n_ubatch;
            uint32_t n_seq_max;
            uint32_t n_rs_seq;
            uint32_t n_outputs_max;
            int32_t  n_threads;
            int32_t  n_threads_batch;
            int32_t  ctx_type;
            int32_t  rope_scaling_type;
            int32_t  pooling_type;
            int32_t  attention_type;
            int32_t  flash_attn_type;
            float    rope_freq_base;
            float    rope_freq_scale;
            float    yarn_ext_factor;
            float    yarn_attn_factor;
            float    yarn_beta_fast;
            float    yarn_beta_slow;
            uint32_t yarn_orig_ctx;
            float    defrag_thold;
            void *   cb_eval;
            void *   cb_eval_user_data;
            int32_t  type_k;
            int32_t  type_v;
        };

        void        llama_backend_init(void);
        void        llama_backend_free(void);
        const char* llama_print_system_info(void);
        bool        llama_supports_mmap(void);
        bool        llama_supports_mlock(void);
        bool        llama_supports_gpu_offload(void);

        struct llama_model_params   llama_model_default_params(void);
        struct llama_context_params llama_context_default_params(void);

        struct llama_model *  llama_model_load_from_file(const char * path, struct llama_model_params params);
        void                  llama_model_free(struct llama_model * model);
        const llama_vocab *   llama_model_get_vocab(const llama_model * model);
        int32_t               llama_model_n_embd(const llama_model * model);
        int32_t               llama_n_vocab(const llama_vocab * vocab);

        struct llama_context * llama_init_from_model(struct llama_model * model, struct llama_context_params params);
        void                  llama_free(struct llama_context * ctx);

        struct llama_batch     llama_batch_get_one(llama_token * tokens, int32_t n_tokens);
        int32_t                llama_decode(struct llama_context * ctx, struct llama_batch batch);
        int32_t                llama_n_ctx(const struct llama_context * ctx);

        int32_t llama_vocab_n_tokens(const struct llama_vocab * vocab);
        int32_t llama_token_to_piece(const struct llama_vocab * vocab, llama_token token, char * buf, int32_t length, int32_t lstrip, bool special);
        int32_t llama_tokenize(const struct llama_vocab * vocab, const char * text, int32_t text_len, llama_token * tokens, int32_t n_tokens_max, bool add_special, bool parse_special);

        float * llama_get_logits(struct llama_context * ctx);
        C;

    private ?\FFI $ffi = null;
    private bool $initialized = false;

    public function __construct(private readonly ?string $libraryPath = null) {}

    public static function resolveLibraryPath(): ?string
    {
        $path = \getenv('FERRY_AI_LLAMA_LIB');
        return $path === false || $path === '' ? null : $path;
    }

    public static function registerLibraryPath(string $libPath): void
    {
        $dir = \dirname($libPath);
        $currentPath = \getenv('PATH') ?: '';
        $sep = \PHP_OS_FAMILY === 'Windows' ? ';' : ':';
        if (!\str_contains($currentPath, $dir)) {
            \putenv('PATH=' . $currentPath . $sep . $dir);
        }
    }

    public function isLibraryLoadable(): bool
    {
        try { return $this->ffi() instanceof \FFI; }
        catch (\Throwable) { return false; }
    }

    public function tryInit(): bool
    {
        if ($this->initialized) return true;
        try {
            ($this->ffi()->llama_backend_init)();
            $this->initialized = true;
            return true;
        } catch (\FFI\Exception) { return false; }
    }

    public function version(): string
    {
        try {
            $info = ($this->ffi()->llama_print_system_info)();
            return \is_string($info) && $info !== '' ? $info : 'llama.cpp (native)';
        } catch (\Throwable) { return 'llama.cpp (probe only)'; }
    }

    public function supportsGpu(): bool
    {
        try { return ($this->ffi()->llama_supports_gpu_offload)(); }
        catch (\Throwable) { return false; }
    }

    public function supportsMmap(): bool
    {
        try { return ($this->ffi()->llama_supports_mmap)(); }
        catch (\Throwable) { return false; }
    }

    /** @return object{llama_model_params} */
    public function newModelParams(): object
    {
        return $this->ffi()->new('struct llama_model_params');
    }

    /** @return object{llama_context_params} */
    public function newContextParams(): object
    {
        return $this->ffi()->new('struct llama_context_params');
    }

    /** @return object{llama_model_params} */
    public function modelDefaultParams(): object
    {
        return ($this->ffi()->llama_model_default_params)();
    }

    /** @return object{llama_context_params} */
    public function contextDefaultParams(): object
    {
        return ($this->ffi()->llama_context_default_params)();
    }

    /** @return object{llama_model} */
    public function modelLoad(string $path, object $params): object
    {
        $model = ($this->ffi()->llama_model_load_from_file)($path, $params);
        if ($model === null || \FFI::isNull(\FFI::addr($model))) {
            throw new \RuntimeException('Failed to load model: ' . $path);
        }
        return $model;
    }

    /** @return object{llama_vocab} */
    public function modelGetVocab(object $model): object
    {
        return ($this->ffi()->llama_model_get_vocab)($model);
    }

    /** @return object{llama_context} */
    public function contextInit(object $model, object $params): object
    {
        $ctx = ($this->ffi()->llama_init_from_model)($model, $params);
        if ($ctx === null || \FFI::isNull(\FFI::addr($ctx))) {
            throw new \RuntimeException('Failed to create context');
        }
        return $ctx;
    }

    public function modelFree(object $model): void
    {
        ($this->ffi()->llama_model_free)($model);
    }

    public function contextFree(object $ctx): void
    {
        ($this->ffi()->llama_free)($ctx);
    }

    public function vocabSize(object $vocab): int
    {
        return ($this->ffi()->llama_vocab_n_tokens)($vocab);
    }

    /** @param object{llama_vocab} $vocab */
    public function tokenize(object $vocab, string $text, bool $addSpecial, bool $parseSpecial): array
    {
        $n = \strlen($text);
        $maxTokens = $n + 16;
        $buf = \FFI::new('int32_t[' . $maxTokens . ']');
        $count = ($this->ffi()->llama_tokenize)($vocab, $text, $n, $buf, $maxTokens, $addSpecial, $parseSpecial);
        if ($count < 0) $count = 0;
        $tokens = [];
        for ($i = 0; $i < $count; $i++) {
            $tokens[] = $buf[$i];
        }
        return $tokens;
    }

    /** @return \FFI\CData */
    public function batchGetOne(array $tokens, int $nTokens): object
    {
        $n = \min(\count($tokens), $nTokens);
        $buf = \FFI::new('int32_t[' . $n . ']');
        for ($i = 0; $i < $n; $i++) {
            $buf[$i] = $tokens[$i];
        }
        return ($this->ffi()->llama_batch_get_one)($buf, $n);
    }

    public function decode(object $ctx, object $batch): int
    {
        return ($this->ffi()->llama_decode)($ctx, $batch);
    }

    /** @return \FFI\CData */
    public function getLogits(object $ctx): object
    {
        return ($this->ffi()->llama_get_logits)($ctx);
    }

    /** @param object{llama_vocab} $vocab */
    public function tokenToPiece(object $vocab, int $token): string
    {
        $buf = \FFI::new('char[256]');
        $n = ($this->ffi()->llama_token_to_piece)($vocab, $token, $buf, 256, 0, true);
        return \FFI::string($buf, \max(0, $n));
    }

    public function modelDim(object $model): int
    {
        return ($this->ffi()->llama_model_n_embd)($model);
    }

    public function nCtx(object $ctx): int
    {
        return ($this->ffi()->llama_n_ctx)($ctx);
    }

    private function ffi(): \FFI
    {
        if ($this->ffi instanceof \FFI) return $this->ffi;
        $path = $this->libraryPath ?? self::resolveLibraryPath();
        if ($path === null || !\is_file($path)) {
            throw new \RuntimeException('llama.cpp library not found; set FERRY_AI_LLAMA_LIB.');
        }
        self::registerLibraryPath($path);
        $this->ffi = \FFI::cdef(self::CDEF, $path);
        return $this->ffi;
    }
}
