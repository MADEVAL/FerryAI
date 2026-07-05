# API reference

The primary surface is the static `FerryAI\AI` facade. Call `AI::config()` once first.

## Facade — `FerryAI\AI`

| Method | Returns | Notes |
|--------|---------|-------|
| `config(array $config): void` | — | Configure backends, device, model paths. |
| `backend(string $name): void` | — | Switch the active backend (`onnx`/`llama`/`cpu`/`auto`). |
| `device(string $device): void` | — | Switch the active device. |
| `embed(string\|string[] $input)` | `EmbeddingResult` \| `EmbeddingResult[]` | Text → vector(s). |
| `similarity(string $a, string $b): float` | float | Cosine similarity. |
| `classify(mixed $input): ClassificationResult` | — | Needs `backends.classify.model_path`. |
| `moderate(string $text): array` | `{categories, flagged}` | Needs `backends.moderate.model_path`. |
| `predict(array $features): mixed` | — | RubixML `.rbm` via cpu backend. |
| `chat(array $messages, ?array $options): GenerationResult` | — | LLM chat. |
| `stream(array $messages, ?array $options): Generator<int,string>` | — | Token stream. |
| `streamResponse(array $messages, ?array $options): ResponseInterface` | — | PSR-7 SSE (needs a PSR-17 factory). |
| `pipeline(): Pipeline` | — | New processing pipeline. |
| `vector(string $collection): VectorStore` | — | Open/create a vector collection. |
| `hub(): ModelHub` | — | Model download/cache/verify. |
| `tokenizer(string $modelName): Tokenizer` | — | Tokenizer for a `tokenizer.json`. |
| `warmup(string[] $modelIds): void` | — | Preload models into the pool. |
| `reset(): void` | — | Clear all facade state. |

### Chat / stream options

`temperature` (0 = greedy), `top_p`, `top_k`, `max_tokens`, `sampler`
(`greedy`\|`top_k`\|`top_p`\|`grammar`), `grammar` (GBNF string or JSON-Schema array).

## Core contracts (`FerryAI\Core\Contracts`)

`Backend`, `Model`, `Tensor`, `Tokenizer`, `Embedder`, `VectorStore`, `Pipeline`, `Stage`,
`ModelHub`, `DataFrame`. Exact signatures live in
[`INTERFACE_CONTRACTS.md`](INTERFACE_CONTRACTS.md).

## Value objects (`FerryAI\Core\ValueObjects`)

`EmbeddingResult { array $vector; int $dimension; string $modelName }`,
`GenerationResult { string $text; int $tokensGenerated; int $tokensPrompt; int $tokensTotal; float $durationMs }`,
`ClassificationResult`, `ChatMessage`, `SamplingParams`, `ModelMetadata`, `Shape`.

## Exceptions (`FerryAI\Core\Exception`)

All extend `FerryAIException` and expose `errorCode(): string` (`FERRY_AI_*`):
`BackendNotAvailableException`, `ModelNotFoundException`, `ModelLoadException`,
`ShapeMismatchException`, `DeviceNotAvailableException`, `ConfigurationException`,
`TokenizerException`, `InferenceException`.

Full file/namespace map: [`FILE_TREE.md`](FILE_TREE.md).
