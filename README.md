# FerryAI — native AI inference for PHP

**Run ONNX, GGUF, and RubixML models directly in PHP — no Python, no HTTP microservices, no Docker sidecars.**
One API, full FFI bridge to native engines. Inference-only. PHP 8.5+.

```php
use FerryAI\AI;

AI::config(['backend' => 'onnx', 'device' => 'cpu']);

$vec = AI::embed('Hello world');
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
| **ONNX** | 🟢 Production | Embeddings, classification, any `.onnx`. Tested: ONNX Runtime 1.27.0, all-MiniLM-L6-v2 (384d), similarity 0.79. |
| **Llama** | 🟢 Probe OK | Library loads, `llama_backend_init()` works. Full inference needs GGUF model + struct declarations from `llama.h`. See setup below. |
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

Vectors live in native `vector(dim)` columns with `jsonb` metadata; verified against
PostgreSQL 18.3 + pgvector 0.8.4. The SQLite backend transparently uses **sqlite-vec**
(vec0 virtual tables) for native KNN when the extension is available, and falls back to a
pure-PHP brute-force scan otherwise — filters always work. See
[`examples/21-postgres-vector.php`](examples/21-postgres-vector.php) and
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
`classify()`/`moderate()`/`predict()`/`chat()` reuse pooled instances. Model downloads
(`Downloader`, `HuggingFaceClient`) retry transient failures via `RetryHandler`, and
`ModelPool` can opt into cross-worker weight sharing (`ext-shmop`). See
[`examples/22-observability.php`](examples/22-observability.php).

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

## Dependencies & downloads

What each capability needs, why, where it goes, and the exact source. Versions intentionally
omitted — always take the latest compatible build from the linked source.

| Capability | PHP side (composer / ext) | Native artifact to download & why | Where it goes / how to enable | Source |
|-----------|---------------------------|-----------------------------------|-------------------------------|--------|
| Embeddings / classification (ONNX, CPU) | `ankane/onnxruntime` (auto) + `ext-ffi` | ONNX Runtime shared lib `onnxruntime.{dll,so,dylib}` (+ `onnxruntime_providers_shared`) — FFI loads it to run models | Extract into `vendor/ankane/onnxruntime/lib/…/lib/` | github.com/microsoft/onnxruntime/releases · onnxruntime.ai |
| ONNX model file | — | `model.onnx` + `tokenizer.json` — the actual network + vocab | Any dir; point config / `FERRY_AI_MODEL_DIR` at it | huggingface.co (e.g. `sentence-transformers/all-MiniLM-L6-v2`) |
| GPU for ONNX (CUDA / TensorRT) | — | ONNX Runtime **GPU** build **+** NVIDIA CUDA Toolkit **+** cuDNN (**+** TensorRT for the TRT provider) | Replace the CPU lib with the GPU package; select provider via `backends.onnx.providers` | onnxruntime releases (gpu zip) · developer.nvidia.com/cuda-downloads · developer.nvidia.com/cudnn · developer.nvidia.com/tensorrt |
| LLM chat / streaming (llama.cpp) | `ext-ffi` | llama.cpp shared libs `llama.*` + `ggml*.*` (+ deps) — the inference engine | Extract to a dir; set `FERRY_AI_LLAMA_LIB` and add the dir to `PATH` | github.com/ggml-org/llama.cpp/releases |
| GGUF model file | — | `*.gguf` quantized weights + tokenizer | Any dir; `backends.llama.model_path` | huggingface.co (e.g. `bartowski/*-GGUF`) |
| GPU for llama.cpp | — | CUDA-enabled llama.cpp build (`*-bin-win-cuda-*`) **+** NVIDIA CUDA Toolkit | Same as llama.cpp above; set `backends.llama.n_gpu_layers` | github.com/ggml-org/llama.cpp/releases · developer.nvidia.com/cuda-downloads |
| Native HuggingFace tokenizer (optional; pure-PHP BPE/WordPiece works without) | `ext-ffi` | tokenizers-cpp shared lib — fast native tokenization | `FERRY_AI_TOKENIZERS_LIB` | github.com/mlc-ai/tokenizers-cpp |
| Vector store — SQLite (default) | `ext-pdo_sqlite` (bundled with PHP) | — (pure-PHP brute-force search) | works out of the box | sqlite.org (bundled) |
| Vector ANN — sqlite-vec | `ext-pdo_sqlite` | `vec0.{dll,so,dylib}` loadable extension — native KNN in SQLite | `FERRY_AI_VEC_EXTENSION_LIB` = path to the lib | github.com/asg017/sqlite-vec/releases |
| Vector store — PostgreSQL | `ext-pdo_pgsql` | PostgreSQL server **+** the **pgvector** extension — production ANN (`<=>`, HNSW/IVFFlat) | `FERRY_AI_VECTOR_DRIVER=pgsql` + `FERRY_AI_PG_DSN/USER/PASSWORD` (or `vector.*` config) | postgresql.org/download · github.com/pgvector/pgvector |
| CPU tabular ML (RubixML) | `rubix/ml` via `composer require` — **isolated** (its amphp/parallel ^1 conflicts with psalm's amphp) | `.rbm` serialized estimator | `FERRY_AI_RUBIXML_AUTOLOAD` = path to the isolated `vendor/autoload.php` | github.com/RubixML/ML · github.com/RubixML/Tensor |
| Model Hub / HuggingFace download | `ext-curl`, `ext-zip`, `ext-sodium` (Ed25519 verify) | models pulled from the Hub on demand | `FERRY_AI_MODEL_CACHE` = cache dir | huggingface.co |
| Shared model weights across workers | `ext-shmop` | — | `model_pool.shared_memory=true` | PHP bundled |

> CUDA note: GPU support means shipping a **CUDA-enabled native build** (ONNX Runtime GPU or a
> llama.cpp CUDA build) alongside the **NVIDIA CUDA Toolkit** and **cuDNN** on the host. GPU paths
> are **not yet verified** in this repo — see `docs/DEBT_REPORT.md` §14.

### Quick checks

```powershell
# ONNX Runtime available?
php -r "require 'vendor/autoload.php'; echo (new FerryAI\OnnxBackend\OnnxBackend())->isAvailable() ? 'OK' : 'FAIL';"

# llama.cpp available?
$env:FERRY_AI_LLAMA_LIB = "C:\llama\llama.dll"; $env:PATH = "C:\llama;" + $env:PATH
php -r "require 'vendor/autoload.php'; echo (new FerryAI\LlamaBackend\LlamaBackend())->isAvailable() ? 'YES' : 'NO';"

# sqlite-vec available?
$env:FERRY_AI_VEC_EXTENSION_LIB = "C:\sqlite-vec\vec0.dll"
php examples/23-sqlite-vec.php
```

> Full llama.cpp inference additionally needs the C struct/function declarations aligned with your
> build's `llama.h`. Generate them with `php bin/generate-ffi.php --header path/to/llama.h`
> (see `docs/DEBT_REPORT.md` §12/§16).

---

## Verified (Windows x64, 2026-07-05)

| Component | Result |
|-----------|--------|
| ONNX Runtime 1.27.0 FFI load | ✅ `isAvailable()`, version, CPU device |
| ONNX inference e2e | ✅ Embed `Hello world` → 384d vector, similarity cat-kitten=0.79 |
| llama.cpp FFI load (build 9873) | ✅ DLL loads, `llama_backend_init()` OK, `supports_mmap()`=YES |
| HuggingFace API | ✅ Qwen3-0.6B found, search works |
| Vector store | ✅ SQLite CRUD, brute-force + sqlite-vec (vec0) native KNN, metadata filter |
| Vector store (Postgres) | ✅ pgvector 0.8.4 native `<=>` search, HNSW index, metadata filter |
| CPU backend | ✅ Tensor math (matmul/transpose/reshape/slice); RubixML `.rbm` predict/proba (isolated) |
| Shared memory (shmop) | ✅ Allocate 2.5B key, attach, detach |
| Async fibers | ✅ Suspend/resume, parallel tasks, timeout 10ms |
| GPU (CUDA) | 🔵 ONNX = CPU build. CUDA DLLs present for llama.cpp |

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
├── ai/            Facade (AI::), backend registry, model pool, metrics, profiler
├── laravel/       Service provider + facade (env-based config)
└── symfony/       Bundle + DI extension
```

---

## Testing

```bash
composer test                # 611 unit tests — pure PHP
composer test-integration    # Integration — needs ONNX Runtime / llama.cpp / PostgreSQL
composer check               # cs-fix + PHPStan lvl8 + Psalm lvl3 + tests — fully green
```

---

## Examples

See [`examples/`](examples/) — 25 standalone scripts covering every capability:
embedding, tokenizer, chat, streaming, RAG, pipeline, vector store (SQLite +
sqlite-vec & PostgreSQL/pgvector), grammar, model hub, profiling, async, model pool,
observability, retry, CPU tensor math + RubixML, benchmarks, Laravel, Symfony.

```bash
set FERRY_AI_MODEL_DIR=D:\FerryAI\all-MiniLM-L6-v2-onnx
php examples/01-hello-embedding.php
```

---

## Documents

| Document | Purpose |
|----------|---------|
| [`docs/SKILL.md`](docs/SKILL.md) | AI coding conventions |
| [`docs/TECHNICAL_SPECIFICATION.md`](docs/TECHNICAL_SPECIFICATION.md) | Architecture |
| [`docs/FILE_TREE.md`](docs/FILE_TREE.md) | Complete file map |
| [`docs/INTERFACE_CONTRACTS.md`](docs/INTERFACE_CONTRACTS.md) | Interface signatures |
| [`docs/BUILD_LOG.md`](docs/BUILD_LOG.md) | Development journal |
| [`docs/DEBT_REPORT.md`](docs/DEBT_REPORT.md) | Technical debt inventory |
| [`docs/EXAMPLES_PLAN.md`](docs/EXAMPLES_PLAN.md) | Examples coverage matrix |
| [`docs/SOURCES.md`](docs/SOURCES.md) | External stack reference |
| [`docs/README.md`](docs/README.md) | Full navigator |

