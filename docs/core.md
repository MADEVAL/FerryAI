# Core (`FerryAI\Core`)

The `core` package holds the contracts, enums, value objects, exceptions and a few shared
utilities. Contracts and value objects are summarised in `docs/api-reference.md`; this page
documents the utilities and enums.

## Configuration — `AIConfig`

Immutable configuration object. `AI::config()` builds one from your array over the defaults.

```php
$cfg = FerryAI\Core\AIConfig::fromArray(['backend' => 'llama', 'temperature' => 0.2]);
$cfg->get('backends.llama.model_path');   // dot-notation access
$cfg->backend();                          // BackendType enum
$cfg2 = $cfg->set('temperature', 0.7);    // returns a NEW instance (immutable)
$cfg['temperature'];                      // ArrayAccess read
```

It implements `ArrayAccess` for reads; writes (`$cfg['x'] = …`, `unset($cfg['x'])`) throw
`\LogicException` because the object is immutable — use `set()`.

## Utilities

- `Logger` — tiny JSON-lines PSR-style logger. Level is case-insensitive; an unknown level falls
  back to `warning`. `new Logger($file, 'warning')`.
- `PlatformDetector` — OS / architecture / shared-library extension detection (`dll`/`so`/`dylib`).
- `RetryHandler` — `retry(callable, $maxAttempts, $delayMs, 'exponential')` with backoff.
- `FFI\CdefGenerator` + `bin/generate-ffi` — turn a C header into an `\FFI::cdef()` string.

## Enums (`FerryAI\Core\Enums`)

`Device` (CPU, CUDA, ROCM, METAL, VULKAN, DIRECTML, OPENVINO, OPENCL, AUTO), `DType` (Float32,
Float16, Int32, Int64, String), `BackendType` (Onnx, Llama, CpuNative), `TokenizerType` (BPE,
WordPiece, SentencePiece, Unigram), `DistanceMetric` (COSINE, EUCLIDEAN, DOT), `IndexType` (HNSW,
IVF, FLAT), `QuantizationType` (FLOAT32, FLOAT16, INT8, BINARY), `GraphOptimizationLevel`.

## Value objects and exceptions

`Shape`, `ModelMetadata`, `ChatMessage`, `SamplingParams`, `GenerationResult`, `EmbeddingResult`,
`ClassificationResult` — see `docs/api-reference.md`. All exceptions extend `FerryAIException` and
expose `errorCode(): string` (`FERRY_AI_*`).

> `SamplingParams` includes `repetitionPenalty` / `frequencyPenalty` / `presencePenalty`, applied
> during llama generation (`SamplerMath::applyPenalties`).
