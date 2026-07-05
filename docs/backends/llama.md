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
