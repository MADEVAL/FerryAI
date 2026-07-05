#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Core\FFI\CdefGenerator;

echo "=== 25 — FFI CDEF generator ===\n\n";

$header = <<<'C'
    #ifndef LLAMA_H
    #define LLAMA_H
    #include <stdint.h>

    #ifdef __cplusplus
    extern "C" {
    #endif

    // opaque handles
    struct llama_model;
    struct llama_context;

    /* model load parameters */
    typedef struct llama_model_params {
        int32_t n_gpu_layers;
        bool    use_mmap;
    } llama_model_params;

    LLAMA_API struct llama_model_params llama_model_default_params(void);
    LLAMA_API struct llama_model * llama_model_load_from_file(const char * path, struct llama_model_params params);
    LLAMA_API void llama_model_free(struct llama_model * model) __attribute__((deprecated));

    #ifdef __cplusplus
    }
    #endif
    #endif // LLAMA_H
    C;

$cdef = (new CdefGenerator())->generate($header, ['LLAMA_API']);

echo "--- Cleaned CDEF (comments/#/extern/LLAMA_API/__attribute__ removed) ---\n\n";
echo $cdef . "\n";

printf("input:  %d bytes\n", strlen($header));
printf("output: %d bytes\n", strlen($cdef));
printf("braces balanced: %s\n\n", substr_count($cdef, '{') === substr_count($cdef, '}') ? 'yes' : 'no');

if (extension_loaded('ffi')) {
    $types = "typedef struct point { int32_t x; int32_t y; } point;\nenum color { RED, GREEN, BLUE };";
    try {
        FFI::cdef((new CdefGenerator())->generate($types));
        echo "FFI::cdef parses generated type declarations: OK\n\n";
    } catch (Throwable $e) {
        echo 'FFI::cdef: ' . $e->getMessage() . "\n\n";
    }
}

echo "--- CLI ---\n\n";
echo "  php bin/generate-ffi.php --header path/to/llama.h --strip LLAMA_API,GGML_API \\\n";
echo "      --output LlamaCppCDEF.php --class LlamaCppCDEF\n\n";
echo "Note: enum values that reference macros from other headers (e.g. GGML_ROPE_TYPE_NEOX)\n";
echo "must be resolved manually — the generator cleans headers, it is not a full C preprocessor.\n";

echo "\n=== OK ===\n";
