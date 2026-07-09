# ferry-ai/inference-model-hub

Model download, caching, verification and inspection for [FerryAI](https://github.com/MADEVAL/FerryAI),
the inference-only runtime for PHP 8.3+.

## Installation

```bash
composer require ferry-ai/inference-model-hub
```

## What's inside

- **`Hub`** — the entry point: resolve, download and cache models by id/version.
- **`HuggingFaceClient`** — fetches model files and metadata from the HuggingFace Hub.
- **`CacheManager`** — local cache layout, pruning and lookups.
- **`Downloader`** / **`HttpStream`** — streaming downloads with resume support.
- **`Format\*`** — inspectors for GGUF / ONNX / safetensors metadata.
- **`Signature\*`** — SHA-256 integrity checks and Ed25519 signature verification.

## Requirements

- PHP >= 8.3
- `ferry-ai/inference-core`
- `ext-hash`, `ext-zip`
- Suggested: `ext-sodium` (Ed25519 signatures), `ext-curl` (faster downloads)

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/model-hub.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/model-hub.md).
