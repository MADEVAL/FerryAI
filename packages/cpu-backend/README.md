# ferry-ai/inference-cpu-backend

CPU-native inference backend for [FerryAI](https://github.com/MADEVAL/FerryAI), the inference-only
runtime for PHP 8.3+. Runs table-based prediction models in pure PHP, with optional RubixML support.

## Installation

```bash
composer require ferry-ai/inference-cpu-backend
```

## What's inside

- **`CpuNativeBackend`**, **`CpuNativeModel`**, **`CpuNativeTensor`** — a dependency-free backend that
  implements the `Backend`/`Model`/`Tensor` contracts using pure-PHP arithmetic.
- **`RubixMLAdapter`** (optional) — loads and runs `rubix/ml` `.rbm` models for classification and
  regression when `rubix/ml` is installed.

## Requirements

- PHP >= 8.3
- `ferry-ai/inference-core`
- `rubix/ml` (optional) — required only for `.rbm` model inference

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/backends/cpu.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/backends/cpu.md).
