# Model hub

Download, cache, verify and inspect models from the HuggingFace Hub.
`FerryAI\ModelHub\Hub` is the facade entry point (`AI::hub()`).

## Requirements

- `ext-hash` (required) — SHA-256 verification.
- `ext-zip` (required) — `.ai` archive format.
- `ext-curl` (recommended) — HTTP downloads.
- `ext-sodium` (optional) — Ed25519 signature verification.

No API token is required for public models; set one via config or `FERRY_AI_HF_TOKEN` for
private/gated repos.

## Usage

```php
$hub = AI::hub();

// Download (returns the local path) and read metadata from the downloaded file
$path = $hub->download('sentence-transformers/all-MiniLM-L6-v2');
$meta = $hub->introspect($path);
// $meta → ModelMetadata { name, version, author, license, tags, sizeBytes, architecture?, ... }

// Raw HuggingFace API access
$client = new FerryAI\ModelHub\HuggingFaceClient();
$files = $client->listFiles('Qwen/Qwen3-0.6B');
$info  = $client->getModelInfo('Qwen/Qwen3-0.6B');
```

See [`examples/12-model-hub.php`](../examples/12-model-hub.php).

## Contract

```php
interface ModelHub
{
    public function download(string $modelId, ?string $version = null): string;
    public function cached(string $modelId, ?string $version = null): ?string;
    public function verify(string $path, ?string $sha256 = null, ?string $signature = null): bool;
    public function introspect(string $path): ModelMetadata;
    public function downloadWithProgress(string $modelId, ?string $version = null): \Generator;
    public function remove(string $modelId, ?string $version = null): void;
    public function prune(?int $maxSizeBytes = null): int;
    public function cacheSize(): int;
    public function warmup(array $modelIds): void;
}
```

`Hub` additionally offers `list()`, `register(name, path, ?sha256)` and `checkUpdates()`.

## Download & cache

`Downloader` fetches a single URL to a destination path with configurable retry
(`RetryHandler`) and optional progress logging. `CacheManager` provides an LRU cache under
`model_cache` (config / `FERRY_AI_MODEL_CACHE`). `HuggingFaceClient::downloadFile()` retries
transient failures automatically. Use `Hub::download()` for the high-level
"model id → local path" flow.

```php
$downloader = new FerryAI\ModelHub\Downloader();
$downloader->download($url, '/tmp/ferry-ai-models/model.onnx');           // void
$downloader->download($url, $dest, fn(int $done, int $total) => /* ... */ null);
```

## Verification

- **SHA-256** — `Sha256Verifier::verify($path, $expectedHash)` compares hashes.
- **Ed25519** — `SignatureVerifier::verify($path, $signature, $publicKey)` checks
  Ed25519 signatures (requires `ext-sodium`).
- `verify_signatures` config gates enforcement; when `false`, only SHA-256 check runs.

`ModelVerifier` composites both verifiers and is used by the Hub during download.

## Format detection & inspection

`FormatDetector` recognises file formats by magic bytes:

| Format | Detection | Inspector |
|--------|----------|-----------|
| ONNX (`.onnx`) | `ONNX` + protobuf header | `OnnxInspector` |
| GGUF (`.gguf`) | `GGUF` magic (0x46554747) | `GgufInspector` |
| Safetensors (`.safetensors`) | JSON header length prefix | `SafetensorsInspector` (metadata only — not loadable) |
| RubixML (`.rbm`) | PHP serialized object | — |
| AiArchive (`.ai`) | ZIP with `manifest.json` | `AiArchive` |

`ModelIntrospector::introspect()` (static) reads metadata without loading the model and
returns a `ModelMetadata`:
```php
$meta = FerryAI\ModelHub\ModelIntrospector::introspect('/path/to/model.onnx');
// → ModelMetadata { name, sizeBytes, architecture, ... }
```

`SafetensorsInspector::inspect($path)` returns the raw header dictionary and
`SafetensorsInspector::sizeBytes($path)` the on-disk size, for `.safetensors` files.

## Streaming large models

`StreamLoader` memory-maps or streams large files so they are never fully read into PHP memory:

```php
$loader = new FerryAI\ModelHub\StreamLoader();
$loader->loadMmap('/path/to/model.gguf');     // memory-mapped (most efficient)
$loader->loadStream('/path/to/model.gguf');   // 1 MB chunks
```

## AiArchive format

The `.ai` archive bundles a model with its tokenizer, metadata, and signatures in one ZIP
file, useful for deployment:

```php
use FerryAI\ModelHub\Format\AiArchive;

// Create
AiArchive::create('/output/model.ai', [
    'model.onnx' => '/path/to/model.onnx',
    'tokenizer.json' => '/path/to/tokenizer.json',
    'manifest.json' => json_encode(['name' => 'my-model', 'version' => '1.0']),
]);

// Inspect / validate / extract (all static)
AiArchive::list('/path/to/model.ai');                    // string[] entry names
AiArchive::validate('/path/to/model.ai');                // bool — has manifest.json
$extracted = AiArchive::extract('/path/to/model.ai', '/tmp/out');   // map name => path
```

> **safetensors** is detected but not loadable — it carries raw weights without a compute
> graph. Convert to ONNX (`optimum-cli export onnx`) or GGUF (`convert_hf_to_gguf.py`) first.
> See [safetensors-conversion.md](safetensors-conversion.md).
