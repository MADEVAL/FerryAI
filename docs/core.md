# Core (`FerryAI\Core`)

The `core` package holds the contracts, enums, value objects, exceptions and shared utilities.
Every other package depends only on `core` — it has zero internal dependencies.

## Configuration — `AIConfig`

Immutable configuration object. `AI::config()` builds one from your array, merged over defaults.
Implements `ArrayAccess` for reads (writes throw `\BadMethodCallException` — use `set()`).

```php
use FerryAI\Core\AIConfig;

$cfg = AIConfig::fromArray(['backend' => 'llama', 'temperature' => 0.2]);
$cfg->get('backends.llama.model_path');   // dot-notation access
$cfg->backend();                          // BackendType enum
$cfg->device();                           // Device enum
$cfg->set('temperature', 0.7);            // returns a NEW instance (immutable)
$cfg->has('backends.embedding');          // bool — key exists?
$cfg['temperature'];                      // ArrayAccess read
```

**Defaults** (set in `defaults()`):
```php
[
    'backend'       => 'auto',
    'device'        => 'auto',
    'model_cache'   => sys_get_temp_dir() . '/ferry-ai-models',
    'max_tokens'     => 2048,
    'temperature'    => 0.7,
    'top_p'          => 1.0,
    'stream_timeout' => 30,
    'verify_signatures' => true,
    'log_level'      => 'warning',
    'backends'       => [],
]
```

Dot notation works for any depth: `get('backends.llama.n_gpu_layers')`.

## Utilities

### Logger
Tiny JSON-lines PSR-style logger. Level is case-insensitive; unknown level falls back to `warning`.
```php
$log = new \FerryAI\Core\Logger('/tmp/ferry.log', 'debug');
$log->info('Model loaded', ['model' => $name]);
```

### PlatformDetector
OS / architecture / shared-library extension detection. Static methods:
```php
\FerryAI\Core\PlatformDetector::os();         // 'windows' | 'linux' | 'mac'
\FerryAI\Core\PlatformDetector::arch();       // 'x64' | 'arm64'
\FerryAI\Core\PlatformDetector::libExt();     // 'dll' | 'so' | 'dylib'
```

### RetryHandler
Retry a callable with configurable backoff:
```php
$handler = new \FerryAI\Core\RetryHandler();
$result = $handler->retry(
    fn() => $client->downloadFile($url),
    maxAttempts: 3,
    delayMs: 200,
    backoff: 'exponential'     // or 'linear'
);
```

### CdefGenerator
Turn a C header file into an `\FFI::cdef()`-compatible string (strips comments, macros, extern).
Also available as a CLI tool:
```bash
php bin/generate-ffi.php --header=llama.h --output=llama_cdef.txt
```

## Enums (`FerryAI\Core\Enums`)

| Enum | Values |
|------|--------|
| `BackendType` | `Onnx`, `Llama`, `CpuNative` |
| `Device` | `CPU`, `CUDA`, `ROCM`, `METAL`, `VULKAN`, `DIRECTML`, `OPENVINO`, `OPENCL`, `AUTO` |
| `DType` | `Float32`, `Float16`, `Int32`, `Int64`, `String` |
| `TokenizerType` | `BPE`, `WordPiece`, `SentencePiece`, `Unigram` |
| `DistanceMetric` | `COSINE`, `EUCLIDEAN`, `DOT` |
| `IndexType` | `HNSW`, `IVF`, `FLAT` |
| `QuantizationType` | `FLOAT32`, `FLOAT16`, `INT8`, `BINARY` |
| `GraphOptimizationLevel` | `All`, `Extended`, `Basic`, `Disable` |

## Value objects

All are `readonly` classes under `FerryAI\Core\ValueObjects\`:

| Class | Properties |
|-------|-----------|
| `Shape` | `array $dims`, `int $rank`, `int $size` (computed: `array_product($dims)`) |
| `ModelMetadata` | `string $name`, `int $sizeBytes`, `array $extra` |
| `ChatMessage` | `string $role` (`system`\|`user`\|`assistant`), `string $content` |
| `SamplingParams` | `float $temperature`, `float $topP`, `int $maxTokens`, `float $repetitionPenalty`, `float $frequencyPenalty`, `float $presencePenalty` |
| `GenerationResult` | `string $text`, `int $tokensGenerated`, `int $tokensPrompt`, `int $tokensTotal`, `float $durationMs` |
| `EmbeddingResult` | `array $vector` (float[]), `int $dimension`, `string $modelName` |
| `ClassificationResult` | `string $label`, `float $confidence`, `array $allScores` |

## Exceptions

All under `FerryAI\Core\Exception\`, all extend `FerryAIException` (extends `\RuntimeException`).

Each exposes `errorCode(): string` returning a `FERRY_AI_*` code:
- `FERRY_AI_BACKEND_UNAVAILABLE`
- `FERRY_AI_MODEL_NOT_FOUND`
- `FERRY_AI_MODEL_LOAD`
- `FERRY_AI_INFERENCE`
- `FERRY_AI_SHAPE_MISMATCH`
- `FERRY_AI_DEVICE_UNAVAILABLE`
- `FERRY_AI_TOKENIZER`
- `FERRY_AI_CONFIGURATION`
- `FERRY_AI_INVALID_STATE`

## Contracts

Interfaces under `FerryAI\Core\Contracts\` — see [API reference](api-reference.md) and
[INTERFACE_CONTRACTS.md](INTERFACE_CONTRACTS.md).
