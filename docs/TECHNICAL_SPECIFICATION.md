# Technical Specification

FerryAI is an **inference-only** runtime for PHP 8.3+ that presents a single API over three
native engines (ONNX Runtime, llama.cpp, RubixML/Tensor). This document describes the
architecture; the per-file map is in [`FILE_TREE.md`](FILE_TREE.md) and the exact signatures
in [`INTERFACE_CONTRACTS.md`](INTERFACE_CONTRACTS.md).

## Architectural rules

1. **Inference-only** — load models, run the forward pass. No training/autograd/optimisers.
2. **FFI is the only bridge** to native code. No `shell_exec` to Python.
3. **Backend isolation** — backends do not know about each other; only the `ai` package composes them.
4. **Contracts are truth** — signatures live in `packages/core/src/Contracts/`; implementations do not deviate.
5. **Exceptions** — all extend `FerryAIException`, each with an `errorCode()` of the form `FERRY_AI_*`.
6. **Zero-copy** — do not copy data PHP↔native without need; `toArray()` is marked expensive.
7. **No hard framework coupling** — models are never committed to the repository.

## Layered composition

```
PHP application
      │
      ▼
FerryAI\AI                      (ai)         static facade; holds process-wide state
      │
      ├── AIFactory             (ai)         builds backends, tokenizers, embedders,
      │                                      pipelines, vector stores, model hub from AIConfig
      ├── BackendRegistry       (ai)         lazily instantiates + caches Backend instances
      ├── TaskRouter            (ai)         picks a BackendType per task
      ├── ModelPool             (ai)         preloaded Model cache (LRU, opt-in shared memory)
      └── Observability         (ai)         Metrics + Profiler + Logger wrapper
      │
      ▼
Backend (contract)  ──►  OnnxBackend │ LlamaBackend │ CpuNativeBackend
      │                     │              │                │
      │                    FFI            FFI          pure PHP / RubixML
      ▼                     ▼              ▼
  Model / Tensor      onnxruntime    ferry_llama       (RubixML estimator)
```

Only the `ai` package depends on all backends. Every other package depends solely on `core`.

## Task routing

`TaskRouter` maps each high-level task to a `BackendType` (see `TaskRouter::routeFor()`):

| Task | Chosen backend | Fallback |
|------|----------------|----------|
| `chat` / `stream` | `Llama` | `Onnx` (when llama unavailable) |
| `embedding` | `Onnx` | — |
| `classification` | `Onnx` | `CpuNative` |
| `prediction` | `CpuNative` | — |

`BackendRegistry::autoDetect()` probes availability in the order **Llama → Onnx → CpuNative**;
`CpuNativeBackend` is always available (pure PHP), so it is the ultimate fallback.

## The FFI boundary and mockable seams

FFI is untyped and cannot be exercised by unit tests or static analysis, so each native backend
splits its runtime behind an interface that unit tests mock (the *interface-and-mock* pattern):

| Backend | Seam interface | Production FFI implementation |
|---------|----------------|-------------------------------|
| ONNX | `Runtime\OnnxRuntimeInterface` | `Runtime\NativeOnnxRuntime` (+ `NativeOnnxSession`) |
| llama | `Runtime\LlamaRuntimeInterface` | `Runtime\NativeLlamaRuntime` (+ `NativeLlamaSession`) |
| CPU | `CpuBackend\Predictor` | `CpuBackend\RubixMLAdapter` |

The single FFI wrappers (`llama-backend/src/FFI/FerryLlama.php`, the ONNX `Native*` classes)
are the only code that calls `\FFI`. They are covered by the integration suite, not unit tests.
C headers are turned into `\FFI::cdef()` strings by `core/src/FFI/CdefGenerator.php`.

## Data flow (typical requests)

- **`AI::embed($text)`** → `TaskRouter::routeForEmbedding()` → `OnnxBackend` model +
  `Tokenizer` → pooling strategy (`embedding` package) → `EmbeddingResult`.
- **`AI::chat($messages)`** → `TaskRouter::routeForChat()` → `LlamaBackend` →
  `ChatFormatter` builds the prompt → `Sampler` selects tokens → `GenerationResult`.
- **`AI::predict($features)`** → `CpuNativeBackend` → `Predictor` (RubixML) → label/proba.

## Device selection

`Device` is an enum (`CPU`, `CUDA`, `ROCM`, `METAL`, `VULKAN`, `DIRECTML`, `OPENVINO`,
`OPENCL`, `AUTO`). For ONNX, `OnnxTypeMapper::providerNamesForDevice()` translates a `Device`
into ordered ONNX Runtime provider strings with a `CPUExecutionProvider` fallback (see
[`backends/onnx.md`](backends/onnx.md)). For llama, GPU offload is controlled by
`$nGpuLayers` in `LlamaModelParams`.

## Value objects and results

Inputs and outputs are immutable `readonly` value objects in `core` (`Shape`, `ModelMetadata`,
`ChatMessage`, `SamplingParams`, `GenerationResult`, `EmbeddingResult`, `ClassificationResult`).
Their fields are enumerated in [`core.md`](core.md) and [`api-reference.md`](api-reference.md).

## Error handling

Every failure path raises a subclass of `FerryAIException` (extends `\RuntimeException`) whose
`errorCode()` returns a stable `FERRY_AI_*` string, so callers can branch on machine-readable
codes rather than messages. The full code table is in [`core.md`](core.md).
