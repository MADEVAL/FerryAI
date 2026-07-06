# Interface Contracts

> **Every contract described here is implemented.** The signature listings have been removed; the
> **source of truth is the interface code itself** in `packages/core/src/Contracts/`, and the
> quick reference is `docs/api-reference.md`.

## Contracts and their implementations

| Contract (`FerryAI\Core\Contracts\…`) | Implemented by |
|---|---|
| `Backend` | `OnnxBackend`, `LlamaBackend`, `CpuNativeBackend` |
| `Model` | `OnnxModel`, `LlamaModel`, `CpuNativeModel` |
| `Tensor` | `ArrayTensor`, `OnnxTensor`, `CpuNativeTensor` (`BackedTensor` is a Phase-1 stub — see DEBT §3) |
| `Tokenizer` | `PureBpeTokenizer`, `PureWordPieceTokenizer`, `HuggingFaceTokenizer` |
| `Embedder` | `Embedding\Embedder` |
| `VectorStore` | `Vector\Collection`, `Vector\PostgresCollection` |
| `Pipeline` | `Pipeline\Pipeline`, `Pipeline\FiberPipeline` |
| `Stage` | 8 stages under `Pipeline\Stages\` |
| `ModelHub` | `ModelHub\Hub` |
| `DataFrame` | **Not implemented** — the `dataframe` package is not created (see DEBT §7) |

Value objects, exceptions and enums: see `docs/core.md` and `docs/api-reference.md`.

> Only the `DataFrame` contract lacks an implementation package; it is tracked in
> `docs/DEBT_REPORT.md`.
