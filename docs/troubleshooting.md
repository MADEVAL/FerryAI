# Troubleshooting

## `AI::config() must be called before using the facade`

Call `AI::config([...])` once before any `AI::embed/chat/...`. In tests, `AI::reset()` between cases.

## ONNX: `BackendNotAvailableException` / `isAvailable() === false`

The ONNX Runtime shared library is not found. Download it and place `onnxruntime.{dll,so,dylib}`
(+ `onnxruntime_providers_shared`) where `ankane/onnxruntime` looks
(`vendor/ankane/onnxruntime/lib/…/lib/`). Verify:

```php
php -r "require 'vendor/autoload.php'; var_dump((new FerryAI\OnnxBackend\OnnxBackend())->isAvailable());"
```

See [backends/onnx](backends/onnx.md).

## Embedding: model / tokenizer not found

`backends.embedding.model_path` must be a directory with `model.onnx` **and** `tokenizer.json`
(or a `.onnx` file with a sibling `tokenizer.json`). Override the tokenizer with
`backends.embedding.tokenizer_path`.

## llama: `ferry_llama wrapper not found`

Set `FERRY_AI_LLAMA_WRAPPER` to `ferry_llama.dll` (or `FERRY_AI_LLAMA_LIB` to `llama.dll` in the
same directory) and add that directory to `PATH`. Build the wrapper with
`native/llama-wrapper/build.ps1`. See [backends/llama](backends/llama.md).

## llama: “no backends are loaded”

The ggml backend DLLs (`ggml-cpu-*.dll`, `ggml-cuda.dll`) must sit next to `llama.dll`; the wrapper
loads them from that directory (`ferry_load_backends`). Ensure the whole llama.cpp build is in one
folder on `PATH`.

## llama under PHPUnit crashes (`GGML_ASSERT` / premature end)

Loading the native library runs ggml’s global constructors, which conflict with PHPUnit’s
output/exception handling. Run LLM code in a **standalone process** (the integration test uses a
subprocess harness). See `docs/DEBT_REPORT.md` §12.

## GPU not used

- llama.cpp: needs a CUDA build (`ggml-cuda.dll` + CUDA runtime) and `device: cuda`. Check
  `llama-bench -ngl 99` shows `loaded CUDA backend`.
- ONNX: the bundled runtime is CPU-only here; a GPU build + CUDA/cuDNN is required (untested — §14).

## sqlite-vec not active

Set `FERRY_AI_VEC_EXTENSION_LIB` to the `vec0` library; needs `ext-pdo_sqlite` and PHP 8.4+
(`Pdo\Sqlite::loadExtension`). Without it, search falls back to brute force. See
[vector-store](vector-store.md).

## PostgreSQL: `extension "vector" is not available`

Install pgvector into the PostgreSQL server (build it against your PG version) before
`CREATE EXTENSION vector`. See [vector-store](vector-store.md).

## Slow grammar-constrained generation

Grammar sampling must scan the full vocabulary each step, so it is slower than greedy/top-p/top-k.
This is expected; keep grammars small.

## RubixML: `RubixML is not installed`

`rubix/ml` conflicts with the dev toolchain’s amphp; install it in an **isolated** location and set
`FERRY_AI_RUBIXML_AUTOLOAD`. See `docs/DEBT_REPORT.md` §15.

Still stuck? The honest status of every feature and its limits is in
[`DEBT_REPORT.md`](DEBT_REPORT.md).
