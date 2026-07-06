# Interface Contracts

Every contract described here has a corresponding implementation. The source of truth is the
interface code itself in `packages/core/src/Contracts/`, and the quick reference is
`docs/api-reference.md`.

## Contracts and their implementations

| Contract (`FerryAI\Core\Contracts\…`) | Implemented by |
|---|---|
| `Backend` | `OnnxBackend`, `LlamaBackend`, `CpuNativeBackend` |
| `Model` | `OnnxModel`, `LlamaModel`, `CpuNativeModel` |
| `Tensor` | `ArrayTensor`, `OnnxTensor`, `CpuNativeTensor`, `BackedTensor` |
| `Tokenizer` | `PureBpeTokenizer`, `PureWordPieceTokenizer`, `HuggingFaceTokenizer` |
| `Embedder` | `Embedding\Embedder` |
| `VectorStore` | `Vector\Collection`, `Vector\PostgresCollection` |
| `Pipeline` | `Pipeline\Pipeline`, `Pipeline\FiberPipeline` |
| `Stage` | 8 stages under `Pipeline\Stages\` |
| `ModelHub` | `ModelHub\Hub` |
| `DataFrame` | `Dataframe\DataFrame` |

Value objects, exceptions and enums: see `docs/core.md` and `docs/api-reference.md`.
