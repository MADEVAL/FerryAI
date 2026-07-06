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

// Model metadata from HuggingFace
$info = $hub->info('sentence-transformers/all-MiniLM-L6-v2');
// $info → ModelMetadata { name, sizeBytes, extra }

// Raw HuggingFace API access
$client = new FerryAI\ModelHub\HuggingFaceClient();
$files = $client->listFiles('Qwen/Qwen3-0.6B');
$fileInfo = $client->fileInfo('Qwen/Qwen3-0.6B', 'README.md');
```

See [`examples/12-model-hub.php`](../examples/12-model-hub.php).

## Contract

```php
interface ModelHub
{
    public function info(string $modelId): ModelMetadata;
    public function search(string $query, int $limit): array;
    public function download(string $modelId, ?string $version): string;
    public function verify(string $path, ?string $expectedHash): bool;
    public function cachePath(string $modelId): string;
}
```

## Download & cache

`Downloader` fetches files with configurable retry (`RetryHandler`) and optional progress
logging. `CacheManager` provides an LRU cache under `model_cache` (config /
`FERRY_AI_MODEL_CACHE`). `HuggingFaceClient::downloadFile()` retries transient failures
automatically.

```php
$downloader = new FerryAI\ModelHub\Downloader();
$localPath = $downloader->download('sentence-transformers/all-MiniLM-L6-v2');
// → /tmp/ferry-ai-models/all-MiniLM-L6-v2/model.onnx
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
| Safetensors (`.safetensors`) | JSON header | — (No runtime support — convert to ONNX/GGUF) |
| RubixML (`.rbm`) | PHP serialized object | — |
| AiArchive (`.ai`) | ZIP with `manifest.json` | `AiArchive` |

`ModelIntrospector` reads metadata without loading the model:
```php
$introspector = new FerryAI\ModelHub\ModelIntrospector();
$meta = $introspector->inspect('/path/to/model.onnx');
// → ['format' => 'onnx', 'input_names' => [...], 'output_names' => [...]]
```

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

// Read
$archive = new AiArchive('/path/to/model.ai');
$onnxPath = $archive->extract('model.onnx');
```

> **safetensors** is detected but not loadable — it carries raw weights without a compute
> graph. Convert to ONNX (`optimum-cli export onnx`) or GGUF (`convert_hf_to_gguf.py`) first.
> See [safetensors-conversion.md](safetensors-conversion.md).
