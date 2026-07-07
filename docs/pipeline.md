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
A stage whose `process()` returns `null` filters the item out. A `Pipeline` itself is
invokable (`__invoke`), so it works with the PHP 8.5 pipe operator: `$input |> $pipeline`.

## Contract

```php
interface Stage
{
    public function process(mixed $input): mixed;   // return null to drop the item
    public function name(): string;
}

interface Pipeline
{
    public function pipe(Stage $stage): self;
    public function run(mixed $input): \Generator;
    public function stages(): array;                 // Stage[]
    public function __invoke(mixed $input): \Generator;
}
```

## Built-in stages

| Stage | Constructor | Purpose |
|-------|------------|---------|
| `TransformStage` | `(\Closure $transform, string $name = 'transform')` | Map each item through a callable |
| `FilterStage` | `(\Closure $predicate)` | Drop items failing the predicate |
| `NormalizeStage` | `()` | L2-normalize an embedding vector |
| `ChunkStage` | `(Tokenizer $tok, int $maxTokens = 512, int $overlap = 64)` | Split text into overlapping chunks |
| `TokenizeStage` | `(Tokenizer $tok, bool $addSpecialTokens = true)` | Encode text to token IDs |
| `EmbedStage` | `(Embedder $embedder)` | Text → embedding vector |
| `ClassifyStage` | `(Backend $backend, string $modelPath)` | Text → `ClassificationResult` |
| `StoreStage` | `(VectorStore $store)` | Persist results to a vector store |

## RAG example

Index documents into a vector store, then query:

```php
$tok = AI::tokenizer('/path/to/tokenizer.json');
$store = AI::vector('knowledge');

// Index phase: chunk → embed → store
AI::pipeline()
    ->pipe(new ChunkStage($tok, maxTokens: 256, overlap: 32))
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

Implement `Stage` and return `null` from `process()` to drop an item:

```php
use FerryAI\Core\Contracts\Stage;

class UppercaseStage implements Stage
{
    public function process(mixed $input): mixed
    {
        return is_string($input) ? strtoupper($input) : null;
    }

    public function name(): string
    {
        return 'uppercase';
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
