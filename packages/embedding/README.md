# ferry-ai/inference-embedding

Text embedding generation for [FerryAI](https://github.com/MADEVAL/FerryAI), the inference-only
runtime for PHP 8.3+.

## Installation

```bash
composer require ferry-ai/inference-embedding
```

## What's inside

- **`Embedder`** — turns text into vector embeddings using a backend model and a pooling strategy,
  returning `EmbeddingResult` value objects.
- **`Pooling\*`** — `MeanPooling`, `ClsPooling`, `EosPooling`, `MaxPooling` (all extending
  `AbstractPooling`) to reduce token-level hidden states into a single vector.
- **`EmbeddedModels`** — a registry of known embedding models and their pooling/normalization defaults.

## Requirements

- PHP >= 8.3
- `ferry-ai/inference-core`
- `ferry-ai/inference-onnx-backend` (suggested) — to run ONNX embedding models
- `ferry-ai/inference-tokenizer` (suggested) — to tokenize input text

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/embedding.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/embedding.md).
