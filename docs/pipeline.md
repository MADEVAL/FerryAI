# Pipeline

Compose reusable processing stages with generator-based, lazy execution. Each stage implements
`FerryAI\Core\Contracts\Stage`; a `Pipeline` chains them.

## Basics

```php
use FerryAI\Pipeline\Stages\{TransformStage, FilterStage};

$out = AI::pipeline()
    ->pipe(new TransformStage(strtoupper(...)))
    ->pipe(new FilterStage(fn(string $x): bool => strlen($x) > 3))
    ->run(['hi', 'hello', 'hey']);        // Generator → ['HELLO']
```

`run()` accepts a single value, an array, or a Generator and returns a `Generator`. A stage
returning `null` filters the item out. `__invoke` supports the PHP 8.5 pipe operator.

## Built-in stages

| Stage | Purpose | Needs |
|-------|---------|-------|
| `TransformStage` | map each item via a callable | — |
| `FilterStage` | drop items failing a predicate | — |
| `NormalizeStage` | normalise input shape | — |
| `ChunkStage` | split text into chunks | tokenizer |
| `TokenizeStage` | tokenize text | tokenizer |
| `EmbedStage` | text → embedding | embedding backend |
| `ClassifyStage` | text → `ClassificationResult` | classifier model |
| `StoreStage` | persist to a vector store | vector store |

## RAG example

Index documents, then query — embed → store → search:

```php
AI::pipeline()
    ->pipe(new ChunkStage($tokenizer, size: 256))
    ->pipe(new EmbedStage($embedder))
    ->pipe(new StoreStage($store))
    ->run($documents);
```

See [`examples/06-rag.php`](../examples/06-rag.php) and
[`examples/07-pipeline.php`](../examples/07-pipeline.php).

## Async

`FiberPipeline::runAsync()` returns a `Fiber` for non-blocking execution; see
[`examples/14-async.php`](../examples/14-async.php).
