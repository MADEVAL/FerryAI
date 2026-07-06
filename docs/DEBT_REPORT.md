# FerryAI — Project Debt Report

> Only unresolved items. Resolved/diagnosed issues are removed. By-design decisions are
> kept where they explain a deliberate choice.
>
> This is now the single home for everything the specification described but which is **not yet
> implemented**. The step-by-step ТЗ (`IMPLEMENTATION_PHASE_1..4.md`), the architecture bible
> (`TECHNICAL_SPECIFICATION.md`) and the contracts doc (`INTERFACE_CONTRACTS.md`) have had their
> implemented content removed — the implemented surface now lives in the code
> (`packages/*/src`), `docs/api-reference.md` and the per-capability guides.
>
> Last pass: 2026-07-06. Baseline: 686 unit tests, PHPStan L8 + Psalm L3 clean.

---

## 0. Deferred / Not Implemented From the Specification

The authoritative list of specified-but-unbuilt features (details in the numbered sections below):

| Item | Spec phase | Status |
|------|-----------|--------|
| `dataframe` package (`DataFrame`, `Column`, CSV/JSON/Parquet IO) | Phase 4 | **Not created.** `Contracts\DataFrame` exists; no implementation. See §7. |
| `BackedTensor` arithmetic (tensor over a native backend tensor) | Phase 1+ | **Stub** — every op throws `Not implemented in Phase 1.`. `ArrayTensor` is the working pure-PHP tensor. See §3. |
| ONNX GPU execution providers (TensorRT / DirectML / OpenVINO / ROCm) | Phase 1/4 | **Planned**, not implemented. CoreML not FFI-wired; CUDA needs cuDNN. See §2 / §13. |
| Safetensors **loader** | — | **Not supported** — detection only; conversion via Python required. See §12. |
| HuggingFace native tokenizer (`tokenizers-cpp` binding) | Phase 2 | **Optional accelerator**, not built; pure-PHP BPE/WordPiece covers all needed types. See §1. |
| Dev tooling (Infection, Pest, CaptainHook, Monorepo-builder, Composer-normalize) | Phase 4 | **Referenced in scripts, not installed.** See §9. |

Everything else described in the specification is implemented and verified (see the guides and
`docs/api-reference.md`).

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

> The unused direct-binding classes `LlamaCpp`/`LlamaContext`/`LlamaBatch` (superseded by the
> `FerryLlama` wrapper) have been **deleted**; the only llama FFI class is now `FFI/FerryLlama.php`.

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
| `tensor/src/BackedTensor.php` | Phase-1 stub: `reshape`/`transpose`/arithmetic/`toArray` etc. throw `Not implemented in Phase 1.`. The working pure-PHP tensor is `ArrayTensor`; ONNX/CPU backends return their own `OnnxTensor`/`CpuNativeTensor`. `BackedTensor` (a tensor wrapping a native backend tensor) is not needed by any current path. |

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
| `FILE_TREE.md` llama-backend section | Stale: still lists the deleted `FFI/LlamaCpp/LlamaContext/LlamaBatch` and omits `FFI/FerryLlama.php` + `Runtime/*`. Needs a reconciliation pass. |
| Root `composer.json` | Only lists `ext-ffi/json/hash/fileinfo`; sub-package extensions declared per-package; optional exts `suggest`-only. Intentional but not centralised. |

> Every engine package now has a guide (added `docs/backends/cpu.md`, `docs/tensor.md`,
> `docs/core.md`). The spec docs (`TECHNICAL_SPECIFICATION.md`, `INTERFACE_CONTRACTS.md`,
> `IMPLEMENTATION_PHASE_1..4.md`) were pruned of implemented content — the source of truth is the
> code + `docs/api-reference.md` + the per-capability guides; unimplemented items live here.

---

## 9. Dev Tooling — Not Installed

Listed in `composer.json` scripts: Infection, Pest, CaptainHook, Monorepo-builder, Composer-normalize.

---

## 10. Test Coverage Gaps — FFI Boundary

8 FFI-boundary files excluded from unit tests **by design**. All pure-PHP classes are tested
(686 unit tests).

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
| Pure-PHP suite | ✅ 686 unit + PHPStan L8 + Psalm L3, Windows + Linux. |
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
