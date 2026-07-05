<?php

declare(strict_types=1);

/*
 * Smoke test for ferry_llama.dll — real llama.cpp inference through PHP FFI on
 * CPU and GPU. Run standalone (NOT under PHPUnit; the ggml global constructors
 * conflict with PHPUnit's output/exception handling — see DEBT_REPORT.md #12).
 *
 *   $env:PATH = "D:\FerryAI;" + $env:PATH
 *   php native/llama-wrapper/ffi-smoke.php
 *
 * Env overrides: FERRY_AI_LLAMA_DIR (default D:\FerryAI),
 *                FERRY_AI_GGUF (default $dir\qwen-0.5b.Q4_K_M.gguf).
 */

$dir = getenv('FERRY_AI_LLAMA_DIR') ?: 'D:\\FerryAI';
$gguf = getenv('FERRY_AI_GGUF') ?: $dir . '\\qwen-0.5b.Q4_K_M.gguf';
$wrapper = $dir . '\\ferry_llama.dll';

if (!is_file($wrapper) || !is_file($gguf)) {
    fwrite(STDERR, "SKIP: need $wrapper and $gguf (and $dir on PATH).\n");
    exit(0);
}

$cdef = <<<'CDEF'
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
int ferry_tokenize(void* model, const char* text, int* out_tokens, int max_tokens, int add_bos);
int ferry_generate_greedy(void* ctx, void* model, const int* prompt_tokens, int n_prompt, int max_new, char* out, int out_size);
CDEF;

$ffi = FFI::cdef($cdef, $wrapper);
$ffi->ferry_load_backends($dir);
$ffi->ferry_llama_backend_init();

echo 'gpu_offload_supported: ' . $ffi->ferry_supports_gpu_offload() . PHP_EOL;

$run = static function (FFI $ffi, string $gguf, int $ngl, string $label): void {
    $model = $ffi->ferry_load_model($gguf, $ngl);

    if ($model === null || FFI::isNull($model)) {
        echo "[$label] model load FAILED" . PHP_EOL;
        return;
    }

    $ctx = $ffi->ferry_new_context($model, 512, 4);

    $maxTok = 64;
    $tokens = $ffi->new("int[$maxTok]");
    $n = $ffi->ferry_tokenize($model, 'The capital of France is', $tokens, $maxTok, 1);

    $out = $ffi->new('char[1024]');
    $t0 = microtime(true);
    $nNew = 24;
    $ffi->ferry_generate_greedy($ctx, $model, $tokens, $n, $nNew, $out, 1024);
    $ms = (microtime(true) - $t0) * 1000;

    echo "[$label] " . trim(FFI::string($out)) . PHP_EOL;
    echo "[$label] ~" . round($nNew / ($ms / 1000), 1) . " tok/s" . PHP_EOL;

    $ffi->ferry_free_context($ctx);
    $ffi->ferry_free_model($model);
};

$run($ffi, $gguf, 0, 'CPU');
$run($ffi, $gguf, 99, 'GPU');

$ffi->ferry_llama_backend_free();
echo 'DONE' . PHP_EOL;
