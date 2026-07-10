# ferry-ai/inference-tensor

Pure-PHP tensor implementation for [FerryAI](https://github.com/MADEVAL/FerryAI), the inference-only
runtime for PHP 8.3+.

## Installation

```bash
composer require ferry-ai/inference-tensor
```

## What's inside

- **`ArrayTensor`** — a row-major, pure-PHP tensor implementing the `FerryAI\Core\Contracts\Tensor`
  contract (shape, reshape, element access, arithmetic).
- **`TensorFactory`** — creates tensors from nested arrays, flat data with an explicit `Shape`,
  zeros/ones/full fills.

`toArray()` materializes the whole tensor and is marked as expensive; prefer streaming access where possible.

## Requirements

- PHP >= 8.3
- `ferry-ai/inference-core`

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/tensor.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/tensor.md).
