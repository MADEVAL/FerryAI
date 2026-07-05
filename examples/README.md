# FerryAI Examples

Standalone PHP scripts demonstrating every FerryAI capability. Each file runs independently.

## Prerequisites

```bash
composer install
```

For embedding/ONNX examples — download `all-MiniLM-L6-v2` from HuggingFace:
```bash
set FERRY_AI_MODEL_DIR=D:\FerryAI\all-MiniLM-L6-v2-onnx
```

The directory must contain: `model.onnx`, `tokenizer.json`, `tokenizer_config.json`.

For LLM examples — a GGUF model and validated llama.cpp FFI binding (see README).

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
| 09 | `grammar.php` | GBNF grammar, JSON Schema → GBNF, samplers (greedy/top-k/top-p) | None |
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

## Running

```bash
# Set model directory (for ONNX examples)
set FERRY_AI_MODEL_DIR=D:\FerryAI\all-MiniLM-L6-v2-onnx

# Run any example
php examples/01-hello-embedding.php
php examples/09-grammar.php
php examples/16-retry.php

# Examples that need llama.cpp will gracefully skip
php examples/03-chat.php
# → SKIP: set FERRY_AI_LLAMA_MODEL to a GGUF file path
```

All examples exit 0 on success, skip gracefully if dependencies are missing, and print `=== OK ===` at the end.
