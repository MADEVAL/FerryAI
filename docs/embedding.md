# Embedding

Text → vector embeddings, backed by an ONNX model + tokenizer with configurable pooling and L2
normalization. `FerryAI\Embedding\Embedder` ties a `Backend`, a `Tokenizer` and a pooling strategy
together.

## Setup

Point config at a directory containing `model.onnx` + `tokenizer.json` (or a `.onnx` file):

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
$vec = AI::embed('Hello world');          // EmbeddingResult
$vec->dimension;                          // 384
$vec->vector;                             // float[]

$batch = AI::embed(['red', 'green', 'blue']);   // EmbeddingResult[]

$sim = AI::similarity('cat', 'kitten');   // cosine, ~0.79
```

See [`examples/01-hello-embedding.php`](../examples/01-hello-embedding.php),
[`examples/26-facade-embed.php`](../examples/26-facade-embed.php).

## Pooling strategies

`embedding.pooling`: `mean` (default), `cls`, `eos`, `max`. The strategy reduces the per-token
hidden states to a single sentence vector.

## Performance

`AIFactory` caches the `Embedder` per model name, so the ONNX model is loaded **once**, not on
every `embed()` call. Reuse the same configured facade for repeated calls.

## Built-in model dimensions

| Model | Dim |
|-------|-----|
| all-MiniLM-L6-v2 | 384 |
| all-mpnet-base-v2 | 768 |
| multilingual-e5-small | 384 |
| bge-small-en-v1.5 | 384 |

Store and search results with the [vector store](vector-store.md).
