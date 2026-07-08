# ferry-ai/inference-core

Core contracts, enums, value objects and exceptions for [FerryAI](https://github.com/MADEVAL/FerryAI) —
an inference-only runtime for PHP 8.5+ with a unified API over ONNX Runtime, llama.cpp and RubixML.

This is the base package: every other FerryAI package depends on it, and it depends on nothing internal.

## Installation

```bash
composer require ferry-ai/inference-core
```

## What's inside

- **Contracts** (`Contracts/`) — `Backend`, `Model`, `Tensor`, `Tokenizer`, `Embedder`, `VectorStore`,
  `Pipeline`, `Stage`, `DataFrame`, `ModelHub`. These signatures are the single source of truth;
  implementations never deviate from them.
- **Enums** (`Enums/`, backed strings) — `BackendType`, `Device`, `DType`, `DistanceMetric`,
  `TokenizerType`, `IndexType`, `GraphOptimizationLevel`, `QuantizationType`.
- **Value objects** (`ValueObjects/`, all `readonly`) — `Shape`, `SamplingParams`, `ModelMetadata`,
  `GenerationResult`, `EmbeddingResult`, `ClassificationResult`, `ChatMessage`.
- **Exceptions** (`Exception/`) — all extend `FerryAIException`, each exposing an `errorCode()`
  returning a `FERRY_AI_*` constant.
- **Utilities** — `PlatformDetector`, `RetryHandler`, `AIConfig`, `FFI\CdefGenerator`,
  `Tensor\CommonTensorOps`.

## Requirements

- PHP >= 8.5

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/core.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/core.md).
