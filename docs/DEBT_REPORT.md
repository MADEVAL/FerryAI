# FerryAI — Project Debt Report

> Only unresolved items. Resolved/diagnosed issues are removed. By-design decisions are
> kept where they explain a deliberate choice.
>
> Last pass: 2026-07-05.

---

## 1. FFI Boundary — Mocked by Design

Static analysis cannot validate `\FFI::cdef()`. These files are excluded from
PHPStan/Psalm and tested via hand-rolled stubs in unit tests. Real path: integration suite.

| File | What's mocked | Real status |
|------|--------------|-------------|
| `onnx-backend/src/OnnxRuntimeFactory.php` | Unit tests use `MockOnnxRuntime` | Real FFI works. Not in automated tests. |
| `onnx-backend/src/Runtime/NativeOnnxRuntime.php` | Unit tests use `MockOnnxRuntime` | Works — delegates to `ankane/onnxruntime`. |
| `llama-backend/src/FFI/FerryLlama.php` | Excluded from analysis | ✅ Drives the real `ferry_llama` wrapper. |
| `llama-backend/src/Runtime/NativeLlamaRuntime.php` | Unit tests use `MockLlamaRuntime` | ✅ CPU+GPU verified (Windows + Linux). |
| `tokenizer/src/HuggingFaceTokenizer.php` | Unit tests use pure-PHP fallback | Pure-PHP BPE/WordPiece covers all needed types. Native binding optional. |

> `LlamaCpp`/`LlamaContext`/`LlamaBatch` are unused (replaced by `FerryLlama` wrapper) and
> can be deleted.

---

## 2. ONNX GPU Providers — Hardcoded `false`

| Class | Why |
|-------|-----|
| `CudaProvider` | `isAvailable()=false` — ONNX GPU package installed, CUDA provider detected, but **cuDNN missing** (requires manual download from developer.nvidia.com). Provider detection works; session creation fails without cuDNN. See §12. |
| `TensorRtProvider` | Planned, not implemented. |
| `DirectMlProvider` | Planned. |
| `RocmProvider` | Planned. |
| `OpenVinoProvider` | Planned. |
| `CoreMlProvider` | `PHP_OS_FAMILY === 'Darwin'` only, not wired to FFI. |

---

## 3. Stub Implementations

| Class | What happens |
|-------|-------------|
| `CpuNativeModel::run()` (no RubixML) | `throw BackendNotAvailableException` with actionable guidance. With RubixML it delegates to real predict/proba. |

> `HuggingFaceTokenizer` — pure-PHP BPE/WordPiece covers all needed tokenizer types; native
> binding is optional and not a stub.
> `CpuNativeTensor` arithmetic — real pure-PHP implementation.
> `RubixMLAdapter` — real predict/proba/loadModel.
> `StreamResponse::create()` — auto-detects PSR-17 factory.

---

## 4. AI Facade — Methods Gated by Model Files

These work when the config points at a real file; wiring is correct, errors are actionable:

| Method | What it needs |
|--------|--------------|
| `AI::classify()` | A real classification ONNX model at `backends.classify.model_path`. |
| `AI::moderate()` | A real moderation ONNX model at `backends.moderate.model_path`. |

> `AI::embed()`/`similarity()`, `AI::chat()`/`stream()`, `AI::predict()` are verified e2e.

---

## 5. Integration Tests — Missing

| Missing | Notes |
|---------|-------|
| Tokenizer end-to-end with real `tokenizer.json` (no ONNX model). |
| Vector store with 10k+ vectors (performance threshold). |
| Model Hub download → cache → verify cycle. |
| HuggingFace API with auth token. |
| ONNX GPU availability (requires cuDNN — §12). |

---

## 6. Framework Integrations — Standalone by Design

Laravel/Symfony adapters do **not** extend framework base classes — intentional decoupling
(AGENTS rule 7). Framework base classes are optional (`suggest` in composer.json). Not tested
inside real Laravel/Symfony applications.

---

## 7. DataFrame Package — Not Created

`packages/dataframe/` (6 files) — Phase 4 spec: "only when demand exists".

---

## 8. Documentation — Minor Gaps

| Gap | Note |
|-----|------|
| `docs/specs/` | Empty — populated by the brainstorming workflow. |
| `SOURCES.md` sqlite-vec version | Lists v0.1.9; verified binary on Windows is v0.1.10-alpha. |
| Root `composer.json` | Only lists `ext-ffi/json/hash/fileinfo`; sub-package extensions declared per-package; optional exts `suggest`-only. Intentional but not centralised. |

> All per-capability guides, API reference, configuration, getting-started, deployment,
> security, troubleshooting and CHANGELOG exist.

---

## 9. Dev Tooling — Not Installed

Listed in `composer.json` scripts: Infection, Pest, CaptainHook, Monorepo-builder, Composer-normalize.

---

## 10. Test Coverage Gaps — FFI Boundary

8 FFI-boundary files excluded from unit tests **by design**. All pure-PHP classes are tested
(630 unit tests).

---

## 11. Summary

| Category | Status |
|----------|--------|
| llama.cpp CPU + GPU | ✅ Windows (RTX 4060 ~250 t/s) + Linux (~176 t/s). |
| ONNX Runtime CPU | ✅ Windows + Linux (embeddings 7/7 integration). |
| ONNX Runtime GPU | 🔴 GPU build installed; CUDA provider detected; **cuDNN missing** (manual download). |
| sqlite-vec | ✅ Windows + Linux (native KNN). |
| PostgreSQL vector store | ✅ Windows (pgvector 0.8.4). WSL → PG blocked by `pg_hba.conf` (environment). |
| RubixML | ✅ Windows + Linux (isolated, subprocess harness). |
| Pure-PHP suite | ✅ 630 unit + PHPStan L8 + Psalm L3, Windows + Linux. |
| Safetensors | 🔴 Format detected, no loader. Requires Python conversion. |
| HuggingFace native tokenizer | Optional accelerator; pure-PHP covers all needed types. |
| `ferry_llama.dll/.so` | Machine-built, not committed. Build via `native/llama-wrapper/build.{ps1,sh}`. |
| Grammar sampling | Strict GBNF enforcement via pure-PHP `GbnfMatcher`. Full-vocab scan (inherent). |
| llama under PHPUnit | Standalone-process only (ggml global ctor conflict); integration via subprocess harness. |

---

## 12. Safetensors — Not Supported

`.safetensors` is a HuggingFace/PyTorch weight format, not a compute graph. ONNX loads `.onnx`,
llama.cpp loads `.gguf`. Conversion is required via Python tooling. `FormatDetector` correctly
returns `'safetensors'` (detection ≠ loading).

---

## 13. ONNX GPU — Diagnosed (cuDNN)

GPU builds (ORT 1.27.0 CUDA 13) installed on Windows + Linux. `CUDAExecutionProvider` detected
at the FFI level. Session creation fails — `onnxruntime_providers_cuda.{dll,so}` depends on
**cuDNN** (manual download from developer.nvidia.com; not in package repos). Download cuDNN
and place it next to the ORT libraries to enable GPU inference.

---

## 14. PostgreSQL from WSL — Environment Blocker

WSL reaches the Windows-host PG at `192.168.96.1:5432`, but `pg_hba.conf` rejects the WSL
subnet. Fix: `host all all 192.168.0.0/16 md5` + restart. Environment config, no code change.
