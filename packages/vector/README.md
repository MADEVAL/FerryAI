# ferry-ai/inference-vector

Vector storage and similarity search for [FerryAI](https://github.com/MADEVAL/FerryAI), the
inference-only runtime for PHP 8.5+. Backed by SQLite or PostgreSQL/pgvector.

## Installation

```bash
composer require ferry-ai/inference-vector
```

## What's inside

- **`SQLiteStore`** / **`Collection`** — SQLite-backed store; brute-force search out of the box,
  optional native KNN via the sqlite-vec (`vec0`) extension (`SqliteVecExtension`).
- **`PostgresStore`** / **`PostgresCollection`** — PostgreSQL + pgvector store with native ANN
  (`<=>`, `<->`, `<#>`) and HNSW / IVFFlat indexes.
- **`MetadataFilter`** — structured filtering on stored metadata.
- **`ExportImport`**, **`CollectionManager`** — portability and collection lifecycle helpers.

## Requirements

- PHP >= 8.5
- `ferry-ai/inference-core`
- `ext-pdo`
- Suggested: `ext-pdo_sqlite`, `ext-pdo_pgsql`, `ext-ffi` (sqlite-vec), a running PostgreSQL + pgvector

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/vector-store.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/vector-store.md).
