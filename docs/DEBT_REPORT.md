# FerryAI — Project Debt Report

> Every stub, mock, skip, gate, and hardcoded value. What, where, why.

---

## 1. FFI Boundary — Mocked by Design

**Reason:** FFI is untyped. Static analysis cannot validate `\FFI::cdef()`. These files are excluded from PHPStan/Psalm and tested via hand-rolled stubs in unit tests. Real path: integration suite only.

| File | What's mocked | Real status |
|------|--------------|-------------|
| `onnx-backend/src/OnnxRuntimeFactory.php` | Unit tests use `MockOnnxRuntime` | Real FFI works (proved manually). Not in automated tests |
| `onnx-backend/src/Runtime/NativeOnnxRuntime.php` | Unit tests use `MockOnnxRuntime` | Works — delegates to `ankane/onnxruntime` |
| `onnx-backend/src/Runtime/NativeOnnxSession.php` | Unit tests use `MockOnnxSession` | Works |
| `llama-backend/src/FFI/LlamaCpp.php` | Unit tests use `MockLlamaRuntime` | DLL loads. `GGML_ASSERT` crash on `llama_backend_init` — ABI mismatch |
| `llama-backend/src/FFI/LlamaContext.php` | Excluded from analysis | Not validated |
| `llama-backend/src/FFI/LlamaBatch.php` | Excluded from analysis | Not validated |
| `llama-backend/src/Runtime/NativeLlamaRuntime.php` | Unit tests use `MockLlamaRuntime` | **`isAvailable()` hardcoded `return false`** — ABI needs per-build validation |
| `llama-backend/src/Runtime/NativeLlamaSession.php` | Unit tests use `MockLlamaSession` | Not validated |
| `tokenizer/src/HuggingFaceTokenizer.php` | Unit tests use pure-PHP fallback | **All encode/decode throw.** Needs `FERRY_AI_TOKENIZERS_LIB` + tokenizers-cpp DLL. Pure-PHP BPE/WordPiece fallback works. |

---

## 2. Hardcoded `return false` / Unavailable Providers

| Class | Method | Value | Why |
|-------|--------|-------|-----|
| `NativeLlamaRuntime` | `isAvailable()` | `false` | llama.cpp ABI not validated per build |
| `CudaProvider` | `isAvailable()` | `false` | GPU probing needs native FFI layer |
| `TensorRtProvider` | `isAvailable()` | `false` | Same |
| `DirectMlProvider` | `isAvailable()` | `false` | Planned, not yet implemented |
| `RocmProvider` | `isAvailable()` | `false` | Planned |
| `OpenVinoProvider` | `isAvailable()` | `false` | Planned |
| `CoreMlProvider` | `isAvailable()` | `PHP_OS_FAMILY === 'Darwin'` | Only macOS, not wired to FFI |

---

## 3. Stub Implementations — Throw on Real Use

| Class | Method | What happens |
|-------|--------|-------------|
| `CpuNativeTensor` | `add()`, `sub()`, `mul()`, `matmul()`, `transpose()`, `reshape()`, `slice()` | `throw new RuntimeException('Not implemented in Phase 3.')` |
| `BackedTensor` | Compute/transform ops | `throw new RuntimeException('Not implemented in Phase 1.')` |
| `OnnxTensor` | Arithmetic/transform ops | `throw new BadMethodCallException` — tensor math belongs to `tensor` package |
| `CpuNativeModel::run()` | Returns | `['output' => [0.5, 0.3, 0.2]]` — hardcoded, not real inference |
| `RubixMLAdapter` | `predict()`, `proba()` | `throw new RuntimeException('RubixML adapter not fully implemented')` |
| `RubixMLAdapter` | `loadModel()` | Works if `rubix/ml` installed — otherwise throws |
| `StreamResponse::create()` | Returns PSR-7 ResponseInterface | ✅ **RESOLVED** — auto-detects a PSR-17 factory (nyholm/guzzle); see §4 |
| `HuggingFaceTokenizer` | `encode()`, `decode()`, all Tokenizer methods | `throw new RuntimeException` — FFI not wired |
| ~~`SqliteVecExtension`~~ | ~~`search()`~~ | ✅ **RESOLVED** — real sqlite-vec (vec0) KNN; see §3a |

---

## 3a. SqliteVecExtension — Implemented (2026-07-05)

### Status: RESOLVED

`SqliteVecExtension` now performs real ANN via the sqlite-vec (`vec0`) loadable extension
instead of returning `[]`.

- **Mechanism:** `SQLiteStore` connects through `Pdo\Sqlite::connect()` (PHP 8.4+, exposes
  `loadExtension`) and exposes `pdo()`. `SqliteVecExtension::load()` loads the `vec0` library
  into that connection. `createIndex()` builds a `vec0` virtual table
  (`embedding float[dim] distance_metric=cosine|l2`) plus a `vecmap_{collection}` side table
  mapping the store's TEXT ids to the integer rowids vec0 requires. `upsert`/`remove`/`clear`
  keep it in sync; `search()` runs `MATCH ... AND k = ? ORDER BY distance` and joins ids back.
- **Opt-in, zero-regression:** enabled only when `FERRY_AI_VEC_EXTENSION_LIB` points at the
  library. `Collection` uses the vec index for unfiltered search and syncs on
  add/update/delete/clear; filtered search and the no-extension path fall back to
  `BruteForceIndex` (default behaviour unchanged).
- **Verified:** runtime probe with `vec0.dll` (sqlite-vec v0.1.10). Unit tests cover the
  disabled path; `tests/Integration/Sqlite/SqliteVecIntegrationTest.php` (4 tests,
  `@group integration`) exercises real load/CRUD/KNN and the `Collection` path — 4/4 pass.
  `examples/23-sqlite-vec.php` runs green.
- **Limitation:** `dot` metric has no vec0 equivalent → mapped to cosine. `vec0` v0.1.10 is
  alpha; brute force remains the default when the extension is absent.

---

## 4. AI Facade — Gated Methods

### Status: config-wiring RESOLVED (2026-07-05)

| Method | State |
|--------|-------|
| `AI::embed()` | ✅ Works via the facade — `backends.embedding.model_path` (dir or `model.onnx`); tokenizer auto-resolved from the same dir (or `backends.embedding.tokenizer_path`); `embedding.pooling`/`embedding.normalize`. Verified e2e (`AiEmbedIntegrationTest`, real all-MiniLM-L6-v2). |
| `AI::similarity()` | ✅ Same wiring; verified (`sim(cat,kitten) > sim(cat,airplane)`). |
| `AI::streamResponse()` | ✅ `StreamResponse::create()` auto-detects a PSR-17 factory (nyholm/psr7 or guzzlehttp/psr7) and returns a real SSE `ResponseInterface`; streams `AI::stream()` tokens. Clear error if no factory. |
| `AI::warmup()` | ✅ Wired to `ModelPool` in §5 (no longer a no-op). |
| `AI::classify()` | ⚠️ Wiring correct; needs a real classification ONNX model at `backends.classify.model_path`. Actionable `ConfigurationException` otherwise. Legitimately model-gated. |
| `AI::moderate()` | ⚠️ Same — needs a moderation ONNX model at `backends.moderate.model_path`. |
| `AI::predict()` | ⚠️ Same — needs a `.rbm` model at `backends.predict.model_path` + RubixML (§15). |
| `AI::chat()` / `AI::stream()` | ⚠️ Needs `backends.llama.model_path` + a validated llama.cpp binding (ABI blocker — §12). |

**Delivered:** `AIFactory::createEmbedder()` now resolves model dir/file → `model.onnx` + `tokenizer.json`
and reads pooling/normalize; `AI::embedder()` reads `backends.embedding.model_path`.
`StreamResponse::create()` returns a PSR-17-backed SSE response (`ai` now `require`s `psr/http-factory`,
`suggest`s nyholm/guzzle). `nyholm/psr7` added as a dev dependency for verification.

**Verification:** `AiEmbedIntegrationTest` (3, real ONNX + all-MiniLM-L6-v2), updated
`StreamResponseTest` (PSR-7 response), example `examples/26-facade-embed.php`.
`composer check` fully green · 615 unit tests.

**Remaining (legitimately model-gated, not code debt):** classify/moderate/predict/chat need the
respective model files; errors are actionable. chat/stream additionally blocked by §12 (llama ABI).

---

## 5. Not Integrated — Code Exists, Never Called

### Status: RESOLVED (2026-07-05)

**Architectural note:** `Metrics`/`Profiler`/`ModelPool`/`SharedMemoryManager`/`NativeBinaryManager`
live in the `ai` package. Backends (`onnx-backend`, `llama-backend`) must not depend on `ai`
(backend isolation + dependency graph), so cross-cutting instrumentation is applied at the
**facade layer**, not inside `Backend::load()`. `Logger`/`RetryHandler` live in `core` and are
used directly by `model-hub`.

| Component | Integration |
|-----------|-------------|
| `Logger` | Emitted by `Observability` (facade) and by `Downloader`/`HuggingFaceClient` (download attempts/failures). Now honours a severity threshold. |
| `Metrics` | `Observability::measure()` records `ai.operation.count` + `ai.operation.ms` around `embed`/`similarity`/`chat`/`classify`/`moderate`/`predict`. Opt-in via `observability.metrics`. |
| `Profiler` | Same wrapper, opt-in via `observability.profiling`. |
| `ModelPool` | `AI` owns a pool; `classify`/`moderate`/`predict`/`chat` load through it (check→load→put). Real `warmup(ids, loader)`, memory-bounded LRU eviction honouring `maxMemoryBytes`. `AI::warmup()` preloads. |
| `NativeBinaryManager` | Implements new `LibraryResolver`; `AIFactory::createBackend(Llama)` best-effort resolves the llama library and sets `FERRY_AI_LLAMA_LIB` when unset (guarded, no download). |
| `RetryHandler` | `Downloader::download()` and `HuggingFaceClient::downloadFile()` wrap network I/O in retry + logging (injectable HTTP seam for tests). |
| `SharedMemoryManager` | Implements new `SharedMemory`; `ModelPool` accepts it and exposes opt-in `shareModel(id, path)` / `isModelShared(id)`; `evict()` detaches. Enabled via `model_pool.shared_memory`. |

**Instrumentation is off by default** (zero overhead, no file writes in tests).

**Limitation (SharedMemoryManager):** only raw model *files* (by path) can be shared across
workers — already-instantiated model objects wrap native handles that cannot be serialized.
`shareModel()` therefore takes a path and is best-effort (returns false when shmop is
unavailable), rather than transparently sharing loaded `Model` instances.

### Verification

- New unit tests: `ObservabilityTest`, extended `ModelPoolTest` (warmup/eviction/shared memory),
  `AIFactoryTest` (llama library resolution), `DownloaderTest` + `HuggingFaceClientTest` (retry),
  `LoggerTest` (level threshold).
- `composer check` → **fully green**: cs-check 0 fixable · PHPStan level 8 **No errors**
  (was 26) · Psalm level 3 No errors · 598 unit tests. Example: `examples/22-observability.php`.

---

## 6. Integration Tests

| Test file | Tests | Status |
|-----------|-------|--------|
| `tests/Integration/Onnx/OnnxRuntimeIntegrationTest.php` | 3 (version, devices, providers) | ✅ Pass with ONNX 1.27.0 |
| `tests/Integration/Onnx/AiEmbedIntegrationTest.php` | 3 (facade embed/similarity/batch, real model) | ✅ Pass with ONNX 1.27.0 + all-MiniLM-L6-v2 |
| `tests/Integration/Llama/LlamaBackendIntegrationTest.php` | 2 (version, devices) | ⊘ Skipped — `NativeLlamaRuntime::isAvailable()` = false |
| `tests/Integration/Postgres/PostgresVectorIntegrationTest.php` | 14 (CRUD, native cosine search, filter, HNSW, AIFactory) | ✅ Pass with PostgreSQL 18.3 + pgvector 0.8.4 |
| `tests/Integration/Sqlite/SqliteVecIntegrationTest.php` | 4 (load, CRUD/KNN, Collection ANN, filtered fallback) | ✅ Pass with sqlite-vec vec0 v0.1.10 |
| `tests/Integration/Rubix/RubixCpuIntegrationTest.php` | 1 (subprocess harness: load .rbm, predict, proba) | ✅ Pass with rubix/ml 2.5.3 (isolated) |

**Missing integration tests:**
- Model loading + inference (ONNX or llama)
- Embedding end-to-end (tokenize → run → pool → normalize)
- Tokenizer end-to-end with real `tokenizer.json`
- Vector store with 10k+ vectors (performance threshold)
- Model Hub download → cache → verify cycle
- HuggingFace API with auth token
- GPU/CUDA availability (when hardware present)

---

## 7. Framework Integrations — Standalone, Not Framework-Tested

| Package | Class | Issue |
|---------|-------|-------|
| `laravel` | `AIServiceProvider` | Does **not** extend `Illuminate\Support\ServiceProvider`. Works standalone. Not tested in real Laravel app. |
| `laravel` | `Facades\AI` | Does **not** extend `Illuminate\Support\Facades\Facade`. Works as proxy. |
| `symfony` | `AIBundle` | Does **not** extend `Symfony\Component\HttpKernel\Bundle\Bundle`. Works standalone. |
| `symfony` | `Configuration` | Does **not** implement `ConfigurationInterface`. Returns plain array. |
| `symfony` | `FerryAIExtension` | Does **not** extend `Extension`. Works standalone. |

**Reason:** AGENTS.md rule 7: "Без жёсткой привязки к Laravel/Symfony". These are standalone adapters that users wire into their framework manually. Framework base classes are optional (`suggest` in composer.json).

---

## 8. DataFrame Package — Not Created

| File | Status |
|------|--------|
| `packages/dataframe/` (6 files) | **Not created** — Phase 4 spec: "только при наличии спроса". No composer.json, no src/, no tests. |

---

## 9. Documentation Debt

### 9a. Dependency / download sources — Addressed (2026-07-05)

Earlier the README (and package docs) never told users **what native libraries/extensions/models
to download, why, where, and from which source** for each capability — a real onboarding gap.

Fixed: README now has **Install** + **Dependencies & downloads** sections — a per-capability
matrix (ONNX Runtime, ONNX/GGUF models, GPU CUDA/TensorRT, llama.cpp, tokenizers-cpp, sqlite-vec,
PostgreSQL/pgvector, RubixML, HuggingFace Hub, shmop) with exact sources and the enabling
env var, cross-linked to `docs/SOURCES.md`. `docs/SOURCES.md` gained pgvector, PostgreSQL and
the NVIDIA CUDA/cuDNN/TensorRT pages.

**Remaining honest gaps:**
- Per-capability guides below are still unwritten (the README matrix is the single source for now).
- `SOURCES.md` recorded sqlite-vec `v0.1.9`, but the verified binary here is `v0.1.10-alpha`
  (pre-1.0 / alpha) — see §3a.
- Packaging: the root `composer.json` `require` lists only `ext-ffi/json/hash/fileinfo`; extensions
  used by sub-packages (`ext-pdo`, `ext-zip`) are declared per-package, and `ext-sodium/curl/shmop/
  pdo_pgsql/pdo_sqlite` are `suggest`-only. Intentional (optional features) but not surfaced in one
  place other than the new README matrix.

| Document | Status |
|----------|--------|
| `docs/specs/` | Empty directory |
| README §Dependencies & downloads | ✅ Written (2026-07-05) |
| `docs/getting-started.md` | Not written |
| `docs/configuration.md` | Not written |
| `docs/backends/onnx.md` | Not written |
| `docs/backends/llama.md` | Not written |
| `docs/embedding.md` | Not written |
| `docs/vector-store.md` | Not written |
| `docs/pipeline.md` | Not written |
| `docs/model-hub.md` | Not written |
| `docs/tokenizer.md` | Not written |
| `docs/streaming.md` | Not written |
| `docs/security.md` | Not written |
| `docs/laravel.md` | Not written |
| `docs/symfony.md` | Not written |
| `docs/deployment.md` | Not written |
| `docs/troubleshooting.md` | Not written |
| `docs/api-reference.md` | Not written |
| `CHANGELOG.md` | Not written |

---

## 10. Dev Tooling Debt

Listed in `composer.json` scripts but not installed:

| Tool | Script | Status |
|------|--------|--------|
| Infection (mutation testing) | `composer mutation` | Not installed |
| Pest | — | Not installed |
| CaptainHook (git hooks) | — | Not installed |
| Monorepo-builder | — | Not installed |
| Composer-normalize | — | Not installed |

---

## 11. Test Coverage Gaps — Unit Tests

| Class | Missing tests | Reason |
|-------|--------------|--------|
| `OnnxRuntimeFactory` | Unit | FFI boundary — excluded by design. Integration only. |
| `OnnxTypeMapper` | ✅ Now tested (Phase 4) | — |
| `HuggingFaceTokenizer` | Unit | FFI boundary — excluded by design |
| Native FFI classes (LlamaCpp, LlamaContext, LlamaBatch, NativeLlamaRuntime, NativeLlamaSession, NativeOnnxRuntime, NativeOnnxSession) | Unit | FFI boundary — excluded by design |

**Remaining untested classes: 7 FFI-boundary files — by design.** All pure-PHP classes tested (568 tests).

---

## Summary Matrix

| Category | Real | Mocked | Gated | Missing |
|----------|------|--------|-------|---------|
| ONNX inference | ✅ e2e proved | — | — | — |
| ONNX providers | CPU ✅ | CUDA/TensorRT/ROCm/OpenVINO/DirectML | GPU probing | — |
| llama.cpp | ✅ CPU + GPU chat/stream via `ferry_llama` wrapper, wired into `LlamaBackend` (§12) | — | Greedy sampler only; standalone-process (PHPUnit ggml ctor conflict) | — |
| Embedding | ✅ e2e via `AI::embed()` facade (config wiring, §4) | — | — | — |
| Tokenizer | ✅ pure-PHP BPE/WordPiece | — | Native tokenizers-cpp | DLL |
| Vector store | ✅ brute-force + sqlite-vec (vec0) native KNN | — | Opt-in via `FERRY_AI_VEC_EXTENSION_LIB` (§3a) | — |
| Model Hub | ✅ HF API, SHA-256, Ed25519, format detect | — | Download cycle | Token for private models |
| Pipeline | ✅ all 8 stages | — | — | — |
| CPU backend | ✅ Backend/Model/Tensor + real tensor math | — | RubixML predict/proba opt-in (§15) | — |
| Shared memory | ✅ allocate/detach | — | Opt-in in ModelPool (§5); file-only sharing | — |
| Async fibers | ✅ suspend/resume/timeout | — | Not tested in RoadRunner | — |
| Model pool | ✅ put/acquire/evict + LRU eviction | — | Integrated into facade (§5) | — |
| Metrics/Profiler | ✅ auto via Observability wrapper (§5) | — | Opt-in (off by default) | — |
| Logger | ✅ used by facade + model-hub (§5) | — | Opt-in; severity threshold | — |
| RetryHandler | ✅ in Downloader + HuggingFaceClient (§5) | — | — | — |
| NativeBinaryManager | ✅ resolve in AIFactory (§5) | — | No auto-download at startup | Download URLs |
| Framework integrations | ✅ standalone classes | Framework base classes | Not tested in real apps | — |
| DataFrame | — | — | — | Entire package (6 files) |
| Documentation | README + BUILD_LOG + design docs | — | — | All usage guides |
| Dev tooling | PHPUnit + PHPStan + Psalm + CS Fixer | — | — | Infection, Pest, CaptainHook |
| Safetensors loading | 🔴 Format detected only | — | — | No loader. Needs Python conversion to ONNX/GGUF. See §13. |
| GPU inference | ✅ llama.cpp CUDA verified (RTX 4060, native + PHP FFI wrapper); 🔴 ONNX GPU untested | — | ONNX = CPU build | See §12, §14 |
| RubixML models | ✅ predict/proba + `.rbm` load (isolated, verified) | — | Opt-in `suggest` (amphp conflict with psalm) | — |
| FFI CDEF generator | ✅ `CdefGenerator` + `bin/generate-ffi.php` (cleans C headers) | — | Not auto-wired into backends; cross-header enum macros need manual resolve (§16) | — |
| WSL / Linux testing | 🔴 Never tested | — | — | All 568 tests pass only on Windows x64. See §17. |
| PostgreSQL vector store | ✅ PostgresStore + PostgresCollection + PostgresVecIndex (pgvector 0.8.4) | — | — | Resolved. See §18. |

---

## 12. llama.cpp FFI — Deep Dive (2026-07-05)

### Status: RESOLVED — wired into LlamaBackend (2026-07-05)

The struct-by-value ABI crash is solved by the flat C wrapper
(`native/llama-wrapper/ferry_llama.dll`), and it is now **wired into the backend**:
`NativeLlamaRuntime` drives llama.cpp through `FerryAI\LlamaBackend\FFI\FerryLlama`
(loads `ferry_llama.dll`, `evaluate()` returns logits, PHP `Sampler`s do the sampling).
`AI::chat()` / `AI::stream()` produce real output on **CPU and GPU**:
- CPU: "What is the capital of France?" → "The capital of France is Paris."
- GPU (`device: cuda`): same, 25/25 layers offloaded on the RTX 4060.
- Streaming: "Count from 1 to 5" → `1 2 3 4 5` token-by-token (+ SSE/NDJSON).

Config: `FERRY_AI_LLAMA_WRAPPER` (or `FERRY_AI_LLAMA_LIB` in the same dir) + that dir on `PATH`;
`backends.llama.model_path`; `device: cpu|cuda`. Verified by
`tests/Integration/Llama/LlamaBackendIntegrationTest` (subprocess harness — 2 tests, CPU+GPU)
and `examples/03-chat.php` / `examples/04-streaming.php`.

**Remaining:**
- `LlamaBackend::load()` always uses `GreedySampler`; wire temperature/top-k/top-p/grammar
  `Sampler`s (the seam already returns logits, so this is a `SamplerFactory` hookup).
- Runs standalone only — under PHPUnit the ggml global ctors conflict, so the integration test
  runs the harness in a subprocess (§12 PHPUnit note).
- `ferry_llama.dll` is machine-built (not committed); ship a prebuilt binary or build in CI.
- Old direct-binding files (`FFI/LlamaCpp.php`, `LlamaContext.php`, `LlamaBatch.php`) are now
  unused and can be removed later.

### Historical detail (the original blocker)

The notes below describe the pre-wrapper investigation of the by-value crash; kept for context.

### Environment

| Item | Value |
|------|-------|
| Build | 9873 (commit `a4107133a`) |
| Compiler | Clang 20.1.8, Windows x86_64 |
| llama.dll | 2.6 MB, `D:\FerryAI\llama.dll` |
| GGUF model | `Qwen2.5-0.5B-Instruct-Q4_K_M.gguf` (380 MB, 24 layers, 151K vocab, Q4_K quant) |
| Model source | `bartowski/Qwen2.5-0.5B-Instruct-GGUF` on HuggingFace |
| GPU | NVIDIA GeForce RTX 4060, 8 GB, driver 591.86 (CUDA backend `ggml-cuda.dll` present) |
| Native CLI test | `llama-cli.exe -m model.gguf -p "Hello" -n 5` → *"Hello! How can I"* at **309 t/s** ✅ |

### What Works

| Step | Detail |
|------|--------|
| `FFI::cdef(CDEF, llama.dll)` | ✅ Loads. Requires `D:\FerryAI` in PATH (dependent DLLs: `ggml.dll`, `ggml-base.dll`, `ggml-cpu-x64.dll`, `llama-common.dll`) |
| `llama_backend_init()` | ✅ No crash when PATH includes DLL directory |
| `llama_print_system_info()` | ⚠️ Returns empty string in this build |
| `llama_supports_mmap()` | ✅ Returns `true` |
| `llama_supports_gpu_offload()` | ✅ Returns `false` (CPU build) |
| `llama_supports_mlock()` | ✅ Returns `true` |
| CPU backend load | ✅ `load_backend: loaded CPU backend from ggml-cpu-x64.dll` |
| Model metadata read | ✅ All 38 KV pairs, 290 tensors, tokenizer (151,936 tokens, BPE) |
| Tensor load start | ✅ `layer 0 assigned to device CPU, is_swa = 0` |

### What Crashes

| Step | Error |
|------|-------|
| `llama_model_load_from_file(path, params)` | `D:/a/llama.cpp/llama.cpp/src/llama-hparams.cpp:55: fatal error` |
| Same with `llama_model_default_params()` | Same crash |
| Same with `FFI::new('llama_model_params')` (zero-init) | Same crash |

### Root Cause Analysis

`llama_model_load_from_file` takes `struct llama_model_params` **by value** (64 bytes on x64).
The struct contains 10 fields including 7 pointers, 3 int32s, and 1 padding int32.

PHP FFI's struct layout for C functions depends on:
1. Platform ABI (Windows x64 = Microsoft x64 calling convention)
2. Struct member alignment rules
3. Compiler-specific padding (Clang vs MSVC vs GCC)

**The `llama.dll` was compiled with Clang 20.1.8 on GitHub Actions (`D:/a/llama.cpp/`).**
PHP FFI uses the platform-default C ABI (MSVC-compatible on Windows). Clang and MSVC
agree on the x64 ABI, BUT:
- Function pointers (`llama_progress_callback`) may have different size/alignment
- `main_gpu` (int32) followed by `tensor_split` (float*) may have different padding

The exact struct layout (verified via `FFI::sizeof`):
```
Offset  Size  Field
0       8     devices (void*)
8       8     tensor_buft_overrides (void*)
16      4     n_gpu_layers (int32)
20      4     split_mode (int32)
24      4     main_gpu (int32)
28      4     _pad (explicit padding)
32      8     tensor_split (float*)
40      8     progress_callback (fn ptr)
48      8     progress_callback_user_data (void*)
56      8     kv_overrides (void*)
Total: 64 bytes
```

Despite the layout matching at the byte level (verified), the crash persists.
Possible explanations:
1. Clang adds tail padding to align struct size to 16 bytes → 80 bytes actual
2. The `llama_model_load_from_file_impl` internal function has a different signature than the public one
3. C++ name mangling or exception handling tables differ between Clang and MSVC
4. The GGML library uses thread-local storage that conflicts with PHP's TLS

### Attempted Fixes

| Attempt | Result |
|---------|--------|
| `llama_model_default_params()` from DLL | Crash |
| `FFI::new()` with zero-init | Crash |
| Removing `llama_batch_get_one` from CDEF | Crash |
| Explicit `int32_t _pad` field | Crash (same offset) |
| Struct size 64 bytes confirmed | No effect |
| Copy ggml-cpu-x64.dll to CWD | CPU backend found, but still crash |
| Set `n_gpu_layers=0` explicitly | No effect |
| Use defaults without modification | No effect |

### What's Needed for Full Inference

**Option A: C wrapper DLL** (recommended)
Write a thin C wrapper that exposes flat-function API:
```c
// wrapper.c → wrapper.dll
void* llama_wrap_load_model(const char* path);  // hides struct params
void* llama_wrap_create_context(void* model, int n_ctx, int n_threads);
int   llama_wrap_tokenize(void* model, const char* text, int* tokens, int max);
// etc.
```
This avoids struct-by-value entirely. Can be compiled with the same Clang version.

**Option B: Byte-perfect struct reverse-engineering**
Dump the actual struct layout from the DLL using `dumpbin /all` or a C sizeof program
compiled with the exact same Clang flags. Update CDEF byte-for-byte.

**Option C: Dynamic struct sizing**
Allocate `N * 8` bytes for the struct and try different sizes until one works.
Clang may pad the struct to 80 bytes (16-byte alignment for SIMD).

### Impact

| What works | What doesn't |
|-----------|-------------|
| Library probe (`isAvailable()`) | Model loading (`llama_model_load_from_file`) |
| Version/capability detection | Tokenization via native vocab |
| CPU/GPU capability checks | `llama_decode` inference |
| Backend init/teardown | Text generation |

### PHPUnit-specific Crash

In PHPUnit context, the DLL crashes on `FFI::cdef()` with `GGML_ASSERT(prev != ggml_uncaught_exception)`.
This is a C++ exception state conflict between PHPUnit's output buffering and the GGML library's
global constructors. Does NOT happen in standalone PHP scripts. `isAvailable()` reports `true`
only in non-PHPUnit contexts. The integration test skips gracefully.

**Workaround:** set `FERRY_AI_LLAMA_LIB` + DLL dir in PATH before PHP starts (not via `putenv()`).
PHPUnit tests will skip; standalone scripts work for probing.

---

## 13. Safetensors — Not Supported

**What:** `D:\FerryAI\Qwen3-0.6B\model.safetensors` exists but cannot be loaded.

**Why safetensors doesn't work:**
- `.safetensors` is a HuggingFace/PyTorch serialization format. It contains raw tensor weights, not a computation graph.
- ONNX Runtime loads `.onnx` (Protobuf graph + weights).
- llama.cpp loads `.gguf` (GGML quantized format with tokenizer embedded).
- `.safetensors` requires a model architecture definition (`config.json`) + weights → needs to be **converted** first.

**Conversion paths:**
| From | To | Tool |
|------|----|------|
| `model.safetensors` + `config.json` | `model.onnx` | `optimum-cli export onnx` (Python, HuggingFace Optimum) |
| `model.safetensors` + `config.json` | `model.gguf` | `convert_hf_to_gguf.py` (Python, llama.cpp) |

**FerryAI can DETECT safetensors** (`FormatDetector` returns `'safetensors'`) but cannot load them.
This is correct behaviour — detection ≠ loading. The format is recognized for informational purposes.

**Debt:** document the conversion workflow for users who have safetensors models from HuggingFace.
Add example: `python -m optimum.exporters.onnx --model Qwen/Qwen3-0.6B output/`

---

## 14. GPU — Partially Verified (2026-07-05)

**Hardware present:** NVIDIA GeForce RTX 4060, 8 GB, driver 591.86.

### llama.cpp — ✅ GPU WORKS (CUDA)
- The `D:\FerryAI` build **is CUDA-capable**: `ggml-cuda.dll` (156 MB) + `cudart64_13.dll`,
  `cublas64_13.dll`, `cublasLt64_13.dll` are present.
- `llama-bench -ngl 99` → `load_backend: loaded CUDA backend from ggml-cuda.dll`, RTX 4060
  detected, **~384 tok/s** on the CUDA backend.
- Through PHP FFI (`ferry_llama` wrapper): `ferry_supports_gpu_offload()=1`, model loaded with
  `n_gpu_layers=99` → `offloaded 25/25 layers to GPU`, ~250 tok/s. (§12)
- Earlier note that the build was "CPU-only" was **wrong** — the CUDA backend loads once the
  correct backend DLLs are loaded from the llama dir.

### ONNX Runtime — 🔴 GPU untested
- Installed package is a **CPU build** (`onnxruntime-win-x64-1.27.0`); providers =
  `["AzureExecutionProvider", "CPUExecutionProvider"]`. All GPU providers report
  `isAvailable()=false` (correct).
- To test: download `onnxruntime-win-x64-gpu-*.zip` (CUDA provider) + CUDA Toolkit + cuDNN.

**Remaining debt:** ONNX GPU path never exercised; llama GPU not yet driven through the FerryAI
`LlamaBackend` (wrapper proven standalone — §12).

---

## 15. RubixML Models — Implemented (2026-07-05)

### Status: RESOLVED

- **`CpuNativeTensor` arithmetic** — `add/sub/mul` (elementwise, shape-checked), `matmul`
  (2D), `transpose`, `reshape`, `slice` are now real pure-PHP implementations (no native
  deps, no dependency on the `tensor` package). Fully unit-tested.
- **`RubixMLAdapter`** — real `loadModel()` (RBX format via `PersistentModel::load`, falling
  back to plain unserialize), `predict()` and `proba()` (build an `Unlabeled` dataset and call
  the estimator). All RubixML access is dynamic (class-name strings), so the file needs no
  compile-time dependency and is excluded from PHPStan/Psalm like the FFI boundaries.
  Fixed a latent bug: availability now uses `interface_exists('Rubix\ML\Estimator')`
  (`Estimator` is an interface, so `class_exists` always returned false).
- **`CpuNativeModel::run()`** — delegates to the estimator via a new `Predictor` interface when
  one is present (`['output' => predictions]`); keeps the legacy fallback otherwise.
- **`CpuNativeBackend::load()`** — RubixML-aware: loads real `.rbm` estimators when the library
  is available, else falls back to the legacy serialized-array path (unchanged when absent).

### Dependency constraint (why rubix/ml is not in the main vendor)

`rubix/ml` requires `amphp/parallel ^1` (→ `amphp/amp ^1`), but the dev toolchain's
`vimeo/psalm` requires `amphp/parallel ^2` (→ `amphp/amp ^3`). They cannot coexist, and
`amphp/amp`'s files-autoload (`Amp\delay()`) collides if both are loaded in one process.
So `rubix/ml` stays a `suggest`-only, opt-in dependency installed in an **isolated** location.

### Verification

- Unit: `CpuNativeTensorTest` (arithmetic + shape-mismatch), `CpuNativeModelTest` (Predictor
  delegation with a fake), `RubixMLAdapterTest` (not-installed path), `CpuNativeBackendTest`.
- Integration (real rubix/ml v2.5.3 + rubix/tensor 3.0.5 on PHP 8.5, isolated process):
  `tests/Integration/Rubix/rubix_harness.php` trains KNN → saves `.rbm` → loads via
  `CpuNativeBackend` → `predict` = `["a","b"]`, `proba.a` = `1`.
  `RubixCpuIntegrationTest` runs the harness as a subprocess (avoids the amphp collision) and
  asserts the JSON — 1 test, passes. Set `FERRY_AI_RUBIXML_AUTOLOAD` to enable; skips otherwise.
- `composer check` fully green: cs 0 · PHPStan L8 No errors · Psalm L3 No errors · 611 unit tests.
- Example: `examples/24-rubix-cpu.php`.

---

## 16. FFI CDEF Generator — Implemented (2026-07-05)

### Status: RESOLVED (with documented limitations)

### Problem
Each FFI backend needs hand-written CDEF declarations kept in sync with the native header,
which changes per build — fragile and unfriendly.

### Delivered
- `packages/core/src/FFI/CdefGenerator.php` — turns a C header into an `\FFI::cdef()`-ready
  string: strips block/line comments, preprocessor directives (incl. `\`-continuations),
  `extern "C"` wrappers, `__attribute__((…))` / `__declspec(…)`, and user-listed export macros
  (e.g. `LLAMA_API`), then rebalances the brace left by the removed `extern "C" {` and trims
  whitespace before `;`.
- `bin/generate-ffi.php` — CLI: `--header <path> [--output <file>] [--class <Name>] [--strip A,B]`.
  Prints the cdef, or writes a `final class X { public const CDEF = <<<'CDEF' … CDEF; }`.

### Verification
- Unit: `CdefGeneratorTest` (4) — strips comments/preprocessor/macros/extern, keeps typedefs &
  prototypes, braces balanced, and `\FFI::cdef()` parses generated type declarations.
- Real header: `bin/generate-ffi.php --header D:\FerryAI\llama.h --strip LLAMA_API,GGML_API,GGML_CALL`
  reduced the 39 KB header to clean, brace-balanced declarations (no macros/`#`/comments/`extern`),
  containing `llama_model_load_from_file`. `composer check` green; example `examples/25-ffi-generator.php`.

### Honest limitations (not a full C preprocessor)
- **Enum values referencing cross-header macros** (e.g. `LLAMA_ROPE_TYPE_NEOX = GGML_ROPE_TYPE_NEOX`)
  are left as-is → `\FFI::cdef()` on the real `llama.h` fails there until such values are resolved
  to integers (needs the macro definitions from `ggml.h`, i.e. real preprocessing). This is a real
  boundary of a header-only cleaner.
- `#define` integer constants are dropped (FFI ignores macros).
- Function-like macros keep their argument list (only bare export tokens are stripped).
- Struct-by-value ABI issues (§12) are unaffected — the generator produces declarations, not a
  working binding; the C-wrapper DLL (§12 Option A) is still the robust path for llama.cpp.

**Remaining:** the generated CDEF is not yet auto-wired into `LlamaCpp::CDEF`; it is a developer
tool. Backends still ship hand-verified CDEFs.

---

## 17. WSL Testing — Never Done

### What
FerryAI has only been tested on native Windows (PowerShell 5.1, PHP 8.5.4 x64).
Windows Subsystem for Linux (WSL2) is a supported deployment target but never exercised.

### What needs testing in WSL
| Item | Reason |
|------|--------|
| `libonnxruntime.so` loading | Linux shared library path differs from Windows DLL |
| `libllama.so` + `libggml.so` | Linux llama.cpp builds use different CPU backends |
| `ext-ffi` with Linux `.so` files | Linux FFI uses ELF, not PE/COFF |
| PHP-FPM + Nginx | Production deployment on Linux |
| `ext-shmop` with Linux shared memory | Different SHM implementation |
| `ext-pdo_pgsql` with Linux PostgreSQL | Different socket than Windows named pipes |
| RoadRunner / FrankenPHP | Long-running PHP on Linux |
| File paths | `/home/` vs `C:\Users\`, forward vs backslash |
| `putenv('PATH=...')` | Works differently on Linux |

**Debt:** zero tests on Linux/WSL. All 568 tests pass only on Windows x64. Linux is the primary
production deployment target for PHP applications.

---

## 18. PostgreSQL Vector Store — Implemented (2026-07-05)

### Status: RESOLVED

The `vector` package now ships a full PostgreSQL + pgvector backend alongside SQLite.

### Environment provisioned

- PostgreSQL 18.3 (x64, MSVC) at `127.0.0.1:5432`, user/pass `postgres`/`postgres`.
- `pgvector` 0.8.4 built from source with Visual Studio 2022 (`nmake /F Makefile.win`)
  and installed into `D:\_PROGRAMS\PostgreSQL\18\{lib,share\extension}`.
  (pgvector 0.8.0 fails to compile against PG 18 — `vacuum_delay_point` signature
  change; 0.8.4 is the first tag that builds cleanly.)
- Verified runtime: `CREATE EXTENSION vector`, native `<=>` cosine ordering.

### Delivered

**New files:**
- `packages/vector/src/PostgresStore.php` — PDO wrapper. Multi-collection storage in
  native `vector(dim)` columns + `jsonb` metadata; upsert, CRUD, iterate, native
  `search()` via pgvector distance operators. Metadata table `ferry_collections`.
- `packages/vector/src/PostgresCollection.php` — `implements VectorStore`. Native ANN
  ordering (no brute force); metadata filtering reuses the tested `MetadataFilter`
  (matching ids resolved first, distance query restricted to them).
- `packages/vector/src/PostgresVecIndex.php` — HNSW / IVFFlat index DDL
  (`vector_cosine_ops` / `vector_l2_ops` / `vector_ip_ops`).

**Integration:**
- `AIFactory::createVectorStore()` selects the driver via `vector.driver`
  (config) or `FERRY_AI_VECTOR_DRIVER` (env); `pgsql` → `PostgresCollection`,
  default `sqlite` → existing path (no regression).
- Config keys: `vector.dsn`, `vector.user`, `vector.password`, `vector.metric`.
- `composer.json` (`vector`): `suggest` `ext-pdo_pgsql`.

**Metric mapping:** `cosine → <=>`, `euclidean → <->`, `dot → <#>`
(matches `BruteForceIndex` semantics, incl. pgvector's negative inner product).

### Verification

- Unit: `PostgresStoreHelpersTest`, `PostgresVecIndexHelpersTest` (pure helpers:
  identifier validation/injection guard, vector literal, operator/opclass, index DDL).
- Integration: `tests/Integration/Postgres/PostgresVectorIntegrationTest.php`
  (`@group integration`, `@coversNothing`) — 14 tests against the real server:
  CRUD, native cosine ordering, metadata filter, update, deleteByFilter, iterator/
  export, clear, dimension mismatch, HNSW index, `AIFactory` pgsql path. Skips
  gracefully when `ext-pdo_pgsql`/server/pgvector is absent.
- `composer test` → 580 tests OK. `composer test-integration` (with
  `FERRY_AI_SKIP_NATIVE=0`) → Postgres suite 14/14 OK. New files pass PHPStan L8
  and Psalm L3.

### Remaining (minor)

- IVFFlat `lists` is fixed at 100; not yet tuned to dataset size.
- Index creation is opt-in (call `PostgresVecIndex::createIndex`); not auto-created
  by `AIFactory`.