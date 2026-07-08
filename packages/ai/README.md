# ferry-ai/inference-ai

The unified facade for [FerryAI](https://github.com/MADEVAL/FerryAI), the inference-only runtime for
PHP 8.5+. This package composes the core primitives and every backend into a single, static API.

## Installation

```bash
composer require ferry-ai/inference-ai
```

Installing this package pulls in `core` and all backends (ONNX, llama, CPU), the tokenizer, embedding,
pipeline, model-hub and vector packages.

## Usage

```php
use FerryAI\AI;

AI::config(['model_dir' => '/path/to/models']);

$reply = AI::chat('Explain PHP FFI in one sentence.');
$vector = AI::embed('hello world');
$score = AI::similarity('cat', 'kitten');
```

Call `AI::config()` once before any other method.

## What's inside

- **`AI`** — the all-static facade: `chat`, `stream`, `streamResponse`, `embed`, `similarity`,
  `classify`, `moderate`, `predict`, `pipeline`, `vector`, `hub`, `tokenizer`, `warmup`, `backend`,
  `device` and related helpers.
- **`AIFactory`**, **`BackendRegistry`**, **`TaskRouter`** — wiring, backend selection and routing.
- **`ModelPool`**, **`SharedMemoryManager`** — pooled model loading with LRU eviction.
- **`Observability`** — opt-in metrics, profiler and logger.
- **`NativeBinaryManager`** — resolves/downloads native libraries and verifies their SHA-256.
- **`FrameworkConfig`**, **`AsyncInference`** — framework config building and Fiber-based async inference.

## Requirements

- PHP >= 8.5
- `ext-ffi`
- Suggested: a PSR-17 factory (`nyholm/psr7` or `guzzlehttp/psr7`) for `AI::streamResponse()`

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/api-reference.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/api-reference.md).
