# FerryAI Examples

Standalone PHP scripts demonstrating every FerryAI capability. Each file runs independently.
Verified on Windows (11 x64, RTX 4060) and WSL2 (Ubuntu 24.04, PHP 8.5.8) — **26/26 pass
on both**.

## Prerequisites

```bash
composer install
```

### Models & native libraries

The examples expect one of two setups:

**Windows** — everything under `D:\FerryAI` (the default):
```
D:\FerryAI\
├── all-MiniLM-L6-v2-onnx/     model.onnx + tokenizer.json (embeddings)
├── qwen-0.5b.Q4_K_M.gguf     GGUF model (LLM chat/stream)
├── ferry_llama.dll            llama.cpp C wrapper
├── llama.dll, ggml*.dll       llama.cpp build + CUDA backend
├── vec0.dll                   sqlite-vec loadable extension
└── onnxruntime-gpu/           ONNX Runtime GPU build (optional)
```

**WSL / Linux** — libraries under `/opt` (override via env vars):
```
/opt/llama/                    CPU llama build + ferry_llama.so
/opt/llama-cuda/               CUDA llama build
/opt/sqlite-vec/vec0.so        sqlite-vec
/opt/onnxruntime-gpu/          ONNX Runtime GPU build (optional)
/opt/rubixml/                  RubixML isolated install
/mnt/d/FerryAI/                Models on the Windows drive
```

The examples default to these paths; override them with environment variables.

### Environment variables

| Variable | Default (Windows) | Default (WSL/Linux) | What it points at |
|----------|------------------|---------------------|-------------------|
| `FERRY_AI_MODEL_DIR` | `D:\FerryAI\all-MiniLM-L6-v2-onnx` | `/mnt/d/FerryAI/all-MiniLM-L6-v2-onnx` | `model.onnx` + `tokenizer.json` |
| `FERRY_AI_LLAMA_DIR` | `D:\FerryAI` | `/opt/llama` | `ferry_llama.{dll,so}` + llama/ggml libs |
| `FERRY_AI_LLAMA_MODEL` | `D:\FerryAI\qwen-0.5b.Q4_K_M.gguf` | `/mnt/d/FerryAI/qwen-0.5b.Q4_K_M.gguf` | GGUF file |
| `FERRY_AI_LLAMA_DEVICE` | `cpu` | `cpu` | `cpu` or `cuda` |
| `FERRY_AI_VEC_EXTENSION_LIB` | `D:\FerryAI\vec0.dll` | `/opt/sqlite-vec/vec0.so` | sqlite-vec loadable extension |


## Tier 1 — Essentials

| # | File | What it shows | Needs native libs |
|---|------|--------------|-------------------|
| 01 | `hello-embedding.php` | First embedding, batch, similarity, L2 normalization | ONNX Runtime |
| 02 | `tokenizer.php` | Encode/decode, special tokens, chunking, batch encoding | None |
| 03 | `chat.php` | LLM chat, ChatFormatter templates, multi-turn | llama.cpp |
| 04 | `streaming.php` | Token-by-token streaming, SSE, NDJSON | llama.cpp |
| 05 | `embeddings-compare.php` | Semantic search from scratch, cosine ranking | ONNX Runtime |

## Tier 2 — Ecosystem

| # | File | What it shows | Needs |
|---|------|--------------|-------|
| 06 | `rag.php` | RAG: embed chunks → vector store → search → metadata filter | ONNX Runtime |
| 07 | `pipeline.php` | Transform/Filter/Normalize/Chunk stages, pipe operator | Tokenizer file |
| 08 | `classification.php` | Classify, moderate, tabular prediction | ONNX Runtime + models |
| 09 | `grammar.php` | GBNF parsing, JSON Schema → GBNF, samplers (greedy/top-k/top-p), grammar-constrained vs free generation | llama.cpp (optional) |
| 10 | `vector-store.php` | CRUD, search, MetadataFilter operators, export/import | None |
| 11 | `multilingual.php` | Embedding in 7 languages, cross-lingual similarity matrix | ONNX Runtime |

## Tier 3 — Production

| # | File | What it shows | Needs |
|---|------|--------------|-------|
| 12 | `model-hub.php` | Format detection, SHA-256, Ed25519, AiArchive, HuggingFace API | ext-sodium (optional) |
| 13 | `profiling.php` | Profiler start/end/report, Metrics counters + timings | ONNX Runtime |
| 14 | `async.php` | AsyncInference: Fiber suspend/resume, parallel, timeout | ONNX Runtime |
| 15 | `model-pool.php` | ModelPool put/acquire/evict, SharedMemoryManager allocate/detach | ext-shmop (optional) |
| 16 | `retry.php` | RetryHandler exponential/linear, PlatformDetector, NativeBinaryManager | None |
| 17 | `benchmark.php` | Throughput: embed, similarity, tokenizer, vector store | ONNX Runtime |
| 18 | `stream-response.php` | SSE and NDJSON formatting for HTTP streaming | None |
| 22 | `observability.php` | Metrics/Profiler/Logger wrapper, ModelPool eviction, RetryHandler, shared memory | None |

## Tier 4 — Frameworks

| # | File | What it shows | Needs |
|---|------|--------------|-------|
| 19 | `laravel.php` | ServiceProvider config + register/boot, Facade proxy, config file | None |
| 20 | `symfony.php` | Bundle boot, Configuration tree, DI extension load | None |

## Tier 5 — Storage backends

| # | File | What it shows | Needs |
|---|------|--------------|-------|
| 21 | `postgres-vector.php` | PostgreSQL + pgvector: CRUD, native `<=>` ANN search, metadata filter, HNSW index | ext-pdo_pgsql + pgvector |
| 23 | `sqlite-vec.php` | SQLite + sqlite-vec (vec0): native KNN, opt-in index sync, brute-force fallback with filters | ext-pdo_sqlite + vec0 lib |
| 24 | `rubix-cpu.php` | CpuNativeTensor arithmetic (matmul/transpose/reshape/slice); RubixML `.rbm` inference (isolated) | rubix/ml (optional) |
| 25 | `ffi-generator.php` | Generate `\FFI::cdef()`-ready declarations from a C header (strip comments/macros/extern) | None |
| 26 | `facade-embed.php` | `AI::embed()`/`similarity()` via `backends.embedding.model_path` config; PSR-7 `StreamResponse::create()` | ONNX Runtime + model (embed); nyholm/psr7 (stream) |

## Running

### Windows

```powershell
# The example defaults point to D:\FerryAI — just run:
php examples/01-hello-embedding.php
php examples/03-chat.php           # LLM chat (ferry_llama.dll + GGUF)
php examples/23-sqlite-vec.php     # sqlite-vec native KNN

# Override model paths when needed:
$env:FERRY_AI_MODEL_DIR = "C:\models\all-MiniLM-L6-v2-onnx"
php examples/01-hello-embedding.php
```

### WSL / Linux

```bash
# Point at the models on the Windows drive
export FERRY_AI_MODEL_DIR=/mnt/d/FerryAI/all-MiniLM-L6-v2-onnx
export FERRY_AI_LLAMA_DIR=/opt/llama
export FERRY_AI_LLAMA_MODEL=/mnt/d/FerryAI/qwen-0.5b.Q4_K_M.gguf
export FERRY_AI_VEC_EXTENSION_LIB=/opt/sqlite-vec/vec0.so

# ONNX embeddings (CPU or GPU — see below)
php examples/01-hello-embedding.php

# LLM chat on CPU
FERRY_AI_LLAMA_DEVICE=cpu php examples/03-chat.php

# LLM chat on CUDA (needs /opt/llama-cuda/ferry_llama.so)
export FERRY_AI_LLAMA_DIR=/opt/llama-cuda FERRY_AI_LLAMA_DEVICE=cuda
php examples/03-chat.php

# sqlite-vec native KNN
php examples/23-sqlite-vec.php
```

### ONNX GPU on WSL

The ONNX examples use whatever execution provider is available. To force GPU (CUDA):

```bash
# Copy the GPU build into the vendor directory (do this once)
cp /opt/onnxruntime-gpu/onnxruntime-linux-x64-gpu_cuda13-*/lib/libonnxruntime*.so* \
   vendor/ankane/onnxruntime/lib/onnxruntime-linux-x64-*/lib/
cp /opt/onnxruntime-gpu/onnxruntime-linux-x64-gpu_cuda13-*/lib/libonnxruntime_providers_*.so \
   vendor/ankane/onnxruntime/lib/onnxruntime-linux-x64-*/lib/

# Point LD_LIBRARY_PATH at the vendor lib + CUDA toolkit
export LD_LIBRARY_PATH=vendor/ankane/onnxruntime/lib/onnxruntime-linux-x64-*/lib:/usr/local/cuda/lib64:$LD_LIBRARY_PATH

# Verify
php -r "require 'vendor/autoload.php'; \$b=new FerryAI\OnnxBackend\OnnxBackend(); echo implode(',',array_map(fn(\$d)=>\$d->value,\$b->availableDevices()));"
# → cuda,cpu

# Then run ONNX examples as above — they'll pick CUDA automatically
php examples/01-hello-embedding.php
```

If the CUDA runtime libraries (`libcurand`, `libcufft`, `libcudnn`) aren't installed
via `apt`, they can be extracted from `.deb` packages without root — see the
**ONNX GPU on WSL** section in the main [`README.md`](../README.md).

All examples exit 0 on success, skip gracefully if dependencies are missing, and print
`=== OK ===` at the end.
