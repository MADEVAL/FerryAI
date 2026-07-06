# Vector store

Store embeddings and run k-NN search. Two interchangeable backends implement the same
`FerryAI\Core\Contracts\VectorStore` contract.

| Backend | Search | Best for |
|---------|--------|----------|
| **SQLite** (default) | PHP brute-force, or native KNN via **sqlite-vec** when available | dev, demos, embedded |
| **PostgreSQL + pgvector** | native `<=>`/`<->`/`<#>`, HNSW/IVFFlat indexes | production, large collections, concurrent access |

## Quick use

```php
$store = AI::vector('docs');               // open or create a collection

// Add one or many
$store->add('doc1', $vec->vector, ['lang' => 'en', 'title' => 'Hello']);
$store->addBatch([
    ['id' => 'doc2', 'vector' => [...], 'metadata' => ['lang' => 'fr']],
    ['id' => 'doc3', 'vector' => [...], 'metadata' => ['lang' => 'en']],
]);

// Search
$hits = $store->search($query, k: 5, filter: ['lang' => ['eq' => 'en']]);
// [ ['id' => 'doc1', 'distance' => 0.02, 'metadata' => ['lang'=>'en']], ... ]

// CRUD
$store->get('doc1');                       // fetch by id
$store->update('doc1', $newVector);        // update vector only
$store->delete('doc1');                    // delete by id
$store->deleteByFilter(['lang' => ['eq' => 'fr']]);
$store->count();                           // how many vectors
$store->dimension();                       // vector dimension (0 if auto-detect)
$store->clear();                           // delete all
$store->export();                          // json-serializable snapshot
```

See [`examples/10-vector-store.php`](../examples/10-vector-store.php).

## Contract

```php
interface VectorStore
{
    public function add(string $id, array $vector, ?array $metadata = null): void;
    public function addBatch(array $items): void;
    public function search(array $query, int $k, ?array $filter = null): array;
    public function get(string $id): ?array;
    public function update(string $id, array $vector, ?array $metadata = null): void;
    public function delete(string $id): void;
    public function deleteByFilter(array $filter): void;
    public function count(): int;
    public function dimension(): int;
    public function clear(): void;
    public function export(): array;
    public function getIterator(): \Traversable;
}
```

## Metadata filters

`MetadataFilter` supports these operators on JSON metadata fields:

| Operator | Syntax | Example |
|----------|--------|---------|
| `eq` | `['field' => ['eq' => value]]` | Exact match |
| `neq` | `['field' => ['neq' => value]]` | Not equal |
| `gt` / `gte` | `['field' => ['gt' => 100]]` | Greater than / greater or equal |
| `lt` / `lte` | `['field' => ['lte' => 200]]` | Less than / less or equal |
| `in` / `nin` | `['field' => ['in' => [1,2,3]]]` | In / not in |
| `contains` | `['field' => ['contains' => 'substr']]` | String contains |
| `exists` | `['field' => ['exists' => true]]` | Key presence check |

Boolean combinators: `and`, `or`, `not` wrap multiple conditions:

```php
$store->search($q, 10, ['and' => [
    ['category' => ['eq' => 'tools']],
    ['price' => ['lt' => 200]],
    ['or' => [
        ['brand' => ['eq' => 'Makita']],
        ['brand' => ['eq' => 'DeWalt']],
    ]],
]]);
```

## SQLite

Default driver. Data is brute-forced in PHP via `BruteForceIndex`. To accelerate with native
KNN, install the sqlite-vec `vec0` extension and set `FERRY_AI_VEC_EXTENSION_LIB` — the
collection then uses vec0 virtual tables for unfiltered search and falls back to brute force
for filtered queries.

`SqliteVecExtension` manages the FFI binding to `vec0`. The SQLite database path is
`vector.db_path` (default `:memory:`).

See [`examples/23-sqlite-vec.php`](../examples/23-sqlite-vec.php).
Source: [asg017/sqlite-vec](https://github.com/asg017/sqlite-vec/releases).

## PostgreSQL + pgvector

```php
AI::config(['vector' => [
    'driver'   => 'pgsql',
    'dsn'      => 'pgsql:host=127.0.0.1;port=5432;dbname=ferryai',
    'user'     => 'postgres',
    'password' => 'postgres',
    'metric'   => 'cosine',
    'dimension' => 384,
]]);

$store = AI::vector('docs');               // PostgresCollection (native ANN)

// Build a HNSW index for fast approximate search
$pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=ferryai', 'postgres', 'postgres');
\FerryAI\Vector\PostgresVecIndex::createIndex('docs', 'hnsw', $pdo);
```

Requires `ext-pdo_pgsql` + the [pgvector](https://github.com/pgvector/pgvector) extension.
Vectors live in native `vector(dim)` columns with `jsonb` metadata.
See [`examples/21-postgres-vector.php`](../examples/21-postgres-vector.php).

Metrics map: `cosine → <=>`, `euclidean → <->`, `dot → <#>`.

## Export / Import

```php
$json = $store->export();                           // json-serializable array
\FerryAI\Vector\ExportImport::toJson($store, '/path/to/export.json');
\FerryAI\Vector\ExportImport::fromJson($newStore, '/path/to/export.json');
```
