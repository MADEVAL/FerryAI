# Model hub

Download, cache, verify and inspect models from the HuggingFace Hub.
`FerryAI\ModelHub\Hub` is the facade (`AI::hub()`).

## Requirements

`ext-hash`, `ext-zip`; `ext-sodium` for Ed25519 signature verification; `ext-curl` recommended
for downloads. No API token is required for public models; set one for private repos.

## Usage

```php
$hub = AI::hub();

$info  = $hub->info('Qwen/Qwen3-0.6B');           // model metadata
$files = (new FerryAI\ModelHub\HuggingFaceClient())->listFiles('Qwen/Qwen3-0.6B');
```

See [`examples/12-model-hub.php`](../examples/12-model-hub.php).

## Download & cache

`Downloader` fetches files with retry (`RetryHandler`) and optional logging; `CacheManager`
provides an LRU cache under `model_cache` (config / `FERRY_AI_MODEL_CACHE`).
`HuggingFaceClient::downloadFile()` retries transient failures.

## Verification

- **SHA-256** via `Sha256Verifier`.
- **Ed25519** signatures via `SignatureVerifier` (needs `ext-sodium`).
- `verify_signatures` config gates enforcement.

## Format detection & inspection

`FormatDetector` recognises `onnx`, `gguf`, `safetensors`, `rbm`, and the `ai` archive format by
magic bytes. `ModelIntrospector` / `GgufInspector` / `OnnxInspector` read model metadata.

> **safetensors** is detected but not loaded — it carries raw weights without a compute graph.
> Convert to ONNX (`optimum-cli export onnx`) or GGUF (`convert_hf_to_gguf.py`) first. See
> `docs/DEBT_REPORT.md` §13.

## Streaming large models

`StreamLoader` memory-maps (`loadMmap()`) or streams (`loadStream()`, 1 MB chunks) very large
files so they are never fully read into PHP memory.
