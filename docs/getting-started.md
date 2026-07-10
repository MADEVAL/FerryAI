# Getting started

FerryAI runs ONNX, GGUF and RubixML models directly in PHP via FFI — no Python, no HTTP
sidecars. This guide gets you from install to a first embedding and chat.

## Requirements

- PHP **8.3+** with `ext-ffi`, `ext-json`, `ext-hash`, `ext-fileinfo`.
- Optional native libraries / models per capability — see the
  [Dependencies & downloads](../README.md#dependencies--downloads) matrix.

## Install

```bash
composer require ferry-ai/php-inference
```

## Configure

Everything goes through the `FerryAI\AI` facade after a one-time `config()`:

```php
use FerryAI\AI;

AI::config([
    'backend' => 'onnx',           // default task backend: onnx | llama | cpu
    'device'  => 'cpu',            // cpu | cuda | auto
    'backends' => [
        'embedding' => ['model_path' => '/path/to/all-MiniLM-L6-v2-onnx'],
        'llama'     => ['model_path' => '/path/to/model.gguf'],
    ],
]);
```

See [configuration](configuration.md) for every key.

## First embedding

```php
$vec = AI::embed('Hello world');   // EmbeddingResult
echo $vec->dimension;              // 384
$sim = AI::similarity('cat', 'kitten');   // 0.79
```

Requires ONNX Runtime + an embedding model — see [backends/onnx](backends/onnx.md) and
[embedding](embedding.md).

## First chat

```php
$reply = AI::chat([
    ['role' => 'user', 'content' => 'What is the capital of France?'],
]);
echo $reply->text;                 // "The capital of France is Paris."
```

Requires the `ferry_llama` wrapper + a GGUF model — see [backends/llama](backends/llama.md)
and [streaming](streaming.md).

## Next steps

- [Vector store](vector-store.md) — store and search embeddings (SQLite / PostgreSQL).
- [Pipeline](pipeline.md) — compose embed → store → search stages.
- [Model hub](model-hub.md) — download & verify models from HuggingFace.
- [Troubleshooting](troubleshooting.md) — when something does not load.
- Runnable examples: [`examples/`](../examples/README.md).
