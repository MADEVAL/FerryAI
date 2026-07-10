# llama.cpp backend

`FerryAI\LlamaBackend\LlamaBackend` runs GGUF LLMs on CPU and GPU via llama.cpp. Because
PHP FFI cannot safely pass llama.cpp's by-value struct params, FerryAI talks to a thin
**`ferry_llama` C wrapper** (see [`native/llama-wrapper`](../../native/llama-wrapper/README.md))
that exposes a flat, pointer-only API.

## What you need

1. **llama.cpp build** — shared libs (`llama.dll` + `ggml.dll` + `ggml-base.dll` +
   `ggml-cpu-*.dll`); for GPU also `ggml-cuda.dll` + CUDA runtime. From
   [ggml-org/llama.cpp releases](https://github.com/ggml-org/llama.cpp/releases).
2. **`ferry_llama.dll`** (or `.so`/`.dylib`) — the C wrapper, built with
   `native/llama-wrapper/build.ps1` (Windows) or `native/llama-wrapper/build.sh` (Linux/macOS).
3. **Matching headers** (same commit as the build): `llama.h`, `ggml.h`, `ggml-cpu.h`,
   `ggml-backend.h`, `ggml-alloc.h`, `ggml-opt.h`, `gguf.h`.
4. A **GGUF model**, e.g. `bartowski/Qwen2.5-0.5B-Instruct-GGUF` from HuggingFace.
5. For GPU: **CUDA Toolkit** → <https://developer.nvidia.com/cuda-downloads>.

## Build the wrapper

**Windows:**
```powershell
powershell -File native/llama-wrapper/build.ps1 -LlamaDir C:\llama
```

**Linux:**
```bash
bash native/llama-wrapper/build.sh /path/to/llama
```

The build script generates DLL import libs from the shared libs, compiles `ferry_llama.c`,
and links against the llama.cpp libraries. It auto-detects CUDA when `ggml-cuda.dll`/`.so`
is present.

## Configure

```php
// Point at the wrapper DLL (or set FERRY_AI_LLAMA_LIB to llama.dll in the same dir)
putenv('FERRY_AI_LLAMA_WRAPPER=C:\llama\ferry_llama.dll');
putenv('PATH=C:\llama;' . getenv('PATH'));   // DLLs must be on PATH

AI::config([
    'backend'  => 'llama',
    'device'   => 'cuda',        // or 'cpu'
    'backends' => [
        'llama' => [
            'model_path'     => 'C:\llama\qwen-0.5b.Q4_K_M.gguf',
            'n_gpu_layers'   => 25,      // how many layers to offload (0 = CPU only)
            'n_ctx'           => 2048,   // context window size
        ],
    ],
]);
```

**Linux:**
```bash
export FERRY_AI_LLAMA_WRAPPER=/path/to/llama/ferry_llama.so
export LD_LIBRARY_PATH=/path/to/llama:$LD_LIBRARY_PATH

# For CUDA:
export FERRY_AI_LLAMA_WRAPPER=/path/to/llama-cuda/ferry_llama.so
export LD_LIBRARY_PATH=/path/to/llama-cuda:/usr/local/cuda/lib64:$LD_LIBRARY_PATH
```

## Chat & stream

```php
// Complete generation
$reply = AI::chat([
    ['role' => 'user', 'content' => 'What is the capital of France?'],
]);
echo $reply->text;                         // "The capital of France is Paris."

// Token-by-token streaming
foreach (AI::stream([['role' => 'user', 'content' => 'Count to 5']]) as $piece) {
    echo $piece;                           // 1 2 3 4 5
    @ob_flush(); @flush();
}
```

See [streaming](../streaming.md) and [`examples/03-chat.php`](../../examples/03-chat.php).

## Sampling

Sampling is per-request via chat options. FerryAI ships these samplers:

| Sampler | Class | Behavior |
|---------|-------|----------|
| `greedy` | `GreedySampler` | Always pick highest-probability token (temperature=0) |
| `top_p` | `TopPSampler` | Nucleus sampling — pick from tokens whose cumulative prob ≤ top_p |
| `top_k` | `TopKSampler` | Sample from top-K most likely tokens |
| `grammar` | `GrammarSampler` | Constrained sampling — only tokens that match the grammar |

The default strategy: `temperature == 0` → greedy; `temperature > 0` → top-p (nucleus).
A native top-K pre-filter keeps all samplers fast.

```php
// Greedy (deterministic)
AI::chat($msgs, ['temperature' => 0]);

// Top-K (force k=10 candidates)
AI::chat($msgs, ['sampler' => 'top_k']);

// Grammar-constrained (GBNF)
AI::chat($msgs, ['grammar' => 'root ::= "yes" | "no"']);

// Grammar-constrained (JSON Schema auto-converted)
AI::chat($msgs, ['grammar' => [
    'type' => 'object',
    'properties' => [
        'city'   => ['type' => 'string'],
        'country' => ['type' => 'string'],
    ],
    'required' => ['city', 'country'],
]]);
```

GBNF grammar support includes: literals, character classes (`[a-z]`), alternation (`|`),
sequences, grouping (`( )`), repetition (`*`, `+`, `?`), rule references, and `#` comments.

`SamplerMath` provides the shared `softmax`/`argmax`/`weightedIndex`/`applyPenalties` routines.
`SamplerFactory` maps string names to sampler instances (`create()`, `forParams()`).

## ChatFormatter templates

`ChatFormatter` converts `ChatMessage[]` arrays into the format the LLM expects:

| Template | Format | Auto-detected for |
|----------|--------|-------------------|
| `chatml` (default) | `<|im_start|>role\ncontent<|im_end|>` | Qwen (fallback) |
| `llama3` | `<|begin_of_text|><|start_header_id|>role<|end_header_id|>\n\ncontent<|eot_id|>` | LLaMA 3 |
| `mistral` | `<s>[INST] content [/INST] content</s>` | Mistral / Mixtral |
| `gemma` | `<start_of_turn>role\ncontent<end_of_turn>` | Gemma |
| `phi` | `<|role|>\ncontent<|end|>` | Phi |

Override with `backends.llama.chat_template` config or let `ChatFormatter::detectFormat()`
pick from the model name.

## Architecture

| Class | Purpose |
|-------|---------|
| `LlamaBackend` | Implements `Backend` — `isAvailable()`, `load()`, `version()` |
| `LlamaModel` | Implements `Model` (`run()`); adds `runComplete()`/`runStream()` for chat + streaming |
| `FerryLlama` | Single FFI wrapper for the `ferry_llama` C API (all pointer args) |
| `NativeLlamaRuntime` | Production FFI implementation of `LlamaRuntimeInterface` |
| `NativeLlamaSession` | Production session wrapper around the native context |
| `ChatFormatter` | Converts chat messages to the LLM's expected format |
| `LlamaContextParams` | Value object for llama context parameters |
| `LlamaModelParams` | Value object for llama model parameters |

## Performance

llama.cpp inference runs on both CPU and CUDA GPU, on Windows and Linux. Native
`llama-cli` / `llama-bench` provide the raw engine throughput; `AI::chat()` adds the
PHP FFI layer on top. GPU offload (`n_gpu_layers`) significantly increases throughput
over CPU-only inference.

Model is pooled — the first call pays the model-load cost, subsequent calls are much
faster (context is re-created per chat but weights are shared).

## Notes

- Runs in a normal PHP process. Under PHPUnit, ggml global constructors conflict with
  PHPUnit's output buffering — integration tests run in a subprocess.
- `ferry_llama.dll` is machine-built and not committed. Either build it (above) or download a
  prebuilt `ferry_llama-<platform>.<ext>` from the [GitHub Releases](https://github.com/MADEVAL/FerryAI/releases)
  page — see [`native/llama-wrapper/README.md`](../../native/llama-wrapper/README.md#prebuilt-binaries).
- `model_path` in config should point at the GGUF file, not a directory.

## Appendix: Why the C wrapper?

The historical investigation confirmed that `llama_model_load_from_file`
crashes when called directly via PHP FFI. The function takes `struct llama_model_params` **by value**
(64 bytes on x64). While PHP FFI can infer the struct layout from the CDEF, the DLL is compiled with
**Clang 20.1.8** on GitHub Actions, while PHP FFI on Windows uses the MSVC-compatible ABI. The
mismatch causes `GGML_ASSERT` failures in `llama-hparams.cpp`.

The `ferry_llama` wrapper solves this by accepting all parameters as **pointers**, which are ABI-safe:
`ferry_llama_model_load(const char *path, const struct llama_model_params *params)` — the struct
pointer is trivially 8 bytes, regardless of ABI.
