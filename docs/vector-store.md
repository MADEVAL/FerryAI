# Vector store

Store embeddings and run k-NN search. Two interchangeable backends implement the same
`FerryAI\Core\Contracts\VectorStore` contract.

| Backend | Search | Best for |
|---------|--------|----------|
| **SQLite** (default) | PHP brute-force, or native KNN via **sqlite-vec** when available | dev, demos, embedded |
| **PostgreSQL + pgvector** | native `<=>`/`<->`/`<#>`, HNSW/IVFFlat | production, large/concurrent |

## Quick use

```php
$store = AI::vector('docs');               // open/create a collection
$store->add('doc1', $vec->vector, ['lang' => 'en']);
$store->addBatch([
    ['id' => 'doc2', 'vector' => [...], 'metadata' => ['lang' => 'fr']],
]);

$hits = $store->search($query, k: 5, filter: ['lang' => ['eq' => 'en']]);
// [ ['id' => 'doc1', 'distance' => 0.02, 'metadata' => ['lang'=>'en']], ... ]
```

`VectorStore` also has `delete`, `deleteByFilter`, `update`, `count`, `dimension`,
`iterator`, `export`, `clear`. See [`examples/10-vector-store.php`](../examples/10-vector-store.php).

## Metadata filters

`MetadataFilter` supports `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `in`, `nin`, `contains`,
`exists`, and boolean `and`/`or`/`not`:

```php
$store->search($q, 10, ['and' => [
    ['category' => ['eq' => 'tools']],
    ['price' => ['lt' => 200]],
]]);
```

## SQLite

Default. Data brute-forced in PHP. To accelerate with native KNN, install the sqlite-vec `vec0`
library and set `FERRY_AI_VEC_EXTENSION_LIB`; the collection then uses vec0 virtual tables for
unfiltered search and falls back to brute force for filtered queries. See
[`examples/23-sqlite-vec.php`](../examples/23-sqlite-vec.php). Source:
[asg017/sqlite-vec](https://github.com/asg017/sqlite-vec/releases).

## PostgreSQL + pgvector

```php
AI::config(['vector' => [
    'driver'   => 'pgsql',
    'dsn'      => 'pgsql:host=127.0.0.1;port=5432',
    'user'     => 'postgres',
    'password' => 'postgres',
    'metric'   => 'cosine',
]]);
$store = AI::vector('docs');               // PostgresCollection (native ANN)
```

Requires `ext-pdo_pgsql` + the pgvector extension
([pgvector](https://github.com/pgvector/pgvector)). Vectors live in `vector(dim)` columns with
`jsonb` metadata. Build an ANN index with `PostgresVecIndex::createIndex($collection, 'hnsw')`.
See [`examples/21-postgres-vector.php`](../examples/21-postgres-vector.php).

Metrics map: `cosine → <=>`, `euclidean → <->`, `dot → <#>`.
