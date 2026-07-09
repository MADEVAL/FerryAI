# ferry-ai/inference-pipeline

Composable, generator-based processing pipelines for [FerryAI](https://github.com/MADEVAL/FerryAI),
the inference-only runtime for PHP 8.3+.

## Installation

```bash
composer require ferry-ai/inference-pipeline
```

## What's inside

- **`Pipeline`** — chains `Stage` implementations; `run()` streams results via a `\Generator`.
- **`FiberPipeline`** — the same interface with cooperative Fiber-based scheduling.
- **`Stages\*`** — ready-made stages: `ChunkStage`, `TokenizeStage`, `EmbedStage`, `ClassifyStage`,
  `NormalizeStage`, `FilterStage`, `StoreStage`, `TransformStage`.

## Requirements

- PHP >= 8.3
- `ferry-ai/inference-core`
- Suggested (per stage): `ferry-ai/inference-tokenizer`, `ferry-ai/inference-embedding`,
  `ferry-ai/inference-vector`

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/pipeline.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/pipeline.md).
