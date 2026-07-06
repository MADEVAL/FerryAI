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
> Last pass: 2026-07-06. Baseline: 754 unit tests, PHPStan L8 + Psalm L3 clean.

---

## 0. Deferred / Not Implemented From the Specification

The authoritative list of specified-but-unbuilt features (details in the numbered sections below):

| Item | Spec phase | Status |
|------|-----------|--------|
| `dataframe` package (`DataFrame`, `Column`, CSV/JSON/Parquet IO) | Phase 4 | **Created 2026-07-06.** 6 files, 72 unit tests. DataFrame (16 methods), Column, CsvReader/Writer, JsonReader. **ParquetReader is a stub** (Thrift CompactProtocol decoder not yet implemented — see §7). |
| `BackedTensor` arithmetic (tensor over a native backend tensor) | Phase 1+ | **Deleted.** Was a stub with zero references. `ArrayTensor`/`OnnxTensor`/`CpuNativeTensor` cover all tensor needs; `BackedTensor` was never used by any code path. |
| ONNX GPU execution providers (TensorRT / DirectML / OpenVINO / ROCm) | Phase 1/4 | **Deleted** — the FerryAI wrapper classes were stub-only, unreferenced by the backend. GPU inference works via `OnnxTypeMapper`. Additional providers can be added to that mapper when their native runtimes are available. See §2. |
| Safetensors **loader** | — | **Not supported** — detection only; conversion via Python required. See §12. |
| HuggingFace native tokenizer (`tokenizers-cpp` binding) | Phase 2 | **Built.** Compiled from source (Rust + cmake, `libtokenizers_cpp.so` → `/opt/tokenizers-cpp`). Loaded via `FERRY_AI_TOKENIZERS_LIB`. The factory auto-selects it; falls back to pure-PHP when the lib is absent. See §1. |
| Dev tooling (Infection, Pest, CaptainHook, Monorepo-builder, Composer-normalize) | Phase 4 | **Installed.** `infection/infection` 0.34.0, `captainhook/captainhook` 5.29.0, `ergebnis/composer-normalize` 2.52.0, `symplify/monorepo-builder` 12.7.1. **Pest excluded** — requires PHPUnit ^12, we use ^13. See §9. |

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

## 2. ONNX GPU Providers — Deleted

The FerryAI provider wrapper classes (`CudaProvider`, `TensorRtProvider`, `DirectMlProvider`,
`RocmProvider`, `OpenVinoProvider`, `CoreMlProvider`) were stubs — each had `isAvailable()=false`
hardcoded, and none were referenced by the backend (which uses `OnnxTypeMapper::providerNamesForDevice()`
directly). **Deleted.** The `CpuProvider` and `ExecutionProvider` interface remain (they are tested
and the interface is the contract for future providers).

ONNX GPU inference works via `OnnxBackend::load()` → `OnnxTypeMapper` and does not need these
wrapper classes. See §13 for the ONNX CUDA GPU status.

---

## 3. Stub Implementations

| Class | What happens |
|-------|-------------|
| `CpuNativeModel::run()` (no RubixML) | `throw BackendNotAvailableException` with actionable guidance. With RubixML it delegates to real predict/proba. |

> `BackedTensor` — Phase-1 stub, removed (zero references; `ArrayTensor`/`OnnxTensor`/`CpuNativeTensor` cover all needs).
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

## 5. Integration Tests — ✅ Written (2026-07-06)

| Test | File | Tests | Status |
|------|------|-------|--------|
| Tokenizer end-to-end with real `tokenizer.json` | `tests/Integration/Tokenizer/TokenizerIntegrationTest.php` | 13 | ✅ |
| Vector store with 10k+ vectors (performance threshold) | `tests/Integration/Vector/VectorStorePerformanceTest.php` | 12 | ✅ |
| Model Hub download → cache → verify cycle | `tests/Integration/ModelHub/ModelHubIntegrationTest.php` | 20 | ✅ |
| HuggingFace API with auth token | `tests/Integration/ModelHub/HuggingFaceAuthIntegrationTest.php` | 3 | ✅ (skips without token) |
| ONNX GPU availability | `tests/Integration/Onnx/OnnxGpuIntegrationTest.php` | 3 | ✅ (skips without CUDA) |

Total: 83 integration tests (was 32, +51 new).

---

## 6. Framework Integrations — Standalone by Design

Laravel/Symfony adapters do **not** extend framework base classes — intentional decoupling
(AGENTS rule 7). Framework base classes are optional (`suggest` in composer.json). Not tested
inside real Laravel/Symfony applications.

---

## 7. DataFrame Package — Created (Parquet stub)

`packages/dataframe/` (6 files, 72 unit tests) — implemented 2026-07-06.

| Component | Files | Tests |
|-----------|-------|-------|
| `Column` | value object: name, type, data, inferType, count | 13 |
| `DataFrame` | 16 contract methods + Iterator + Countable | 36 |
| `CsvReader` | CSV → DataFrame, auto-delimiter, header detection | 9 |
| `CsvWriter` | DataFrame → CSV, round-trip | 5 |
| `JsonReader` | Array-of-objects + NDJSON → DataFrame | 9 |
| `ParquetReader` | Stub — throws `IoException('not yet implemented')` | 4 |

**ParquetReader limitation:** The Parquet format requires a Thrift CompactProtocol decoder
for metadata parsing. A full implementation needs the `apache/thrift` library or a
hand-rolled CompactProtocol parser (~500 lines). CSV and JSON cover typical tabular data
needs; Parquet support will be added in a future release.

---

## 8. Documentation — Minor Gaps

| Gap | Note |
|-----|------|
| `docs/specs/` | Empty — populated by the brainstorming workflow. |
| `SOURCES.md` sqlite-vec version | Lists v0.1.9; verified binary on Windows is v0.1.10-alpha. | ✅ **Fixed 2026-07-06.** Updated to v0.1.10-alpha (Windows) + v0.1.9 (Linux). |
| `FILE_TREE.md` llama-backend section | Stale: still lists the deleted `FFI/LlamaCpp/LlamaContext/LlamaBatch` and omits `FFI/FerryLlama.php` + `Runtime/*`. Needs a reconciliation pass. | ✅ **Fixed 2026-07-06.** Replaced with `FerryLlama.php`, added `Runtime/*` index entries, added missing `Grammar/GbnfNode.php` + `Grammar/GbnfMatcher.php`. Count updated 16 → 21. |
| Root `composer.json` | Only lists `ext-ffi/json/hash/fileinfo`; sub-package extensions declared per-package; optional exts `suggest`-only. Intentional but not centralised. |

> Every engine package now has a guide (added `docs/backends/cpu.md`, `docs/tensor.md`,
> `docs/core.md`). The spec docs (`TECHNICAL_SPECIFICATION.md`, `INTERFACE_CONTRACTS.md`,
> `IMPLEMENTATION_PHASE_1..4.md`) were pruned of implemented content — the source of truth is the
> code + `docs/api-reference.md` + the per-capability guides; unimplemented items live here.

---

## 9. Dev Tooling — Installed (Pest excluded)

| Tool | Version | Status |
|------|---------|--------|
| `infection/infection` | 0.34.0 | ✅ Installed |
| `captainhook/captainhook` | 5.29.0 | ✅ Installed |
| `ergebnis/composer-normalize` | 2.52.0 | ✅ Installed |
| `symplify/monorepo-builder` | 12.7.1 | ✅ Installed (resolved with Symfony 8 deps) |
| `pestphp/pest` | — | 🔴 Excluded — requires PHPUnit ^12, project uses ^13 |

Installed 2026-07-06 via `composer require --dev` with `-W` flag.

---

## 10. Test Coverage Gaps — FFI Boundary

8 FFI-boundary files excluded from unit tests **by design**. All pure-PHP classes are tested
(754 unit tests).

---

## 11. Summary

| Category | Status |
|----------|--------|
| llama.cpp CPU + GPU | ✅ Windows (RTX 4060 ~250 t/s) + Linux (~176 t/s). |
| ONNX Runtime CPU | ✅ Windows + Linux (embeddings 7/7 integration). |
| ONNX Runtime GPU | ✅ Windows (RTX 4060, CUDA 13.1, cuDNN 9) + WSL (verified). |
| sqlite-vec | ✅ Windows + Linux (native KNN). |
| PostgreSQL vector store | ✅ Windows (pgvector 0.8.4). WSL → PG blocked by `pg_hba.conf` (environment). |
| RubixML | ✅ Windows + Linux (isolated, subprocess harness). |
| Pure-PHP suite | ✅ 754 unit + PHPStan L8 + Psalm L3, Windows + Linux. |
| Safetensors | 🔴 Format detected, conversion to GGUF required (external Python tool). See §12. |
| `ferry_llama.dll/.so` | Machine-built, not committed. Build via `native/llama-wrapper/build.{ps1,sh}`. |
| llama under PHPUnit | Standalone-process only (ggml global ctor conflict); integration via subprocess harness. |

---

## 12. Safetensors — Conversion Required (Not a Loader)

`.safetensors` is a HuggingFace/PyTorch weight format, not a compute graph. It contains only
the numeric weight matrices — no architecture, no tokenizer, no graph. ONNX loads `.onnx`,
llama.cpp loads `.gguf`.

**To use a safetensors model** with FerryAI, convert it to GGUF via llama.cpp's
`convert_hf_to_gguf.py` (82 architectures supported, including Qwen, Llama, Mistral, Phi, Gemma).
Full step-by-step guide: [`docs/safetensors-conversion.md`](safetensors-conversion.md).

FerryAI provides `SafetensorsInspector` (pure PHP) which reads the safetensors header and
reports tensor names, shapes, dtypes and sizes without loading weights — useful for Model Hub
"what's inside" checks.

**Status:** Format detected by `FormatDetector`; metadata readable via `SafetensorsInspector`;
conversion to GGUF via external Python tool (one-time); GGUF inference through `LlamaBackend` (works).

---

## 13. ONNX GPU — Verified on Windows + WSL (manual cuDNN + CUDA runtime)

GPU builds (ORT 1.27.0 CUDA 13) installed on Windows + Linux/WSL. The ORT GPU download
does **not** bundle the CUDA runtime math libraries; they must be provided separately.

**cuDNN** → https://developer.nvidia.com/cudnn (manual download, requires NVIDIA account).

### Windows (verified 2026-07-06, RTX 4060, CUDA 13.1)

| DLL | Source | Status |
|-----|--------|--------|
| `cublas64_13.dll` / `cublasLt64_13.dll` / `cudart64_13.dll` | Shipped by `ankane/onnxruntime` | Present |
| `cudnn64_9.dll` + 9 aux DLLs | https://developer.nvidia.com/cudnn → Windows x64 zip | Extracted from CUDA 13.3 cuDNN package |
| `curand64_10.dll` | pip `nvidia-curand-cu12` wheel | Extracted from wheel |
| `cufft64_11.dll` / `cufftw64_11.dll` | pip `nvidia-cufft-cu12` wheel | Extracted from wheel |

**Verified working** on Windows (RTX 4060) — `availableDevices() = CUDA, CPU`,
`availableProviders() = TensorrtExecutionProvider, CUDAExecutionProvider, CPUExecutionProvider`.

Steps:
1. Replace vendor `onnxruntime.dll` (CPU) with GPU `onnxruntime.dll` from the ORT GPU zip
2. Place `onnxruntime_providers_cuda.dll` + `onnxruntime_providers_shared.dll` alongside
3. Copy cuDNN `.dll` files from NVIDIA cuDNN zip (`bin/13.3/x64/*.dll`) into the vendor lib dir
4. Extract `curand64_10.dll`, `cufft64_11.dll`, `cufftw64_11.dll` from pip wheels and copy in

### Linux / WSL (verified, RTX 4060)

| SONAME | Default CUDA 13.3 dev toolkit? | Status on WSL |
|--------|-----------------------------|---------------|
| `libcurand.so.10` | No (separate `libcurand-13-2` pkg) | Extracted from `.deb` without sudo |
| `libcufft.so.12` | No (separate `libcufft-13-2` pkg) | Extracted from `.deb` without sudo |
| `libcudnn.so.9` | No (separate cuDNN download) | Extracted from `.deb` without sudo |
| `libcublas.so.13` / `libcudart.so.13` | Yes | Present |

**Verified working** on WSL (RTX 4060) — `availableDevices() = cuda,cpu`,
all-MiniLM-L6-v2 embeddings produce identical output to CPU (cat/kitten 0.7882).
The libraries were extracted without sudo:
`apt-get download libcurand-13-2` → `ar x` → `tar xf` (same for libcufft).
cuDNN was extracted from the NVIDIA local-repo `.deb`. All `.so` files were placed
in the vendor ORT lib dir + `LD_LIBRARY_PATH`.

Full setup guide is in `README.md` (ONNX GPU on WSL section).

Mitigated: `OnnxBackend::load()` **falls back to the CPU execution provider** when the
resolved GPU provider fails to create a session, so embeddings keep working on an
incomplete GPU runtime without crashing.

---

## 14. PostgreSQL from WSL — Environment Blocker

WSL reaches the Windows-host PG at `192.168.96.1:5432`, but `pg_hba.conf` rejects the WSL
subnet. Fix: `host all all 192.168.0.0/16 md5` + restart. Environment config, no code change.
