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

`run()` accepts a single value, an array, or a `Generator` and returns a `Generator`.
A stage returning `null` filters the item out. Stages are PHP 8.5 pipe-operator compatible.

## Contract

```php
interface Stage
{
    public function __invoke(mixed $item): mixed;   // return null to drop
}

interface Pipeline
{
    public function pipe(Stage $stage): static;
    public function run(mixed $items): \Generator;
}
```

## Built-in stages

| Stage | Constructor | Purpose |
|-------|------------|---------|
| `TransformStage` | `(callable $fn)` | Map each item through a callable |
| `FilterStage` | `(callable $predicate)` | Drop items failing the predicate |
| `NormalizeStage` | `(?int $dimension)` | Normalize input shape / values |
| `ChunkStage` | `(Tokenizer $tok, int $size, ?int $overlap)` | Split text into overlapping chunks |
| `TokenizeStage` | `(Tokenizer $tok, ?int $maxLength)` | Encode text to token IDs |
| `EmbedStage` | `(Embedder $embedder)` | Text → embedding vector |
| `ClassifyStage` | `(Backend $backend, string $modelPath)` | Text → `ClassificationResult` |
| `StoreStage` | `(VectorStore $store, ?string $idPrefix)` | Persist results to a vector store |

## RAG example

Index documents into a vector store, then query:

```php
$tok = AI::tokenizer('/path/to/tokenizer.json');
$store = AI::vector('knowledge');

// Index phase: chunk → embed → store
AI::pipeline()
    ->pipe(new ChunkStage($tok, size: 256, overlap: 32))
    ->pipe(new EmbedStage($embedder))
    ->pipe(new StoreStage($store))
    ->run($documents);

// Query phase: embed → search
$query = 'What is PHP?';
$hits = $store->search(AI::embed($query)->vector, k: 5);
```

See [`examples/06-rag.php`](../examples/06-rag.php) and
[`examples/07-pipeline.php`](../examples/07-pipeline.php).

## Custom stages

Implement `Stage` and return `null` to drop an item:

```php
use FerryAI\Core\Contracts\Stage;

class UppercaseStage implements Stage
{
    public function __invoke(mixed $item): mixed
    {
        return is_string($item) ? strtoupper($item) : null;
    }
}

$out = AI::pipeline()->pipe(new UppercaseStage())->run(['hello', 'world']);
```

## Async (Fibers)

`FiberPipeline` extends `Pipeline` with `runAsync()` which returns a `Fiber`:

```php
$fiber = (new FiberPipeline())
    ->pipe(new EmbedStage($embedder))
    ->pipe(new StoreStage($store))
    ->runAsync($documents);

// Do other work...
while (!$fiber->isTerminated()) {
    sleep(0.01);
}
```

See [`examples/14-async.php`](../examples/14-async.php).
