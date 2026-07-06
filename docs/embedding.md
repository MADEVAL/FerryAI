# Embedding

Text → vector embeddings, backed by ONNX models with configurable pooling and L2 normalization.
`FerryAI\Embedding\Embedder` ties a `Backend`, a `Tokenizer` and a `PoolingStrategy` together.

## Setup

Point config at a directory containing `model.onnx` + `tokenizer.json` (or a standalone `.onnx` file):

```php
AI::config([
    'backend'  => 'onnx',
    'backends' => ['embedding' => ['model_path' => '/path/to/all-MiniLM-L6-v2-onnx']],
    'embedding' => ['pooling' => 'mean', 'normalize' => true],
]);
```

Requires ONNX Runtime — see [backends/onnx](backends/onnx.md).

## Usage

```php
// Single text
$vec = AI::embed('Hello world');          // EmbeddingResult
$vec->dimension;                          // 384
$vec->vector;                             // float[]
$vec->modelName;                          // 'all-MiniLM-L6-v2-onnx'

// Batch
$batch = AI::embed(['red', 'green', 'blue']);   // EmbeddingResult[]

// Similarity
$sim = AI::similarity('cat', 'kitten');   // cosine similarity, ~0.79
```

See [`examples/01-hello-embedding.php`](../examples/01-hello-embedding.php),
[`examples/26-facade-embed.php`](../examples/26-facade-embed.php).

## Pooling strategies

After running the model forward pass, each token position has a hidden state. The pooling
strategy reduces that to a single fixed-size vector:

| Strategy | How it works | Best for |
|----------|-------------|----------|
| `mean` (default) | Average all token embeddings, excluding padding | General-purpose sentence embeddings |
| `cls` | Take the `[CLS]` token embedding | BERT-family models |
| `eos` | Take the `</s>` / `[SEP]` token embedding | GPT-style decoder models |
| `max` | Element-wise max over all token positions | Sensitivity to specific features |

Configure via `embedding.pooling` in config.

## Contract

```php
interface Embedder
{
    public function embed(string $text): array;          // float[]
    public function embedBatch(array $texts): array;     // float[][]
    public function dimension(): int;
    public function modelName(): string;
    public function cosineSimilarity(array $a, array $b): float;
}
```

## Performance

`AIFactory` caches the `Embedder` per model name — the ONNX model is loaded **once**, not
on every `embed()` call. Reuse the same facade for repeated calls.

## Built-in model dimensions

`EmbeddedModels` maps known model IDs to their default configurations:

| Model | Dim | Pooling |
|-------|-----|---------|
| `all-MiniLM-L6-v2` | 384 | mean |
| `all-mpnet-base-v2` | 768 | mean |
| `multilingual-e5-small` | 384 | mean |
| `bge-small-en-v1.5` | 384 | cls |

These models are available on HuggingFace
([sentence-transformers](https://huggingface.co/sentence-transformers)). Export to ONNX with
`optimum-cli export onnx --model <model-id> <output-dir>`.

Store and search results with the [vector store](vector-store.md).
