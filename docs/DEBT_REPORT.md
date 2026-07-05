# FerryAI — Project Debt Report

> Only **unresolved** items and by-design statuses. Resolved items are removed; any valuable
> runtime findings have been moved to the relevant capability docs.
>
> Last pass: 2026-07-05.

---

## 1. FFI Boundary — Mocked by Design

**Reason:** FFI is untyped. Static analysis cannot validate `\FFI::cdef()`. These files are
excluded from PHPStan/Psalm and tested via hand-rolled stubs in unit tests. Real path:
integration suite only.

| File | What's mocked | Real status |
|------|--------------|-------------|
| `onnx-backend/src/OnnxRuntimeFactory.php` | Unit tests use `MockOnnxRuntime` | Real FFI works (proved manually). Not in automated tests |
| `onnx-backend/src/Runtime/NativeOnnxRuntime.php` | Unit tests use `MockOnnxRuntime` | Works — delegates to `ankane/onnxruntime` |
| `onnx-backend/src/Runtime/NativeOnnxSession.php` | Unit tests use `MockOnnxSession` | Works |
| `llama-backend/src/FFI/LlamaCpp.php` | Excluded from analysis | Unused (replaced by `FerryLlama` wrapper) |
| `llama-backend/src/FFI/LlamaContext.php` | Excluded from analysis | Unused |
| `llama-backend/src/FFI/LlamaBatch.php` | Excluded from analysis | Unused |
| `llama-backend/src/FFI/FerryLlama.php` | Excluded from analysis | ✅ Drives the real `ferry_llama` wrapper |
| `llama-backend/src/Runtime/NativeLlamaRuntime.php` | Unit tests use `MockLlamaRuntime` | ✅ Drives `FerryLlama`; CPU+GPU verified (Windows + Linux) |
| `llama-backend/src/Runtime/NativeLlamaSession.php` | Unit tests use `MockLlamaSession` | ✅ Holds model/context handles + cached metadata |
| `tokenizer/src/HuggingFaceTokenizer.php` | Unit tests use pure-PHP fallback | **All encode/decode throw.** Needs `FERRY_AI_TOKENIZERS_LIB` + tokenizers-cpp lib. Pure-PHP BPE/WordPiece fallback works. |

---

## 2. Hardcoded `return false` / Unavailable Providers

| Class | Method | Value | Why |
|-------|--------|-------|-----|
| `CudaProvider` | `isAvailable()` | `false` | ONNX Runtime package is CPU-build; GPU probing needs Gpu build + CUDA driver |
| `TensorRtProvider` | `isAvailable()` | `false` | Planned, not yet implemented |
| `DirectMlProvider` | `isAvailable()` | `false` | Planned |
| `RocmProvider` | `isAvailable()` | `false` | Planned |
| `OpenVinoProvider` | `isAvailable()` | `false` | Planned |
| `CoreMlProvider` | `isAvailable()` | `PHP_OS_FAMILY === 'Darwin'` | Only macOS, not wired to FFI |

---

## 3. Stub Implementations — Throw on Real Use

| Class | Method | What happens |
|-------|--------|-------------|
| `CpuNativeModel::run()` | No RubixML estimator present | `throw BackendNotAvailableException` with actionable guidance to install RubixML and set `FERRY_AI_RUBIXML_AUTOLOAD`. With an estimator present it delegates to real predict/proba. |
| `AI::predict()` | No RubixML available | Loads a `.rbm` model and delegates to `CpuNativeModel::run()`. **Verified e2e** via isolated RubixML harness: train KNN → save `.rbm` → `AI::predict({x:1.05,y:1.0})` → `"a"`, `({5.05,5.0})` → `"b"`. `backends.predict.model_path` must point at a valid `.rbm`. |

> `HuggingFaceTokenizer` is an *optional* FFI binding — the `TokenizerFactory` transparently
> falls back to the pure-PHP BPE/WordPiece tokenizers when the native `tokenizers-cpp` library
> is absent. Every model we use (all-MiniLM-L6-v2 = WordPiece, Qwen2.5 = BPE) is covered by the
> pure-PHP path. The native binding is only needed for tokenizer types the pure-PHP tokenizers
> don't support (e.g. SentencePiece), and it requires Rust/cargo to build. **It is not a stub;
> it is an optional accelerator for niche tokenizer types, and it correctly falls back.**
>
> `BackedTensor` and `OnnxTensor` arithmetic is *by design* not implemented in PHP — ONNX Runtime
> performs tensor math natively on the GPU/CPU via FFI, and `CpuNativeTensor` provides the
> pure-PHP implementation. `BackedTensor` wraps a backend-native tensor handle; `OnnxTensor`
> similarly delegates arithmetic to ONNX Runtime. These are not stubs but architectural boundaries.

---

## 4. AI Facade — Methods Gated by Model Availability

These are **legitimately model-gated** (they work when the config points at a real file; the
wiring is correct and errors are actionable):

| Method | What it needs |
|--------|--------------|
| `AI::classify()` | A real classification ONNX model at `backends.classify.model_path` |
| `AI::moderate()` | A real moderation ONNX model at `backends.moderate.model_path` |

> `AI::predict()` is verified e2e with a real `.rbm` model via the isolated RubixML harness
> (see `tests/Integration/Rubix/rubix_predict_harness.php`).

---

## 5. Integration Tests — Missing

| Missing | Notes |
|---------|-------|
| Tokenizer end-to-end with real `tokenizer.json` (no ONNX model) | TokenizerFactory + pure-PHP round-trip never in integration |
| Vector store with 10k+ vectors (performance threshold) | Scalability not measured |
| Model Hub download → cache → verify cycle | Hub integration not tested e2e |
| HuggingFace API with auth token | Private-model access not exercised |
| GPU/CUDA availability for ONNX | ONNX GPU build not installed (only llama GPU verified) |

---

## 6. Framework Integrations — Standalone, Not Framework-Tested

| Package | Class | Issue |
|---------|-------|-------|
| `laravel` | `AIServiceProvider` | Does **not** extend `Illuminate\Support\ServiceProvider`. Works standalone. Not tested in real Laravel app. |
| `laravel` | `Facades\AI` | Does **not** extend `Illuminate\Support\Facades\Facade`. Works as proxy. |
| `symfony` | `AIBundle` | Does **not** extend `Symfony\Component\HttpKernel\Bundle\Bundle`. Works standalone. |
| `symfony` | `Configuration` | Does **not** implement `ConfigurationInterface`. Returns plain array. |
| `symfony` | `FerryAIExtension` | Does **not** extend `Extension`. Works standalone. |

**Reason:** AGENTS.md rule 7: no hard coupling. Framework base classes are optional (`suggest`).

---

## 7. DataFrame Package — Not Created

`packages/dataframe/` (6 files) — Phase 4 spec: "only when demand exists". No composer.json,
no src/, no tests.

---

## 8. Documentation Debt

| Gap | Note |
|-----|------|
| `docs/specs/` | Empty — populated by the brainstorming workflow |
| `SOURCES.md` sqlite-vec version | Lists v0.1.9; the verified Windows binary is v0.1.10-alpha (pre-1.0). Both are alpha — the point is that version pinning matters. |
| Root `composer.json` require | Lists only `ext-ffi/json/hash/fileinfo`; sub-package extensions (`ext-pdo`, `ext-zip`) declared per-package; `ext-sodium/curl/shmop/pdo_pgsql/pdo_sqlite` are `suggest`-only. Intentional (optional), but not surfaced in one place other than the README matrix. |

> All per-capability guides, the API reference, the configuration reference,
> getting-started, deployment, security and troubleshooting docs and the CHANGELOG
> now exist.

---

## 9. Dev Tooling Debt

Listed in `composer.json` scripts but not installed:

| Tool | Script |
|------|--------|
| Infection (mutation testing) | `composer mutation` |
| Pest | — |
| CaptainHook (git hooks) | — |
| Monorepo-builder | — |
| Composer-normalize | — |

---

## 10. Test Coverage Gaps — Unit Tests

| Class | Missing tests | Reason |
|-------|--------------|--------|
| `OnnxRuntimeFactory` | Unit | FFI boundary — excluded by design. Integration only. |
| `HuggingFaceTokenizer` | Unit | FFI boundary — excluded by design |
| Native FFI classes (`LlamaCpp`, `LlamaContext`, `LlamaBatch`, `FerryLlama`, `NativeLlamaRuntime`, `NativeLlamaSession`, `NativeOnnxRuntime`, `NativeOnnxSession`) | Unit | FFI boundary — excluded by design |

**Remaining untested classes: 8 FFI-boundary files — by design.** All pure-PHP classes tested.

---

## 11. Summary Matrix (unresolved / by-design only)

| Category | Status | What's missing / why not |
|----------|--------|--------------------------|
| ONNX GPU providers | CPU only | ONNX Runtime package is CPU-build. CUDA/TensorRT/ROCm/OpenVINO/DirectML providers `isAvailable()=false`. GPU build + CUDA Toolkit + cuDNN needed. |
| Native HuggingFace tokenizer | Pure-PHP fallback only | `tokenizers-cpp` shared library not installed; `FERRY_AI_TOKENIZERS_LIB` unset |
| StreamResponse PSR-7 | ✅ (nyholm/psr7 dev dep) | Consumer must install a PSR-17 factory |
| llama.cpp old FFI files | Unused | `LlamaCpp`/`LlamaContext`/`LlamaBatch` can be deleted (replaced by `FerryLlama` wrapper) |
| llama.cpp under PHPUnit | Standalone-process only | ggml global constructors conflict with test runner; integration via subprocess harness |
| `ferry_llama.dll` / `.so` | Machine-built | Not committed; build via `native/llama-wrapper/build.ps1` / `build.sh` |
| DataFrame | — | Entire package not created (no demand) |
| Dev tooling | — | Infection, Pest, CaptainHook, Monorepo-builder, Composer-normalize not installed |
| Framework integration tests | — | Laravel/Symfony adapters not tested inside real applications |
| Safetensors | 🔴 | Format detected, no loader. Needs Python conversion to ONNX/GGUF. See §12. |
| ONNX GPU | 🔴 | Never tested. ONNX GPU build + CUDA/cuDNN needed. See §13. |
| PostgreSQL WSL auth | 🔴 diagnosed | `pg_hba.conf` must allow the WSL subnet; pure environment config. See §14. |
| Long-running PHP on Linux | 🔴 | PHP-FPM/Nginx, RoadRunner/FrankenPHP, ext-shmop not exercised on Linux. |

> llama.cpp (CPU + CUDA GPU), ONNX Runtime embeddings, sqlite-vec, RubixML and the full
> pure-PHP suite (630 unit + PHPStan + Psalm) are verified on both Windows and Linux/WSL.

---

## 12. Safetensors — Not Supported

**What:** `D:\FerryAI\Qwen3-0.6B\model.safetensors` exists but cannot be loaded.

**Why:** `.safetensors` is a HuggingFace/PyTorch weight format, not a compute graph. ONNX Runtime
loads `.onnx`; llama.cpp loads `.gguf`. Conversion is required.

**Conversion paths:**

| From | To | Tool |
|------|----|------|
| `model.safetensors` + `config.json` | `model.onnx` | `optimum-cli export onnx` (Python, HuggingFace Optimum) |
| `model.safetensors` + `config.json` | `model.gguf` | `convert_hf_to_gguf.py` (Python, llama.cpp) |

`FormatDetector` returns `'safetensors'` (correct — detection ≠ loading).

---

## 13. GPU — Partially Verified

**Hardware present:** NVIDIA GeForce RTX 4060, 8 GB, driver 591.86.

### llama.cpp — ✅ GPU works (CUDA), Windows + Linux
- Windows: ~250 tok/s via PHP FFI, 25/25 layers offloaded.
- Linux/WSL2: ~176 tok/s via wrapper (source-built with `GGML_CUDA=ON`, CUDA 12.6, `sm_89`).
  Build recipe in `native/llama-wrapper/README.md`.

### ONNX Runtime — 🔴 GPU never tested
- Installed package is a **CPU build** (`onnxruntime-win-x64-1.27.0`); `GetAvailableProviders`
  returns `["AzureExecutionProvider", "CPUExecutionProvider"]`. All GPU providers correctly
  report `isAvailable()=false`.
- To test: download the ONNX Runtime **Gpu** package, plus CUDA Toolkit and cuDNN.

---

## 14. PostgreSQL from WSL — Diagnosed, Environment Blocker

WSL reaches the Windows-host PostgreSQL at `192.168.96.1:5432`, but `pg_hba.conf` rejects the
connection — only `127.0.0.1` / `::1` are allowed. Fix: add `host all all 192.168.0.0/16 md5`
and restart the service. Pure environment config; no code change.

The Postgres integration tests (14) skip on Linux; they pass on Windows against the local server.

---

## 15. Honest Notes (not debt, just facts)

- **llama.cpp grammar sampling** is inherently slower (full-vocab scan required). It is strict now
  (pure-PHP `GbnfMatcher` enforces `root ::= "yes" | "no"` → exactly `yes`/`no`). Supported GBNF
  subset: literals, char classes, `|`, sequences, `( )`, `* + ?`, rule refs, `#` comments.
- **`GrammarSampler` GBNF enforcement** is simplified — it masks tokens to keep output on a
  viable grammar prefix, but does not strictly reject every single off-grammar possibility with
  the fidelity of the native llama.cpp GBNF engine.
- **Native top-k pre-filter** keeps greedy/top-k/top-p sampling fast (~300 ms vs the old ~5 s/tok).
- **`SqliteVecExtension`** maps the `dot` metric to `cosine` (vec0 has no dot-product distance).
  vec0 v0.1.10 is alpha.
- **`ferry_llama.dll`/`.so`** is machine-built and not committed — build it or ship a prebuilt binary.
- **`NativeLlamaRuntime::isAvailable()`** now checks `ext-ffi` + wrapper file presence (reports
  `true` when available). It does NOT load the DLL during the probe (lazy in `createSession`),
  to avoid the PHPUnit ggml-constructor conflict during availability checks. Inference runs in
  standalone PHP processes; the integration test uses a subprocess harness.
- **Postgres pgvector index** is opt-in (`PostgresVecIndex::createIndex`); IVFFlat `lists` is
  fixed at 100.
- **Vector store with 10k+ vectors** — insert & search throughput not benchmarked.
- **Capability-specific guides now exist** for all features; per-guide links in `README.md`
  and `docs/README.md`.
