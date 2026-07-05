#include "llama.h"
#include <string.h>
#include <stdlib.h>

#define FERRY_API __declspec(dllexport)

/*
 * FerryAI flat wrapper around llama.cpp.
 *
 * Purpose: never pass a C struct BY VALUE across the PHP-FFI boundary. All
 * llama_*_params structs are created and passed inside this DLL (real C ABI,
 * compiled with MSVC against the actual headers), so PHP FFI only ever sees
 * pointers, ints, strings and byte buffers. This sidesteps the struct-by-value
 * ABI mismatch that crashes PHP FFI on model load (DEBT_REPORT.md #12).
 *
 * Build (see build.ps1): needs llama.h + the ggml headers, plus import libs
 * generated from llama.dll and ggml.dll. Link: llama.lib ggml.lib.
 */

FERRY_API void ferry_llama_backend_init(void) {
    llama_backend_init();
}

FERRY_API void ferry_llama_backend_free(void) {
    llama_backend_free();
}

/*
 * Load the ggml backend DLLs (ggml-cpu-*.dll, ggml-cuda.dll, ...) from a
 * directory. Must be called before loading a model. llama.cpp resolves the
 * backend DLLs relative to the host executable by default, which is not the
 * llama directory when the host is php.exe — hence the explicit path.
 */
FERRY_API void ferry_load_backends(const char * dir) {
    if (dir && dir[0]) {
        ggml_backend_load_all_from_path(dir);
    } else {
        ggml_backend_load_all();
    }
}

FERRY_API int ferry_supports_gpu_offload(void) {
    return llama_supports_gpu_offload() ? 1 : 0;
}

FERRY_API struct llama_model * ferry_load_model(const char * path, int n_gpu_layers) {
    struct llama_model_params mp = llama_model_default_params();
    mp.n_gpu_layers = n_gpu_layers;
    return llama_model_load_from_file(path, mp);
}

FERRY_API void ferry_free_model(struct llama_model * model) {
    if (model) llama_model_free(model);
}

FERRY_API struct llama_context * ferry_new_context(struct llama_model * model, int n_ctx, int n_threads) {
    struct llama_context_params cp = llama_context_default_params();
    cp.n_ctx = (unsigned int) n_ctx;
    if (n_threads > 0) {
        cp.n_threads = n_threads;
        cp.n_threads_batch = n_threads;
    }
    return llama_init_from_model(model, cp);
}

FERRY_API void ferry_free_context(struct llama_context * ctx) {
    if (ctx) llama_free(ctx);
}

FERRY_API int ferry_n_vocab(struct llama_model * model) {
    return llama_vocab_n_tokens(llama_model_get_vocab(model));
}

FERRY_API int ferry_n_embd(struct llama_model * model) {
    return llama_model_n_embd(model);
}

FERRY_API int ferry_n_ctx(struct llama_context * ctx) {
    return (int) llama_n_ctx(ctx);
}

FERRY_API int ferry_eos_token(struct llama_model * model) {
    return llama_vocab_eos(llama_model_get_vocab(model));
}

FERRY_API int ferry_token_to_piece(struct llama_model * model, int token, char * buf, int buf_size) {
    const struct llama_vocab * vocab = llama_model_get_vocab(model);
    return llama_token_to_piece(vocab, token, buf, buf_size, 0, true);
}

/* Clears the KV cache so a fresh sequence can be generated. */
FERRY_API void ferry_reset(struct llama_context * ctx) {
    llama_memory_clear(llama_get_memory(ctx), true);
}

/*
 * Decode n_tokens and copy the next-position logits (vocab-size floats) into out.
 * Positions are tracked automatically by the context memory (call ferry_reset first
 * for a new sequence, then feed the prompt, then one token at a time). Returns the
 * number of logits written (vocab size), or -1 on decode error.
 */
FERRY_API int ferry_eval(struct llama_context * ctx, struct llama_model * model,
                         const int * tokens, int n_tokens, float * out, int out_size) {
    struct llama_batch batch = llama_batch_get_one((int *) tokens, n_tokens);
    if (llama_decode(ctx, batch) != 0) return -1;

    int n_vocab = llama_vocab_n_tokens(llama_model_get_vocab(model));
    if (n_vocab > out_size) n_vocab = out_size;

    float * logits = llama_get_logits_ith(ctx, -1);
    memcpy(out, logits, (size_t) n_vocab * sizeof(float));

    return n_vocab;
}

/* Tokenize text into caller-allocated out_tokens; returns token count (negative on overflow). */
FERRY_API int ferry_tokenize(struct llama_model * model, const char * text, int * out_tokens, int max_tokens, int add_bos) {
    const struct llama_vocab * vocab = llama_model_get_vocab(model);
    return llama_tokenize(vocab, text, (int) strlen(text), out_tokens, max_tokens, add_bos != 0, true);
}

/*
 * Greedy generation. Decodes the prompt, then argmax-samples up to max_new
 * tokens, writing decoded UTF-8 into out (NUL-terminated). Returns bytes
 * written, or -1 on decode error.
 */
FERRY_API int ferry_generate_greedy(struct llama_context * ctx, struct llama_model * model,
                                    const int * prompt_tokens, int n_prompt,
                                    int max_new, char * out, int out_size) {
    const struct llama_vocab * vocab = llama_model_get_vocab(model);
    int n_vocab = llama_vocab_n_tokens(vocab);
    int out_len = 0;

    struct llama_batch batch = llama_batch_get_one((int *) prompt_tokens, n_prompt);
    if (llama_decode(ctx, batch) != 0) return -1;

    for (int i = 0; i < max_new; i++) {
        float * logits = llama_get_logits_ith(ctx, -1);
        int best = 0;
        float best_v = logits[0];
        for (int t = 1; t < n_vocab; t++) {
            if (logits[t] > best_v) { best_v = logits[t]; best = t; }
        }

        if (llama_vocab_is_eog(vocab, best)) break;

        char piece[512];
        int pl = llama_token_to_piece(vocab, best, piece, (int) sizeof(piece), 0, true);
        if (pl > 0 && out_len + pl < out_size - 1) {
            memcpy(out + out_len, piece, pl);
            out_len += pl;
        }

        int tok = best;
        struct llama_batch b2 = llama_batch_get_one(&tok, 1);
        if (llama_decode(ctx, b2) != 0) break;
    }

    out[out_len] = 0;
    return out_len;
}

/*
 * Like ferry_eval, but returns only the top-k tokens by logit (descending), as parallel
 * arrays out_ids[k] / out_logits[k]. This keeps the expensive per-token work (over the full
 * ~150k vocab) in C, so PHP sampling operates on a tiny set. Returns the number written (<= k),
 * or -1 on decode error.
 */
FERRY_API int ferry_eval_topk(struct llama_context * ctx, struct llama_model * model,
                              const int * tokens, int n_tokens, int k,
                              int * out_ids, float * out_logits) {
    struct llama_batch batch = llama_batch_get_one((int *) tokens, n_tokens);
    if (llama_decode(ctx, batch) != 0) return -1;

    int n_vocab = llama_vocab_n_tokens(llama_model_get_vocab(model));
    float * logits = llama_get_logits_ith(ctx, -1);

    if (k > n_vocab) k = n_vocab;
    if (k < 1) k = 1;

    int count = 0;

    for (int t = 0; t < n_vocab; t++) {
        float v = logits[t];

        if (count < k) {
            int pos = count;
            while (pos > 0 && out_logits[pos - 1] < v) {
                out_logits[pos] = out_logits[pos - 1];
                out_ids[pos] = out_ids[pos - 1];
                pos--;
            }
            out_logits[pos] = v;
            out_ids[pos] = t;
            count++;
        } else if (v > out_logits[k - 1]) {
            int pos = k - 1;
            while (pos > 0 && out_logits[pos - 1] < v) {
                out_logits[pos] = out_logits[pos - 1];
                out_ids[pos] = out_ids[pos - 1];
                pos--;
            }
            out_logits[pos] = v;
            out_ids[pos] = t;
        }
    }

    return count;
}
