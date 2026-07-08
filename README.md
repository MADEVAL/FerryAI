<p align="center">
  <img src="img/ferryai_banner.svg" alt="FerryAI" width="100%">
</p>

# FerryAI — native AI inference for PHP

**Run ONNX, GGUF, and RubixML models directly in PHP — no Python, no HTTP microservices, no Docker sidecars.**
One API, full FFI bridge to native engines. Inference-only. PHP 8.5+.

[![CI](https://github.com/MADEVAL/FerryAI/actions/workflows/ci.yml/badge.svg)](https://github.com/MADEVAL/FerryAI/actions/workflows/ci.yml)
[![Version](https://img.shields.io/github/v/tag/MADEVAL/FerryAI?label=version&color=blue)](https://github.com/MADEVAL/FerryAI/tags)
[![PHP](https://img.shields.io/badge/php-8.5%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE.md)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](phpstan.neon)
[![Psalm](https://img.shields.io/badge/Psalm-level%203-brightgreen.svg)](psalm.xml)

> **Status: early release (`v0.1.0`).** The public API is stabilizing and may change before `1.0` —
> pin a version and skim the [CHANGELOG](CHANGELOG.md) when upgrading. Code quality is production-grade
> (PHPStan level 8, Psalm level 3, full unit suite green on Windows + Linux).

## Contents

- [Quick example](#quick-example)
- [Why FerryAI](#why-ferryai)
- [Backends](#backends)
- [Vector store](#vector-store)
- [Observability & model pool](#observability--model-pool)
- [Install](#install)
- [Dependencies & downloads](#dependencies--downloads)
- [LLM on CPU & GPU (llama.cpp)](#llm-on-cpu--gpu-llamacpp)
- [Capabilities](#capabilities)
- [Packages](#packages)
- [Testing](#testing)
- [Examples](#examples)
- [Documentation](#documentation)
- [Contributing & license](#contributing--license)

---

## Quick example

```php
use FerryAI\AI;

AI::config([
    'backend' => 'onnx',
    'device' => 'cpu',
    'backends' => ['embedding' => ['model_path' => '/path/to/all-MiniLM-L6-v2-onnx']],
]);

$vec = AI::embed('Hello world');              // reads model.onnx + tokenizer.json from the dir
echo $vec->dimension;                         // 384

$sim = AI::similarity('cat', 'kitten');       // 0.79

$store = AI::vector('docs');
$store->add('doc1', $vec->vector, ['title' => 'Getting Started']);
$hits = $store->search(AI::embed('hello')->vector, k: 5);

$results = AI::pipeline()
    ->pipe(new TransformStage(strtoupper(...)))
    ->pipe(new FilterStage(fn($x) => strlen($x) > 3))
    ->run(['hi', 'hello', 'hey']);
```

Chat with a local LLM in three lines:

```php
AI::config(['backend' => 'llama', 'backends' => ['llama' => ['model_path' => '/models/qwen.gguf']]]);

echo AI::chat('Explain PHP FFI in one sentence.');   // full reply
foreach (AI::stream('Write a haiku about ferries.') as $token) { echo $token; }
```

---

## Why FerryAI

| | FerryAI | Python sidecar |
|---|---|---|
| Deployment | One PHP process. `composer require` | Python runtime + HTTP server + process manager |
| Latency | Zero-copy FFI → sub-ms overhead | HTTP round-trip per inference |
| Memory | Shared weights across workers (shmop) | Duplicated per process |
| Debugging | PHP stack traces, xdebug | Cross-process tracing |
| Type safety | PHPStan level 8 + Psalm level 3 | mypy (optional) |

FerryAI loads native shared libraries (`onnxruntime.dll`, `llama.dll`) directly via PHP FFI —
the same C APIs that Python uses. No subprocess, no shell_exec, no Python.

---

## Backends

| Backend | Status | What it does |
|---------|--------|-------------|
| **ONNX** | 🟢 Production | Embeddings, classification, any `.onnx`. Runs on CPU (and CUDA GPU when the runtime + cuDNN/curand/cufft are present); e.g. all-MiniLM-L6-v2 produces 384d vectors. |
| **Llama** | 🟢 CPU + GPU | Real chat/generation via a thin `ferry_llama` C wrapper over llama.cpp; runs on CPU and CUDA GPU on Windows and Linux. See [`native/llama-wrapper`](native/llama-wrapper). |
| **CPU Native** | 🟢 Always | Pure-PHP tensor math (add/sub/mul/matmul/transpose/reshape/slice) + RubixML `.rbm` inference (optional). No native deps for tensor ops. |

---

## Vector store

Two interchangeable backends behind the same `VectorStore` contract — pick per environment:

| Backend | Status | Search | Best for |
|---------|--------|--------|----------|
| **SQLite** | 🟢 Production | PHP brute-force, or native KNN via **sqlite-vec** (vec0) when `FERRY_AI_VEC_EXTENSION_LIB` is set | Dev, demos, embedded, single-file |
| **PostgreSQL + pgvector** | 🟢 Production | Native `<=>` / `<->` / `<#>`, HNSW / IVFFlat indexes | Production, large collections, concurrent access |

```php
use FerryAI\AI;

// Opt in via config or FERRY_AI_VECTOR_DRIVER=pgsql (default: sqlite)
AI::config(['vector' => [
    'driver' => 'pgsql',
    'dsn' => 'pgsql:host=127.0.0.1;port=5432',
    'user' => 'postgres', 'password' => 'postgres',
]]);

$store = AI::vector('docs');                       // PostgresCollection (implements VectorStore)
$store->add('doc1', $vec->vector, ['lang' => 'en']);
$hits = $store->search($query, k: 5, filter: ['lang' => ['eq' => 'en']]);
```

The SQLite backend transparently uses **sqlite-vec** (vec0 virtual tables) for native KNN when the
extension is available, and falls back to a pure-PHP brute-force scan otherwise — filters always work.
See [`examples/21-postgres-vector.php`](examples/21-postgres-vector.php) and
[`examples/23-sqlite-vec.php`](examples/23-sqlite-vec.php).

---

## Observability & model pool

Cross-cutting instrumentation is applied at the facade layer (backends stay isolated) and is
**off by default** — enable per channel via config:

```php
AI::config(['observability' => ['metrics' => true, 'profiling' => true, 'logging' => true]]);

AI::embed('hello');                 // automatically timed, counted and logged
print_r(FerryAI\Metrics::report()); // counters + timing histograms
print_r(FerryAI\Profiler::report());// per-operation count/avg/min/max
```

`AI::warmup([...])` preloads models into a shared `ModelPool` (memory-bounded LRU eviction);
`classify()`/`moderate()`/`predict()`/`chat()` reuse pooled instances. Model downloads retry
transient failures via `RetryHandler`, and `ModelPool` can opt into cross-worker weight sharing
(`ext-shmop`). See [`examples/22-observability.php`](examples/22-observability.php).

---

## Install

```bash
composer require ferry-ai/php-inference
```

Base requirements: **PHP 8.5+**, `ext-ffi`, `ext-json`, `ext-hash`, `ext-fileinfo`.

Everything else is **optional and on-demand** — install only what a feature needs.
FerryAI degrades gracefully (pure-PHP fallback or a clear "not available") when a native
library, extension, or model is missing. Canonical, version-checked source list:
[`docs/SOURCES.md`](docs/SOURCES.md).

---

## Dependencies & downloads

What each capability needs, why, where it goes, and the exact source. Versions intentionally
omitted — always take the latest compatible build from the linked source.

| Capability | PHP side (composer / ext) | Native artifact to download & why | Where it goes / how to enable | Source |
|-----------|---------------------------|-----------------------------------|-------------------------------|--------|
| Embeddings / classification (ONNX) | `ankane/onnxruntime` (auto) + `ext-ffi` | ONNX Runtime shared lib — CPU by default. On Linux run `php -r 'OnnxRuntime\\Vendor::check();'` to auto-download. | Extract into `vendor/ankane/onnxruntime/lib/…/lib/` | github.com/microsoft/onnxruntime/releases · onnxruntime.ai |
| ONNX model file | — | `model.onnx` + `tokenizer.json` — the actual network + vocab | Any dir; point config / `FERRY_AI_MODEL_DIR` at it | huggingface.co (e.g. `sentence-transformers/all-MiniLM-L6-v2`) |
| LLM chat / streaming (llama.cpp) | `ext-ffi` | llama.cpp shared libs `llama.*` + `ggml*.*` (+ deps) — the inference engine | Extract to a dir; set `FERRY_AI_LLAMA_LIB` and add the dir to `PATH` | github.com/ggml-org/llama.cpp/releases |
| GGUF model file | — | `*.gguf` quantized weights + tokenizer | Any dir; `backends.llama.model_path` | huggingface.co (e.g. `bartowski/*-GGUF`) |
| GPU (ONNX CUDA / llama.cpp) | — | GPU-enabled native build **+** NVIDIA CUDA Toolkit (ONNX also needs cuDNN/curand/cufft) | See the GPU setup guide in [`DOCUMENTATION.md`](DOCUMENTATION.md) | onnxruntime / llama.cpp releases · developer.nvidia.com |
| Native HuggingFace tokenizer (**optional**; pure-PHP BPE/WordPiece works without) | `ext-ffi` | tokenizers-cpp shared lib — optional, pure-PHP BPE/WordPiece covers all types | `FERRY_AI_TOKENIZERS_LIB` | github.com/mlc-ai/tokenizers-cpp |
| Vector store — SQLite (default) | `ext-pdo_sqlite` (bundled with PHP) | — (pure-PHP brute-force search) | works out of the box | sqlite.org (bundled) |
| Vector ANN — sqlite-vec | `ext-pdo_sqlite` | `vec0.{dll,so,dylib}` loadable extension — native KNN in SQLite | `FERRY_AI_VEC_EXTENSION_LIB` = path to the lib | github.com/asg017/sqlite-vec/releases |
| Vector store — PostgreSQL | `ext-pdo_pgsql` | PostgreSQL server **+** the **pgvector** extension — production ANN (`<=>`, HNSW/IVFFlat) | `FERRY_AI_VECTOR_DRIVER=pgsql` + `FERRY_AI_PG_DSN/USER/PASSWORD` (or `vector.*` config) | postgresql.org/download · github.com/pgvector/pgvector |
| CPU tabular ML (RubixML) | `rubix/ml` via `composer require` — **isolated** | `.rbm` serialized estimator | `FERRY_AI_RUBIXML_AUTOLOAD` = path to the isolated `vendor/autoload.php` | github.com/RubixML/ML · github.com/RubixML/Tensor |
| Model Hub / HuggingFace download | `ext-curl`, `ext-zip`, `ext-sodium` (Ed25519 verify) | models pulled from the Hub on demand | `FERRY_AI_MODEL_CACHE` = cache dir | huggingface.co |
| Safetensors models (conversion) | Python 3.10+, `torch`, `safetensors` | `convert_hf_to_gguf.py` from llama.cpp — converts HF safetensors to GGUF | Run once; then point `backends.llama.model_path` at the `.gguf` output | [docs/safetensors-conversion.md](docs/safetensors-conversion.md) |
| Shared model weights across workers | `ext-shmop` | — | `model_pool.shared_memory=true` | PHP bundled |

> **GPU setup** (ONNX CUDA on Windows/Linux, extracting cuDNN/curand/cufft, and the full llama.cpp
> `ferry_llama` build) has a dedicated step-by-step guide in
> [`DOCUMENTATION.md`](DOCUMENTATION.md) → *Quick Start → GPU setup*. The `OnnxBackend::load()`
> CPU-fallback handles a missing GPU runtime automatically.

---

## LLM on CPU & GPU (llama.cpp)

llama.cpp inference runs through PHP on both CPU and CUDA GPU, on Windows and Linux:

| Path | Support |
|------|---------|
| Native `llama-cli` / `llama-bench` (CPU / CUDA) | ✅ standard llama.cpp tooling |
| **`AI::chat()` / `AI::stream()`** (CPU) | ✅ real chat via `LlamaBackend` + wrapper, Windows and Linux |
| **`AI::chat()` / `AI::stream()`** (GPU, CUDA) | ✅ layer offload via a CUDA-enabled llama.cpp build (`GGML_CUDA=ON`) |
| **`AI::chat()`** (safetensors→GGUF models, e.g. Qwen3-0.6B) | ✅ after conversion to GGUF |
| **ONNX embeddings** (GPU, CUDA) | ✅ CUDA provider (`availableDevices = cuda,cpu`) when present |

```php
AI::config([
    'backend'  => 'llama',
    'device'   => 'cuda',   // or 'cpu'
    'backends' => ['llama' => ['model_path' => 'C:\llama\model.gguf', 'n_gpu_layers' => 35]],
]);

echo AI::chat('Summarize FFI in PHP.');
```

Sampling is per request: `temperature: 0` → greedy, `> 0` → nucleus; force one with
`AI::chat($msgs, ['sampler' => 'top_k'])` or `['grammar' => '<gbnf>']`. Point FerryAI at the wrapper
via `FERRY_AI_LLAMA_WRAPPER` (or `FERRY_AI_LLAMA_LIB`) and add that dir to `PATH`.
Full build steps and the flat wrapper API: [`DOCUMENTATION.md`](DOCUMENTATION.md) and
[`native/llama-wrapper/README.md`](native/llama-wrapper/README.md). Runnable:
[`examples/03-chat.php`](examples/03-chat.php), [`examples/04-streaming.php`](examples/04-streaming.php).

---

## Capabilities

| Component | Status |
|-----------|--------|
| ONNX Runtime FFI load | ✅ `isAvailable()`, version, CPU device |
| ONNX inference e2e | ✅ Embed text → 384d vector (all-MiniLM-L6-v2), cosine similarity |
| llama.cpp inference via PHP FFI | ✅ CPU + GPU through the `ferry_llama` wrapper |
| GPU (CUDA) — llama.cpp | ✅ layer offload with a CUDA-enabled llama.cpp build |
| GPU (CUDA) — ONNX | ✅ CUDA provider on Windows and Linux |
| HuggingFace API | ✅ model search + download |
| Vector store | ✅ SQLite CRUD, brute-force + sqlite-vec (vec0) native KNN, metadata filter |
| Vector store (Postgres) | ✅ pgvector native `<=>` search, HNSW index, metadata filter |
| CPU backend | ✅ Tensor math (matmul/transpose/reshape/slice); RubixML `.rbm` predict/proba (isolated) |
| Shared memory (shmop) | ✅ Allocate, attach, detach |
| Async fibers | ✅ Suspend/resume, parallel tasks, timeout |
| Windows / Linux | ✅ Unit tests + static analysis; native backends run on both |

---

## Packages

```
packages/
├── core/          Contracts, enums, value objects, exceptions, AIConfig
├── tensor/        ArrayTensor (pure PHP), BackedTensor, TensorFactory
├── onnx-backend/  ONNX Runtime via ankane/onnxruntime FFI
├── llama-backend/ llama.cpp FFI, samplers (greedy/top-k/top-p/grammar),
│                  GBNF grammar, JSON Schema→GBNF, ChatFormatter (5 templates)
├── tokenizer/     Pure PHP BPE + WordPiece (round-tripping, chunking)
├── embedding/     Mean/CLS/EOS/Max pooling, 4 built-in models
├── vector/        SQLite + PostgreSQL/pgvector store, brute-force & native ANN, metadata filtering
├── model-hub/     HF download, LRU cache, SHA-256+Ed25519, format detection
├── pipeline/      Generator-based stages (8 types)
├── cpu-backend/   Pure-PHP tensor math + optional RubixML (.rbm) tabular inference
├── dataframe/     Tabular data: typed columns, CSV/JSON I/O, Tensor conversion
├── ai/            Facade (AI::), backend registry, model pool, metrics, profiler
├── laravel/       Service provider + facade (env-based config)
└── symfony/       Bundle + DI extension
```

---

## Testing

```bash
composer test                # Unit tests — pure PHP
composer test-integration    # Integration — needs ONNX Runtime / llama.cpp / PostgreSQL
composer check               # cs-fix + PHPStan lvl8 + Psalm lvl3 + tests — fully green
```

---

## Examples

See [`examples/`](examples/) — 26 standalone scripts covering every capability:
embedding, tokenizer, chat, streaming, RAG, pipeline, vector store (SQLite +
sqlite-vec & PostgreSQL/pgvector), grammar, model hub, profiling, async, model pool,
observability, retry, CPU tensor math + RubixML, benchmarks, Laravel, Symfony.

```bash
set FERRY_AI_MODEL_DIR=C:\llama\all-MiniLM-L6-v2-onnx
php examples/01-hello-embedding.php
```

---

## Documentation

Start here: **[`DOCUMENTATION.md`](DOCUMENTATION.md)** — the definitive single-file reference
(architecture, facade API, contracts, GPU setup).

Guides: [getting-started](docs/getting-started.md) ·
[configuration](docs/configuration.md) ·
[ONNX](docs/backends/onnx.md) / [llama.cpp](docs/backends/llama.md) ·
[embedding](docs/embedding.md) · [vector store](docs/vector-store.md) ·
[pipeline](docs/pipeline.md) · [model hub](docs/model-hub.md) ·
[safetensors → GGUF](docs/safetensors-conversion.md) ·
[tokenizer](docs/tokenizer.md) · [streaming](docs/streaming.md) ·
[security](docs/security.md) · [deployment](docs/deployment.md) ·
[Laravel](docs/laravel.md) / [Symfony](docs/symfony.md) ·
[troubleshooting](docs/troubleshooting.md) · [API reference](docs/api-reference.md) ·
[CHANGELOG](CHANGELOG.md)

| Document | Purpose |
|----------|---------|
| [`docs/TECHNICAL_SPECIFICATION.md`](docs/TECHNICAL_SPECIFICATION.md) | Architecture |
| [`docs/FILE_TREE.md`](docs/FILE_TREE.md) | Complete file map |
| [`docs/INTERFACE_CONTRACTS.md`](docs/INTERFACE_CONTRACTS.md) | Interface signatures |
| [`docs/SOURCES.md`](docs/SOURCES.md) | External stack reference |
| [`docs/README.md`](docs/README.md) | Full navigator |

---

## Contributing & license

- **Contributing:** guidelines and workflow in [CONTRIBUTING.md](CONTRIBUTING.md).
- **Security:** report vulnerabilities via [SECURITY.md](SECURITY.md) — please do not open public issues for them.
- **Code of Conduct:** [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
- **License:** MIT — see [LICENSE.md](LICENSE.md).
