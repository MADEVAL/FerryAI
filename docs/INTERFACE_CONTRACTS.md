# Interface Contracts

The source of truth is the interface code itself in `packages/core/src/Contracts/`. This
document mirrors those signatures so they can be looked up without opening the source. If this
file and the code ever disagree, **the code wins** — update this file.

Namespace for every contract below: `FerryAI\Core\Contracts`.

## Implementations at a glance

| Contract | Implemented by |
|---|---|
| `Backend` | `OnnxBackend`, `LlamaBackend`, `CpuNativeBackend` |
| `Model` | `OnnxModel`, `LlamaModel`, `CpuNativeModel` |
| `Tensor` | `ArrayTensor`, `OnnxTensor`, `CpuNativeTensor` |
| `Tokenizer` | `PureBpeTokenizer`, `PureWordPieceTokenizer`, `HuggingFaceTokenizer` |
| `Embedder` | `Embedding\Embedder` |
| `VectorStore` | `Vector\Collection`, `Vector\PostgresCollection` |
| `Pipeline` | `Pipeline\Pipeline`, `Pipeline\FiberPipeline` |
| `Stage` | 8 stages under `Pipeline\Stages\` |
| `ModelHub` | `ModelHub\Hub` |
| `DataFrame` | `Dataframe\DataFrame` |

---

## `Backend`

Loads models and reports capability/availability. Backends never reference each other.

```php
interface Backend
{
    public function isAvailable(): bool;
    public function version(): string;
    public function availableDevices(): array;                 // Device[]
    public function load(string $source, ?Device $device = null): Model;
}
```

## `Model`

A loaded model ready for the forward pass.

```php
interface Model
{
    public function run(array $inputs): array;                 // name => output
    public function inputs(): array;                           // input specs
    public function outputs(): array;                          // output specs
    public function metadata(): ModelMetadata;
    public function device(): Device;
    public function unload(): void;
}
```

## `Tensor`

`extends \ArrayAccess<int, mixed>, \Countable, \JsonSerializable`. Fixed shape:
`$t[$i] = $v` is allowed, but appending (`$t[] = $v`) and `unset()` throw
`\BadMethodCallException`.

```php
interface Tensor extends \ArrayAccess, \Countable, \JsonSerializable
{
    public function shape(): Shape;
    public function dtype(): DType;
    public function to(Device $device): self;
    public function device(): Device;
    public function toArray(): array;                          // EXPENSIVE (forces copy)
    public function data(): mixed;                             // raw/native buffer
    public function add(self $other): self;
    public function sub(self $other): self;
    public function mul(self $other): self;
    public function matmul(self $other): self;
    public function transpose(?array $axes = null): self;
    public function reshape(Shape $newShape): self;
    public function slice(array $slices): self;
    public function __clone();
    public function jsonSerialize(): array;
    public function __serialize(): array;
    public function __unserialize(array $data): void;
}
```

## `Tokenizer`

Text ↔ token IDs. Special tokens are role-keyed (`bos`, `eos`, `unk`, `pad`, `cls`, `sep`, `mask`).

```php
interface Tokenizer
{
    public function encode(string $text, bool $addSpecialTokens = true): array;   // int[]
    public function decode(array $ids): string;
    public function encodeBatch(array $texts, bool $padToMaxLength = true): array; // int[][]
    public function vocabSize(): int;
    public function type(): TokenizerType;
    public function specialTokenId(string $tokenName): ?int;
    public function specialTokens(): array;                    // role => id
    public function countTokens(string $text): int;
    public function chunk(string $text, int $maxTokens = 512, int $overlap = 64): array; // string[]
}
```

## `Embedder`

Text → dense vector.

```php
interface Embedder
{
    public function embed(string $text): array;                // float[]
    public function embedBatch(array $texts): array;           // float[][]
    public function dimension(): int;
    public function normalize(array $vector): array;           // L2-normalised float[]
    public function cosineSimilarity(array $a, array $b): float;
    public function modelName(): string;
}
```

## `VectorStore`

Similarity search over stored vectors with optional metadata filtering.

```php
interface VectorStore
{
    public function add(string $id, array $vector, ?array $metadata = null): void;
    public function addBatch(array $items): void;
    public function search(array $queryVector, int $k = 10, ?array $filter = null): array;
    public function delete(string $id): void;
    public function deleteByFilter(array $filter): int;
    public function update(string $id, ?array $vector = null, ?array $metadata = null): void;
    public function count(): int;
    public function dimension(): int;
    public function collectionName(): string;
    public function iterator(): \Iterator;
    public function export(): array;
    public function clear(): void;
}
```

## `Pipeline`

Composable, lazy processing stages. `run()` returns a `Generator`.

```php
interface Pipeline
{
    public function pipe(Stage $stage): self;
    public function run(mixed $input): \Generator;
    public function stages(): array;                           // Stage[]
    public function __invoke(mixed $input): \Generator;
}
```

## `Stage`

A single pipeline step.

```php
interface Stage
{
    public function process(mixed $input): mixed;
    public function name(): string;
}
```

## `ModelHub`

Download, cache, verify and inspect models.

```php
interface ModelHub
{
    public function download(string $modelId, ?string $version = null): string;   // local path
    public function cached(string $modelId, ?string $version = null): ?string;
    public function verify(string $path, ?string $sha256 = null, ?string $signature = null): bool;
    public function introspect(string $path): ModelMetadata;
    public function downloadWithProgress(string $modelId, ?string $version = null): \Generator;
    public function remove(string $modelId, ?string $version = null): void;
    public function prune(?int $maxSizeBytes = null): int;     // bytes freed
    public function cacheSize(): int;
    public function warmup(array $modelIds): void;
}
```

## `DataFrame`

`extends \Iterator<int, array<string, mixed>>, \Countable`. Column-oriented tabular data.

```php
interface DataFrame extends \Iterator, \Countable
{
    public function columns(): array;                          // string[] column names
    public function dtypes(): array;                           // name => dtype
    public function numRows(): int;
    public function numCols(): int;
    public function filter(callable $predicate): self;
    public function sort(string $column, bool $ascending = true): self;
    public function groupBy(string $column): array;            // value => DataFrame
    public function aggregate(string $column, string $function): float|int;
    public function select(array $columns): self;
    public function column(string $name): array;
    public function row(int $index): array;
    public function toTensor(string $column): Tensor;
    public function toArray(): array;
    public function toCsv(string $path, bool $includeHeader = true): void;
}
```

---

Value objects, exceptions and enums: see [`core.md`](core.md) and [`api-reference.md`](api-reference.md).
