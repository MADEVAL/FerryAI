# Configuration

FerryAI is configured once via `AI::config(array $config)`. Values are read through
`FerryAI\Core\AIConfig` (dot-notation `get()`), and most also have an environment fallback.

```php
AI::config([
    'backend'  => 'onnx',
    'device'   => 'cpu',
    'backends' => [ /* per-task model paths */ ],
]);
```

## Top-level keys

| Key | Default | Meaning |
|-----|---------|---------|
| `backend` | `auto` | Default task backend: `onnx`, `llama`, `cpu`/`cpu_native`, or `auto` (→ ONNX). |
| `device` | `auto` | `cpu`, `cuda`, or `auto`. Selects GPU offload where supported. |
| `model_cache` | temp dir | Where the model hub caches downloads (`FERRY_AI_MODEL_CACHE`). |
| `max_tokens` | `2048` | Default generation length. |
| `temperature` | `0.7` | Default sampling temperature (0 = greedy). |
| `top_p` | `1.0` | Default nucleus threshold. |

## `backends.*`

| Key | Used by | Notes |
|-----|---------|-------|
| `backends.embedding.model_path` | `AI::embed()`, `AI::similarity()` | Dir with `model.onnx` + `tokenizer.json`, or a `.onnx` file. |
| `backends.embedding.tokenizer_path` | embedding | Override tokenizer.json location. |
| `backends.classify.model_path` | `AI::classify()` | Classification ONNX model. |
| `backends.moderate.model_path` | `AI::moderate()` | Moderation ONNX model. |
| `backends.predict.model_path` | `AI::predict()` | RubixML `.rbm` model (see [security](security.md) / cpu backend). |
| `backends.llama.model_path` | `AI::chat()`, `AI::stream()` | GGUF model. |

## Embedding options

| Key | Default | Meaning |
|-----|---------|---------|
| `embedding.model` | `all-MiniLM-L6-v2` | Fallback model name if `backends.embedding.model_path` is unset. |
| `embedding.pooling` | `mean` | `mean`, `cls`, `eos`, `max`. |
| `embedding.normalize` | `true` | L2-normalise output vectors. |

## Vector store

| Key | Default | Meaning |
|-----|---------|---------|
| `vector.driver` | `sqlite` | `sqlite` or `pgsql` (`FERRY_AI_VECTOR_DRIVER`). |
| `vector.db_path` | `:memory:` | SQLite path. |
| `vector.dsn` / `vector.user` / `vector.password` | — | PostgreSQL connection (`FERRY_AI_PG_*`). |
| `vector.metric` | `cosine` | `cosine`, `euclidean`, `dot`. |

See [vector-store](vector-store.md).

## Model pool & observability

| Key | Default | Meaning |
|-----|---------|---------|
| `model_pool.max_memory_bytes` | ~2 GB | LRU eviction budget. |
| `model_pool.shared_memory` | `false` | Opt-in cross-worker weight sharing (`ext-shmop`). |
| `observability.metrics` | `false` | Record counters/timings (`FerryAI\Metrics`). |
| `observability.profiling` | `false` | Record per-op durations (`FerryAI\Profiler`). |
| `observability.logging` | `false` | JSON log lines (set `observability.log_file`). |

See [observability in the README](../README.md#observability--model-pool) and
[`examples/22-observability.php`](../examples/22-observability.php).

## Native library env vars

| Variable | Purpose |
|----------|---------|
| `FERRY_AI_LLAMA_WRAPPER` | Path to `ferry_llama.dll` (or set `FERRY_AI_LLAMA_LIB` to `llama.dll` in the same dir). |
| `FERRY_AI_VEC_EXTENSION_LIB` | Path to the sqlite-vec `vec0` library (opt-in native KNN). |
| `FERRY_AI_TOKENIZERS_LIB` | Path to the native tokenizers-cpp library (optional). |
| `FERRY_AI_RUBIXML_AUTOLOAD` | Path to an isolated `rubix/ml` autoloader. |

The directory holding native DLLs must be on `PATH` at runtime.
