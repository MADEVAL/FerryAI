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
| `StreamResponse::create()` | Returns PSR-7 ResponseInterface | `throw new RuntimeException` — needs `nyholm/psr7` or `guzzlehttp/psr7` |
| `HuggingFaceTokenizer` | `encode()`, `decode()`, all Tokenizer methods | `throw new RuntimeException` — FFI not wired |
| `SqliteVecExtension` | `search()` | Returns `[]` — FFI not wired. BruteForceIndex fallback works. |

---

## 4. AI Facade — Gated Methods

| Method | Gates on | What's missing |
|--------|---------|---------------|
| `AI::embed()` | `TokenizerFactory::create()` needs file path or Hub | Works via direct `Embedder` construction (see examples). Facade path needs `backends.embedding.model_path` in config |
| `AI::similarity()` | Same as `embed()` | Same |
| `AI::classify()` | `backends.classify.model_path` in config | Needs actual classification ONNX model file |
| `AI::moderate()` | `backends.moderate.model_path` in config | Needs moderation ONNX model |
| `AI::predict()` | `backends.predict.model_path` in config | Needs `.rbm` model or RubixML |
| `AI::chat()` | llama.cpp backend available + `backends.llama.model_path` | llama.cpp ABI not validated |
| `AI::stream()` | Same as `chat()` | Same |
| `AI::streamResponse()` | PSR-7 implementation | `StreamResponse::create()` throws |
| `AI::warmup()` | `no-op` | Not wired to ModelPool or Hub |

---

## 5. Not Integrated — Code Exists, Never Called

| Component | Where defined | Never called by |
|-----------|--------------|----------------|
| `ModelPool` | `ai/src/ModelPool.php` | Backends don't check pool in `load()`. No preload at startup. |
| `SharedMemoryManager` | `ai/src/SharedMemoryManager.php` | Not integrated into `LlamaBackend::load()` or `OnnxBackend::load()` |
| `RetryHandler` | `core/src/RetryHandler.php` | `Downloader` and `HuggingFaceClient` don't use it |
| `Logger` | `core/src/Logger.php` | No component calls `Logger::info()` or `Logger::error()` |
| `Metrics` | `ai/src/Metrics.php` | Backends don't call `Metrics::increment()` or `Metrics::timing()` |
| `Profiler` | `ai/src/Profiler.php` | Same — not called automatically |
| `NativeBinaryManager` | `ai/src/NativeBinaryManager.php` | No auto-download at startup. Not called in `OnnxRuntimeFactory` or `LlamaCpp` |

---

## 6. Integration Tests

| Test file | Tests | Status |
|-----------|-------|--------|
| `tests/Integration/Onnx/OnnxRuntimeIntegrationTest.php` | 3 (version, devices, providers) | ✅ Pass with ONNX 1.27.0 |
| `tests/Integration/Llama/LlamaBackendIntegrationTest.php` | 2 (version, devices) | ⊘ Skipped — `NativeLlamaRuntime::isAvailable()` = false |

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

| Document | Status |
|----------|--------|
| `docs/specs/` | Empty directory |
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
| llama.cpp | DLL loads | — | ABI not validated | Full CDEF |
| Embedding | ✅ via Embedder direct | — | AI::embed() via facade | Config wiring |
| Tokenizer | ✅ pure-PHP BPE/WordPiece | — | Native tokenizers-cpp | DLL |
| Vector store | ✅ brute-force | sqlite-vec (FFI not wired) | — | sqlite-vec DLL |
| Model Hub | ✅ HF API, SHA-256, Ed25519, format detect | — | Download cycle | Token for private models |
| Pipeline | ✅ all 8 stages | — | — | — |
| CPU backend | ✅ Backend/Model/Tensor | RubixML predict/proba | `rubix/ml` not installed | .rbm inference |
| Shared memory | ✅ allocate/detach | — | Not integrated into backends | — |
| Async fibers | ✅ suspend/resume/timeout | — | Not tested in RoadRunner | — |
| Model pool | ✅ put/acquire/evict | — | Not integrated into backends | — |
| Metrics/Profiler | ✅ manual calls work | — | Not auto-called by backends | — |
| Logger | ✅ writes JSON lines | — | Not called by any component | — |
| RetryHandler | ✅ retry works | — | Not integrated into Downloader | — |
| NativeBinaryManager | ✅ resolve/verify | — | Not called at startup | Download URLs |
| Framework integrations | ✅ standalone classes | Framework base classes | Not tested in real apps | — |
| DataFrame | — | — | — | Entire package (6 files) |
| Documentation | README + BUILD_LOG + design docs | — | — | All usage guides |
| Dev tooling | PHPUnit + PHPStan + Psalm + CS Fixer | — | — | Infection, Pest, CaptainHook |
