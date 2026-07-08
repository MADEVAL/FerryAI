# ferry-ai/inference-llama-backend

llama.cpp inference backend for [FerryAI](https://github.com/MADEVAL/FerryAI), the inference-only
runtime for PHP 8.5+. Provides LLM chat, token streaming, sampling and grammar-constrained generation.

## Installation

```bash
composer require ferry-ai/inference-llama-backend
```

## What's inside

- **`LlamaBackend`** — implements the `Backend` contract; loads `.gguf` models via llama.cpp.
- **`LlamaModel`** — chat / completion, with `runStream()` returning a `\Generator` of tokens.
- **`LlamaRuntimeInterface`** / **`NativeLlamaRuntime`**, **`FFI\FerryLlama`** — the FFI seam to the
  native `ferry_llama` wrapper. Only plain PHP values cross the seam.
- **`ChatFormatter`** — applies chat templates to `ChatMessage` sequences.
- **Grammar & sampling** — GBNF grammar matcher plus samplers (greedy, nucleus, top-k pre-filter).

## Requirements

- PHP >= 8.5
- `ferry-ai/inference-core`
- `ext-ffi` at runtime
- Native libraries: the `ferry_llama` wrapper plus `libllama` / `libggml*`, and a `.gguf` model.
  Prebuilt wrappers are published via GitHub Releases; see the main repository for setup.

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/backends/llama.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/backends/llama.md) and
[docs/streaming.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/streaming.md).
