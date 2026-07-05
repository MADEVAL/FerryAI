# FerryAI Examples — Coverage Reference

> **All 25 examples are implemented** in `examples/`. This document is the capability coverage matrix.
> Each example is a standalone PHP script: `php examples/<name>.php`.

---

## Coverage Map

Numbers refer to FerryAI capabilities. Every capability must appear in at least one example.

| # | Capability | Package | Tier |
|---|-----------|---------|------|
| 1 | AI::config, backend/device selection | ai | 1 |
| 2 | AI::embed / embedBatch | ai / embedding | 1 |
| 3 | AI::similarity | ai / embedding | 1 |
| 4 | AI::tokenizer | ai / tokenizer | 1 |
| 5 | AI::chat (LLM) | ai / llama-backend | 1 |
| 6 | AI::stream (LLM streaming) | ai / llama-backend | 1 |
| 7 | AI::classify | ai / onnx-backend | 2 |
| 8 | AI::moderate | ai / onnx-backend | 2 |
| 9 | AI::predict (tabular) | ai / cpu-backend | 2 |
| 10 | AI::vector (VectorStore CRUD) | ai / vector | 2 |
| 11 | VectorStore search + metadata filter | vector | 2 |
| 12 | VectorStore export/import | vector | 3 |
| 13 | AI::hub (download, cache, verify) | ai / model-hub | 3 |
| 14 | Hub::downloadWithProgress, prune | model-hub | 3 |
| 15 | Format detection (ONNX, GGUF, safetensors) | model-hub | 3 |
| 16 | AI::pipeline | ai / pipeline | 2 |
| 17 | Pipeline stages: chunk, tokenize, embed, normalize, store, classify, filter, transform | pipeline | 2 |
| 18 | StreamResponse (SSE, NDJSON) | ai | 3 |
| 19 | SharedMemoryManager | ai | 3 |
| 20 | ModelPool | ai | 3 |
| 21 | AsyncInference (Fibers) | ai | 3 |
| 22 | Metrics + Profiler | ai | 3 |
| 23 | RetryHandler | core | 3 |
| 24 | PlatformDetector | core | 3 |
| 25 | NativeBinaryManager | ai | 3 |
| 26 | LLM with GBNF grammar | llama-backend | 2 |
| 27 | LLM with JSON Schema → GBNF | llama-backend | 2 |
| 28 | Samplers (greedy, top-k, top-p) | llama-backend | 2 |
| 29 | ChatFormatter (llama3, chatml, mistral, gemma, phi) | llama-backend | 2 |
| 30 | Laravel integration | laravel | 4 |
| 31 | Symfony integration | symfony | 4 |

---

## Tier 1 — Essentials (5 examples)

New users start here. Each example works with ONNX Runtime (the always-working backend).

### `01-hello-embedding.php` — Your first embedding
**Covers:** 1, 2, 3
- Configure ONNX backend
- Load `all-MiniLM-L6-v2` model
- `AI::embed('Hello world')` → get 384-dim vector
- `AI::similarity('cat', 'kitten')` → > 0.8
- `AI::embedBatch(['a', 'b', 'c'])` → 3 vectors

### `02-tokenizer.php` — Tokenize and decode
**Covers:** 4
- Load tokenizer from `tokenizer.json` file
- `encode()` → token IDs
- `decode()` → back to text
- `countTokens()`, `chunk()` with overlap
- `encodeBatch()` with padding + attention mask
- `vocabSize()`, `specialTokens()`

### `03-chat.php` — LLM chat with llama.cpp backend
**Covers:** 5, 29
- Configure llama backend + GGUF model
- ChatFormatter auto-detection → pick template
- `AI::chat([system, user messages])` → `GenerationResult`
- Print tokensGenerated, tokensPrompt, durationMs
- Show ChatFormatter templates (llama3, chatml, mistral)

### `04-streaming.php` — Real-time token streaming
**Covers:** 6, 18
- `AI::stream($messages)` → Generator
- Print tokens as they arrive
- `StreamResponse::toSse($generator)` → Server-Sent Events
- `StreamResponse::toNdjson($generator)` → NDJSON
- Demonstrate with a `foreach` loop

### `05-embeddings-compare.php` — Semantic search from scratch
**Covers:** 1, 2, 3
- Define a list of documents (titles + descriptions)
- Embed all documents
- Embed a query
- Rank documents by cosine similarity
- Print top-3 results with scores

---

## Tier 2 — Ecosystem (6 examples)

RAG, pipelines, vector search, classification, grammars.

### `06-rag.php` — Retrieval-Augmented Generation
**Covers:** 1, 2, 3, 10, 11, 5
- `AI::vector('knowledge_base')` → create collection
- Add document chunks with metadata to vector store
- Embed user query
- `$store->search($queryVector, k: 5)` → relevant chunks
- `$store->search($queryVector, k: 5, filter: ['source' => ['eq' => 'docs']])`
- Feed chunks into `AI::chat()` as context
- Return grounded answer

### `07-pipeline.php` — Composable processing pipeline
**Covers:** 16, 17
- Build pipeline: `TokenizeStage → EmbedStage → NormalizeStage → StoreStage`
- Run on array of texts
- Run on Generator (lazy)
- `FilterStage` → skip short texts
- `TransformStage` → preprocess
- `$pipeline()` — PHP 8.5 pipe operator
- Print pipeline stages with names

### `08-classification.php` — Text and image classification
**Covers:** 7, 8, 9
- Load classification ONNX model
- `AI::classify('positive review')` → label + confidence
- `AI::moderate('some text')` → categories + flagged
- `AI::predict(['feature1' => 0.5, 'feature2' => 1.2])` → CPU-native prediction
- Show `ClassificationResult` structure

### `09-grammar.php` — Structured LLM output with GBNF
**Covers:** 26, 27, 28
- Create `GbnfGrammar::fromString('root ::= "yes" | "no"')`
- Create `GbnfGrammar::fromJsonSchema($schema)` → JSON Schema to GBNF
- `GrammarSampler` enforces valid output
- `TopPSampler`, `TopKSampler`, `GreedySampler`
- `SamplerFactory::create('top-p')`
- Show how grammar guarantees valid JSON from LLM

### `10-vector-store.php` — Full vector database workflow
**Covers:** 10, 11, 12
- `CollectionManager::create('products', 384)`
- `add()`, `addBatch()`, `count()`
- `search()` with cosine/euclidean/dot metrics
- `search()` with `MetadataFilter` (eq, gt, in, and, or)
- `update()`, `delete()`, `deleteByFilter()`
- `ExportImport::toJson()` / `ExportImport::fromJson()` / `ExportImport::toCsv()`
- `iterator()`, `clear()`

### `11-multilingual.php` — Multilingual embeddings
**Covers:** 1, 2, 3
- Load `multilingual-e5-small` model
- Embed same concept in English, русский, 中文, العربية
- Show cross-lingual similarity (all pairs)

---

## Tier 3 — Production (7 examples)

Performance, monitoring, model management, infrastructure.

### `12-model-hub.php` — Download, cache, verify models
**Covers:** 13, 14, 15
- `AI::hub()->download('sentence-transformers/all-MiniLM-L6-v2')` → local path
- `AI::hub()->cached($modelId)` → check cache
- `AI::hub()->downloadWithProgress()` → progress Generator
- `AI::hub()->verify($path, $sha256)` → integrity check
- `AI::hub()->introspect($path)` → metadata without loading
- `AI::hub()->prune($maxBytes)` → LRU eviction
- `AI::hub()->cacheSize()`
- `FormatDetector::detect($path)` → onnx/gguf/safetensors
- `AiArchive::create()` / `extract()` / `validate()`

### `13-profiling.php` — Measure everything
**Covers:** 22
- `Profiler::start('embed')` / `Profiler::end('embed')`
- Run 100 embeddings, capture stats
- `Profiler::report()` → count, total_ms, avg_ms, min_ms, max_ms
- `Metrics::increment('requests', ['backend' => 'onnx'])`
- `Metrics::timing('inference_ms', $duration, ['model' => 'MiniLM'])`
- `Metrics::report()` → counters + timing histograms

### `14-async.php` — Non-blocking inference with Fibers
**Covers:** 21
- `AsyncInference::runAsync(fn() => AI::embed('text'))` → Fiber
- Run 3 embeddings in parallel via `runParallel()`
- `wait($fiber, timeoutMs: 5000)` with timeout handling
- Compare sync vs async timing

### `15-model-pool.php` — Preload and reuse models
**Covers:** 19, 20
- `ModelPool` → `put()` loaded models
- `acquire()` → reuse across requests
- `evict()` → unload when memory full
- `size()`, `memoryUsage()`
- `SharedMemoryManager` → share weights across FPM workers
- `allocateModel()`, `attachModel()`, `detachModel()`

### `16-retry.php` — Resilient operations
**Covers:** 23, 24, 25
- `RetryHandler::retry(fn, maxAttempts: 3, backoff: 'exponential')`
- Retry HuggingFace download on network error
- `RetryHandler::shouldRetry()` — skips ModelLoadException
- `PlatformDetector::os()`, `arch()`, `platformKey()`
- `NativeBinaryManager::resolve('onnxruntime')` → auto-find library
- `NativeBinaryManager::download('onnxruntime', '1.27.0')`

### `17-benchmark.php` — Throughput and latency benchmarks
**Covers:** 2, 3, 22
- Embedding: single / batch / throughput (vectors/sec)
- Similarity: pairs/sec
- Tokenizer: tokens/sec
- Vector store: insert/sec, search/sec at 10k vectors
- Report as Markdown table

### `18-stream-response.php` — HTTP streaming for web apps
**Covers:** 6, 18
- `StreamResponse::create($generator)` → PSR-7 Response
- `StreamResponse::toSse()` → Server-Sent Events
- `StreamResponse::toNdjson()` → NDJSON
- Show Content-Type headers
- Ready for Laravel/Symfony controllers

---

## Tier 4 — Frameworks (2 examples)

### `19-laravel.php` — Laravel integration
**Covers:** 1, 30
- `config/ferry-ai.php` → env-based configuration
- `AIServiceProvider::register()` → `AI::config()`
- `\AI::embed()` via Facade
- `\AI::chat()` in a controller context
- Artisan commands: `ferry-ai:models:list`, `ferry-ai:tokenize`

### `20-symfony.php` — Symfony integration
**Covers:** 1, 31
- `config/packages/ferry_ai.yaml` → configuration tree
- `AIBundle::boot()` → `AI::config()`
- `FerryAIExtension::load()` → merge configs
- Service autowiring
- Usage in a controller

---

## Capability Coverage Matrix

| Example | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 | 16 | 17 | 18 | 19 | 20 | 21 | 22 | 23 | 24 | 25 | 26 | 27 | 28 | 29 | 30 | 31 |
|---------|---|---|---|---|---|---|---|---|---|---|---|---|----|----|----|----|----|----|----|----|----|----|----|----|----|----|----|----|----|----|----|----|
| 01-hello | ✓ | ✓ | ✓ |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 02-tokenizer |   |   |   | ✓ |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 03-chat |   |   |   |   | ✓ |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    | ✓ |    |    |
| 04-streaming |   |   |   |   |   | ✓ |   |   |   |    |    |    |    |    |    |    |    | ✓ |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 05-embeddings-compare | ✓ | ✓ | ✓ |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 06-rag | ✓ | ✓ | ✓ |   | ✓ |   |   |   |   | ✓ | ✓ |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 07-pipeline |   |   |   |   |   |   |   |   |   |    |    |    |    |    |    | ✓ | ✓ |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 08-classification |   |   |   |   |   |   | ✓ | ✓ | ✓ |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 09-grammar |   |   |   |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    | ✓ | ✓ | ✓ |    |    |    |
| 10-vector-store |   |   |   |   |   |   |   |   |   | ✓ | ✓ | ✓ |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 11-multilingual | ✓ | ✓ | ✓ |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 12-model-hub |   |   |   |   |   |   |   |   |   |    |    |    | ✓ | ✓ | ✓ |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 13-profiling |   |   |   |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    | ✓ |    |    |    |    |    |    |    |    |    |
| 14-async |   |   |   |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    | ✓ |    |    |    |    |    |    |    |    |    |    |
| 15-model-pool |   |   |   |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    | ✓ | ✓ |    |    |    |    |    |    |    |    |    |    |    |
| 16-retry |   |   |   |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    |    | ✓ | ✓ | ✓ |    |    |    |    |    |    |    |
| 17-benchmark |   | ✓ | ✓ |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    | ✓ |    |    |    |    |    |    |    |    |    |
| 18-stream-response |   |   |   |   |   | ✓ |   |   |   |    |    |    |    |    |    |    |    | ✓ |    |    |    |    |    |    |    |    |    |    |    |    |    |
| 19-laravel | ✓ |   |   |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    | ✓ |    |
| 20-symfony | ✓ |   |   |   |   |   |   |   |   |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    |    | ✓ |

---

## File Structure

```
examples/
├── 01-hello-embedding.php
├── 02-tokenizer.php
├── 03-chat.php
├── 04-streaming.php
├── 05-embeddings-compare.php
├── 06-rag.php
├── 07-pipeline.php
├── 08-classification.php
├── 09-grammar.php
├── 10-vector-store.php
├── 11-multilingual.php
├── 12-model-hub.php
├── 13-profiling.php
├── 14-async.php
├── 15-model-pool.php
├── 16-retry.php
├── 17-benchmark.php
├── 18-stream-response.php
├── 19-laravel.php
├── 20-symfony.php
├── 21-postgres-vector.php      # PostgreSQL + pgvector: native ANN, HNSW, metadata filter
├── 22-observability.php        # Metrics/Profiler/Logger, ModelPool eviction, RetryHandler, shared memory
├── 23-sqlite-vec.php           # SQLite + sqlite-vec (vec0): native KNN, opt-in index sync, brute-force fallback
├── 24-rubix-cpu.php            # CpuNativeTensor arithmetic + RubixML .rbm inference (isolated process)
├── 25-ffi-generator.php        # Generate FFI cdef from a C header (strip comments/macros/extern)
└── README.md                  # How to run, prerequisites, expected output
```

## Conventions

1. Every file is a standalone PHP script: `#!/usr/bin/env php` shebang, `declare(strict_types=1)`, `require __DIR__ . '/../vendor/autoload.php'`
2. Exits with code 0 on success, prints `=== OK ===` at the end
3. If a native dependency is missing, prints `=== SKIP: <reason> ===` and exits 0
4. Uses `FerryAI\AI` facade for 90% of calls — shows the simplest API surface
5. Drops to lower-level classes (`OnnxBackend`, `CollectionManager`, `RetryHandler`) only when showcasing capabilities the facade doesn't expose
6. No comments — code is self-documenting through clear variable/method names
7. All output is plain text with structured sections: `=== Section Title ===`
8. **Milestone policy:** every notable feature or backend added to the project MUST ship
   with (a) a runnable example in `examples/` and (b) at least a short section (~5+ lines)
   in the root `README.md`. "No example + README entry ⇒ the milestone is not done."


