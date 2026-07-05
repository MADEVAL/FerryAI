# llama.cpp backend

`FerryAI\LlamaBackend\LlamaBackend` runs GGUF LLMs on CPU and GPU via llama.cpp. Because PHP FFI
cannot safely pass llama.cpp's by-value struct params, FerryAI talks to a thin **`ferry_llama`
C wrapper** (see [`native/llama-wrapper`](../../native/llama-wrapper/README.md)) that exposes a
flat, pointer-only API.

## What you need

1. **llama.cpp Windows build** — `llama.dll`, `ggml.dll`, `ggml-base.dll`, `ggml-cpu-*.dll`; for GPU
   also `ggml-cuda.dll` + CUDA runtime. From
   [ggml-org/llama.cpp releases](https://github.com/ggml-org/llama.cpp/releases)
   (CUDA build: `llama-bXXXX-bin-win-cuda-*.zip`).
2. **CUDA Toolkit** for GPU: <https://developer.nvidia.com/cuda-downloads>.
3. **Matching headers** (same commit) + **`ferry_llama.dll`** built with
   [`native/llama-wrapper/build.ps1`](../../native/llama-wrapper/build.ps1).
4. A **GGUF model**, e.g. `bartowski/Qwen2.5-0.5B-Instruct-GGUF` from HuggingFace.

## Configure

```php
putenv('FERRY_AI_LLAMA_WRAPPER=D:\FerryAI\ferry_llama.dll');  // or FERRY_AI_LLAMA_LIB=…\llama.dll
putenv('PATH=D:\FerryAI;' . getenv('PATH'));                   // dir with the DLLs

AI::config([
    'backend'  => 'llama',
    'device'   => 'cuda',        // or 'cpu'
    'backends' => ['llama' => ['model_path' => 'D:\FerryAI\qwen-0.5b.Q4_K_M.gguf']],
]);
```

## Chat & stream

```php
$reply = AI::chat([['role' => 'user', 'content' => 'Capital of France?']]);
echo $reply->text;                      // "The capital of France is Paris."

foreach (AI::stream([['role' => 'user', 'content' => 'Count to 5']]) as $piece) {
    echo $piece;                        // 1 2 3 4 5
}
```

See [streaming](../streaming.md) and [`examples/03-chat.php`](../../examples/03-chat.php).

## Sampling

Per request via chat options:

- `temperature: 0` → greedy (deterministic); `> 0` → nucleus (top-p).
- `sampler: 'greedy'|'top_k'|'top_p'|'grammar'` to force one.
- `grammar: '<gbnf>'` (or a JSON-Schema array) → strict grammar-constrained output
  (`root ::= "yes" | "no"` yields exactly `yes`/`no`). Supported GBNF subset: literals, char
  classes, `|`, sequences, `( )`, `* + ?`, rule references, `#` comments.

A native top-k pre-filter keeps greedy/top-p/top-k fast; grammar must scan the full vocab and is
slower.

## Verified

RTX 4060, llama.cpp build 9873: CPU and GPU chat/stream work; the model is pooled across calls
(second chat ~11 ms vs ~470 ms first). Status & limitations: `docs/DEBT_REPORT.md` §12/§14.

## Notes

- Runs in a normal PHP process; under PHPUnit the ggml global constructors conflict, so the
  integration test drives chat in a subprocess.
- `ferry_llama.dll` is machine-built and not committed — build it or ship a prebuilt binary.

---

## Appendix: FFI ABI investigation (historical)

The direct `llama.dll` → PHP FFI path was investigated and found to crash on
`llama_model_load_from_file` — this is what the wrapper solves. Key findings kept for context.

### Environment (build 9873, commit `a4107133a`)

| Item | Value |
|------|-------|
| Native CLI | `llama-cli -m model.gguf -p "Hello" -n 5` → "Hello! How can I" at **309 t/s** ✅ |
| `FFI::cdef` | Loads when the DLL dir is on `PATH` |
| `llama_backend_init` | No crash (loaded backend from `ggml-cpu-x64.dll`) |
| `supports_mmap` | `true` |
| Model metadata read | ✅ 38 KV pairs, 290 tensors, tokenizer (151,936 tokens, BPE) |
| GPU | RTX 4060, `ggml-cuda.dll` present; CUDA backend loads and is verified via the wrapper |

### The crash: struct-by-value ABI

`llama_model_load_from_file` takes `struct llama_model_params` **by value** (64 bytes on x64).
PHP FFI infers the layout from the CDEF, but the DLL was compiled with **Clang 20.1.8** on
GitHub Actions, while PHP FFI uses the platform-default C ABI (MSVC-compatible on Windows).
The mismatch causes `llama-hparams.cpp:55: fatal error`.

Layout verified via `FFI::sizeof`:
```
Offset  Size  Field
0       8     devices (void*)
8       8     tensor_buft_overrides (void*)
16      4     n_gpu_layers (int32)
20      4     split_mode (int32)
24      4     main_gpu (int32)
28      4     _pad (explicit padding)
32      8     tensor_split (float*)
40      8     progress_callback (fn ptr)
48      8     progress_callback_user_data (void*)
56      8     kv_overrides (void*)
Total: 64 bytes
```

Despite byte-level match, the crash persisted → the flat C wrapper (`ferry_llama`) was chosen
as the reliable solution.

### PHPUnit conflict

Under PHPUnit the DLL crashes on `FFI::cdef()` with `GGML_ASSERT(prev != ggml_uncaught_exception)`
— a C++ exception-state conflict between PHPUnit's output buffering and GGML's global constructors.
Standalone PHP scripts work fine. The integration test therefore runs the harness in a subprocess.
