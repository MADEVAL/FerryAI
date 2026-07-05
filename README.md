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
| **CPU Native** | 🟢 Always | Pure-PHP fallback for `.rbm` models. No native deps. |

---

## Vector store

Two interchangeable backends behind the same `VectorStore` contract — pick per environment:

| Backend | Status | Search | Best for |
|---------|--------|--------|----------|
| **SQLite** | 🟢 Production | PHP brute-force (cosine/euclidean/dot) | Dev, demos, small collections, zero-setup |
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
PostgreSQL 18.3 + pgvector 0.8.4. See [`examples/21-postgres-vector.php`](examples/21-postgres-vector.php).

---

## Quick Start

```bash
composer require ferry-ai/php-inference
```

Requirements: PHP 8.5+, `ext-ffi`, `ext-json`, `ext-hash`, `ext-fileinfo`.

### ONNX Runtime (for embeddings)

```powershell
# Download from https://github.com/microsoft/onnxruntime/releases (≥1.18)
# Extract to vendor\ankane\onnxruntime\lib\onnxruntime-win-x64-1.27.0\lib\
# Place onnxruntime.dll and onnxruntime_providers_shared.dll there.

# Test:
php -r "require 'vendor/autoload.php'; echo (new FerryAI\OnnxBackend\OnnxBackend())->isAvailable() ? 'OK' : 'FAIL';"
```

### llama.cpp (for LLM chat)

```powershell
# Download from https://github.com/ggml-org/llama.cpp/releases (build 9873 tested)
# Extract to C:\llama

$env:FERRY_AI_LLAMA_LIB = "C:\llama\llama.dll"
$env:PATH = "C:\llama;" + $env:PATH         # Required: ggml.dll, llama-common.dll etc

# Test:
php -r "
require 'vendor/autoload.php';
\$b = new FerryAI\LlamaBackend\LlamaBackend();
echo \$b->isAvailable() ? 'YES' : 'NO';
"

# For full inference:
# 1. Copy llama.h from your release to reference
# 2. Add struct definitions (llama_model_params, llama_context_params) to LlamaCpp::CDEF
# 3. Add inference functions (llama_model_load_from_file, llama_decode, etc.)
# 4. Set model path: AI::config(['backends' => ['llama' => ['model_path' => 'model.gguf']]])
# 5. AI::chat([['role' => 'user', 'content' => 'Hello']])
```

---

## Verified (Windows x64, 2026-07-05)

| Component | Result |
|-----------|--------|
| ONNX Runtime 1.27.0 FFI load | ✅ `isAvailable()`, version, CPU device |
| ONNX inference e2e | ✅ Embed `Hello world` → 384d vector, similarity cat-kitten=0.79 |
| llama.cpp FFI load (build 9873) | ✅ DLL loads, `llama_backend_init()` OK, `supports_mmap()`=YES |
| HuggingFace API | ✅ Qwen3-0.6B found, search works |
| Vector store | ✅ SQLite CRUD, brute-force search, metadata filter |
| Vector store (Postgres) | ✅ pgvector 0.8.4 native `<=>` search, HNSW index, metadata filter |
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
├── cpu-backend/   Always-available CPU fallback
├── ai/            Facade (AI::), backend registry, model pool, metrics, profiler
├── laravel/       Service provider + facade (env-based config)
└── symfony/       Bundle + DI extension
```

---

## Testing

```bash
composer test                # 580 unit tests — pure PHP
composer test-integration    # Integration — needs ONNX Runtime / llama.cpp / PostgreSQL
composer check               # cs-fix + PHPStan lvl8 + Psalm lvl3 + tests
```

---

## Examples

See [`examples/`](examples/) — 21 standalone scripts covering every capability:
embedding, tokenizer, chat, streaming, RAG, pipeline, vector store (SQLite &
PostgreSQL/pgvector), grammar, model hub, profiling, async, model pool, retry,
benchmarks, Laravel, Symfony.

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
