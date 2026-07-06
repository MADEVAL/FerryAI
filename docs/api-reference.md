# API reference

The primary surface is the static `FerryAI\AI` facade. Call `AI::config()` once first.

## Facade — `FerryAI\AI`

| Method | Returns | Notes |
|--------|---------|-------|
| `config(array $config): void` | — | Configure backends, device, model paths. Must be called first. |
| `backend(string $name): void` | — | Switch the active backend (`onnx`/`llama`/`cpu`/`auto`). |
| `device(string $device): void` | — | Switch the active device. |
| `embed(string\|string[] $input)` | `EmbeddingResult` \| `EmbeddingResult[]` | Text → vector(s). Single string returns one result; array returns array. |
| `similarity(string $a, string $b): float` | float | Cosine similarity of two texts. |
| `classify(mixed $input): ClassificationResult` | — | Needs `backends.classify.model_path`. |
| `moderate(string $text): array` | `{categories, flagged}` | Needs `backends.moderate.model_path`. |
| `predict(array $features): mixed` | — | RubixML `.rbm` via cpu backend. Needs `backends.predict.model_path`. |
| `chat(array $messages, ?array $options): GenerationResult` | — | LLM chat. Needs llama backend + GGUF model. |
| `stream(array $messages, ?array $options): Generator<int,string>` | — | Token-by-token generation stream. |
| `streamResponse(array $messages, ?array $options): ResponseInterface` | — | PSR-7 SSE response (needs a PSR-17 factory like `nyholm/psr7`). |
| `pipeline(): Pipeline` | — | Create a new processing pipeline. |
| `vector(string $collection): VectorStore` | — | Open or create a named vector collection. |
| `hub(): ModelHub` | — | Model download, cache, verify, format detection. |
| `tokenizer(string $modelName): Tokenizer` | — | Tokenizer for a `tokenizer.json` file path or model name. |
| `warmup(string[] $modelIds): void` | — | Preload models into the pool. |
| `reset(): void` | — | Clear all facade state (config, registry, pool, observability). |

### Chat / stream options

`AI::chat($messages, $options)` and `AI::stream($messages, $options)` accept:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `temperature` | float | config | 0 = deterministic (greedy), > 0 = stochastic |
| `top_p` | float | config | Nucleus sampling threshold |
| `top_k` | int | — | Top-K sampling candidates |
| `max_tokens` | int | config | Generation length cap |
| `sampler` | string | auto | Force `greedy`, `top_k`, `top_p`, or `grammar` |
| `grammar` | string\|array | — | GBNF grammar string or JSON-Schema array for constrained output |

### EmbeddingResult

```php
$vec = AI::embed('Hello');
$vec->vector;       // float[] — the embedding
$vec->dimension;    // int (e.g. 384)
$vec->modelName;    // string (e.g. "all-MiniLM-L6-v2")
```

### GenerationResult

```php
$reply = AI::chat([...]);
$reply->text;            // string — the generated text
$reply->tokensGenerated; // int
$reply->tokensPrompt;    // int
$reply->tokensTotal;     // int
$reply->durationMs;      // float
```

### ClassificationResult

```php
$res = AI::classify($input);
$res->label;        // string — predicted class
$res->confidence;   // float — probability
$res->allScores;    // array<string, float> — all class scores
```

## Core contracts (`FerryAI\Core\Contracts`)

All interfaces live in `packages/core/src/Contracts/` — these are the source of truth for
method signatures:

| Contract | Implemented by |
|----------|---------------|
| `Backend` | `OnnxBackend`, `LlamaBackend`, `CpuNativeBackend` |
| `Model` | `OnnxModel`, `LlamaModel`, `CpuNativeModel` |
| `Tensor` | `ArrayTensor`, `OnnxTensor`, `CpuNativeTensor`, `BackedTensor` |
| `Tokenizer` | `PureBpeTokenizer`, `PureWordPieceTokenizer`, `HuggingFaceTokenizer` |
| `Embedder` | `Embedding\Embedder` |
| `VectorStore` | `Vector\Collection`, `Vector\PostgresCollection` |
| `Pipeline` | `Pipeline\Pipeline`, `Pipeline\FiberPipeline` |
| `Stage` | 8 stages under `Pipeline\Stages\` |
| `ModelHub` | `ModelHub\Hub` |
| `DataFrame` | Tabular data: Column-oriented storage, CSV/JSON I/O |

## Value objects (`FerryAI\Core\ValueObjects`)

`EmbeddingResult { array $vector; int $dimension; string $modelName }`,
`GenerationResult { string $text; int $tokensGenerated; int $tokensPrompt; int $tokensTotal; float $durationMs }`,
`ClassificationResult { string $label; float $confidence; array $allScores }`,
`ChatMessage { string $role; string $content }`,
`SamplingParams { float $temperature; float $topP; int $maxTokens; float $repetitionPenalty; float $frequencyPenalty; float $presencePenalty }`,
`ModelMetadata { string $name; int $sizeBytes; array $extra }`,
`Shape { array $dims; int $rank }`.

## Exceptions (`FerryAI\Core\Exception`)

All extend `FerryAIException` and expose `errorCode(): string` (`FERRY_AI_*`):
`BackendNotAvailableException`, `ModelNotFoundException`, `ModelLoadException`,
`ShapeMismatchException`, `DeviceNotAvailableException`, `ConfigurationException`,
`TokenizerException`, `InferenceException`, `InvalidStateException`.

## Enums (`FerryAI\Core\Enums`)

- `BackendType` — `Onnx`, `Llama`, `CpuNative`
- `Device` — `CPU`, `CUDA`, `ROCM`, `METAL`, `VULKAN`, `DIRECTML`, `OPENVINO`, `OPENCL`, `AUTO`
- `DType` — `Float32`, `Float16`, `Int32`, `Int64`, `String`
- `DistanceMetric` — `COSINE`, `EUCLIDEAN`, `DOT`
- `TokenizerType` — `BPE`, `WordPiece`, `SentencePiece`, `Unigram`
- `IndexType` — `HNSW`, `IVF`, `FLAT`
- `QuantizationType` — `FLOAT32`, `FLOAT16`, `INT8`, `BINARY`

Full file/namespace map: [`FILE_TREE.md`](FILE_TREE.md).
