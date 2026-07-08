# ferry-ai/inference-dataframe

Tabular data handling for [FerryAI](https://github.com/MADEVAL/FerryAI), the inference-only runtime
for PHP 8.5+. Typed columns, CSV/JSON I/O and Tensor conversion.

## Installation

```bash
composer require ferry-ai/inference-dataframe
```

## What's inside

- **`DataFrame`** / **`Column`** — typed, column-oriented tabular data with selection, filtering and
  conversion to a `Tensor`.
- **`IO\CsvReader`**, **`IO\CsvWriter`**, **`IO\JsonReader`** — read/write tabular files.
- **`IO\ParquetReader`** — validates Parquet magic bytes; full Parquet decoding is planned for a
  future release (use CSV or JSON for now).

## Requirements

- PHP >= 8.5
- `ferry-ai/inference-core`
- `ferry-ai/inference-tensor`

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).
