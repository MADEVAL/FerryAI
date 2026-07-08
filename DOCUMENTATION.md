# FerryAI — Definitive Documentation

> **PHP inference runtime. ONNX + llama.cpp + RubixML. One API. No Python.**
> Version 0.1.0 · 14 packages · PHP 8.5+ · MIT

---

## 1. What is FerryAI

FerryAI loads native AI libraries directly into PHP via FFI. No HTTP servers, no Python
sidecars, no Docker microservices. You get the same C API that Python uses.

**Backends:**
| Backend | Engine | What it does |
|---------|--------|-------------|
| ONNX | ONNX Runtime ≥1.18 | Embeddings (384d), classification, any `.onnx` model |
| Llama | llama.cpp | Chat, streaming, grammar-constrained generation |
| CPU Native | RubixML / Pure PHP | `.rbm` models, always-available fallback |

**Core principles:** inference-only (no training) · FFI is the only bridge to native code ·
backends never know about each other · contracts define truth · zero-copy where possible.

---

## 2. Quick Start

```bash
composer require ferry-ai/php-inference
```

### ONNX Runtime setup (Windows)

```powershell
# Download ONNX Runtime ≥1.18 from https://github.com/microsoft/onnxruntime/releases
# Extract to: vendor\ankane\onnxruntime\lib\onnxruntime-win-x64-{version}\lib\
# Place onnxruntime.dll and onnxruntime_providers_shared.dll there.

# Download a model from HuggingFace:
# https://huggingface.co/sentence-transformers/all-MiniLM-L6-v2
# You need: model.onnx, tokenizer.json, tokenizer_config.json
```

### llama.cpp setup (Windows)

```powershell
# Download from https://github.com/ggml-org/llama.cpp/releases
# Extract to C:\llama
# Build ferry_llama.dll via native\llama-wrapper\build.ps1

putenv('FERRY_AI_LLAMA_WRAPPER=C:\llama\ferry_llama.dll');
putenv('PATH=C:\llama;' . getenv('PATH'));

AI::config([
    'backend'  => 'llama',
    'backends' => ['llama' => ['model_path' => 'C:\llama\model.gguf']],
]);
```

### llama.cpp full build (`ferry_llama` wrapper)

Full llama.cpp inference through PHP is done via a thin `ferry_llama` C wrapper (it hides
llama.cpp's by-value struct params, which PHP FFI cannot pass safely). `LlamaBackend` uses
`NativeLlamaRuntime`, which drives llama.cpp through this flat wrapper (real CPU + GPU). Point it at
the wrapper via `FERRY_AI_LLAMA_WRAPPER=…\ferry_llama.dll` (or `FERRY_AI_LLAMA_LIB=…\llama.dll` in
the same dir) and add that dir to `PATH`; select the device with config `device: cpu|cuda`.

What you need (all in one dir, e.g. `C:\llama` on Windows or `/path/to/llama` on Linux, on `PATH` /
`LD_LIBRARY_PATH` at runtime):

1. **llama.cpp build** — shared libs (`llama` + `ggml*` + `ggml-cpu-*`);
   for GPU on Windows: `ggml-cuda.dll` + CUDA runtime; for GPU on Linux: build from source with
   `GGML_CUDA=ON` (see `native/llama-wrapper/README.md`).
   → https://github.com/ggml-org/llama.cpp/releases (CPU builds for all platforms; CUDA prebuilt for Windows)
2. **NVIDIA CUDA Toolkit** (for GPU) → https://developer.nvidia.com/cuda-downloads
3. **Matching headers** (same commit): `llama.h`, `ggml.h`, `ggml-cpu.h`, `ggml-backend.h`,
   `ggml-alloc.h`, `ggml-opt.h`, `gguf.h` → llama.cpp repo (`include/` + `ggml/include/`).
4. **A GGUF model** → https://huggingface.co (e.g. `bartowski/Qwen2.5-0.5B-Instruct-GGUF`).
5. **Compiler**: Visual Studio 2022 on Windows; `cc`/`gcc`/`clang` on Linux/macOS.

Then:

```powershell
# Build the wrapper (auto-creates llama.lib / ggml.lib import libs from the DLLs)
powershell -File native/llama-wrapper/build.ps1 -LlamaDir C:\llama

# Smoke-test CPU + GPU
$env:PATH = "C:\llama;" + $env:PATH
php native/llama-wrapper/ffi-smoke.php
```

On Linux/macOS use `native/llama-wrapper/build.sh /path/to/llama` (needs a Linux llama.cpp build +
`cc`); Linux CUDA needs a CUDA-enabled llama.cpp build. Sampling is per request: `temperature: 0`
→ greedy, `> 0` → nucleus; force one with `AI::chat($msgs, ['sampler' => 'top_k'])` or
`['grammar' => '<gbnf>']`. A native top-k pre-filter keeps sampling fast. Details, flat API and
limits: [`native/llama-wrapper/README.md`](native/llama-wrapper/README.md).

### ONNX GPU on Linux

The ONNX Runtime Linux GPU download does **not** bundle the CUDA runtime math libraries
(`libcurand`, `libcufft`, `libcudnn`) — those live in separate NVIDIA packages. The
CUDA dev toolkit provides `cublas` + `cudart` but NOT the math
libraries. They can be extracted from `.deb` packages using `apt-get download`
(useful when you cannot install system packages as root):

```bash
# 1 — Place the ONNX Runtime GPU build next to the vendor lib
cp /path/to/onnxruntime-gpu/onnxruntime-linux-x64-gpu_cuda13-*/lib/libonnxruntime*.so* \
   vendor/ankane/onnxruntime/lib/onnxruntime-linux-x64-*/lib/
cp /path/to/onnxruntime-gpu/onnxruntime-linux-x64-gpu_cuda13-*/lib/libonnxruntime_providers_{cuda,shared}.so \
   vendor/ankane/onnxruntime/lib/onnxruntime-linux-x64-*/lib/

# 2 — Extract CUDA runtime math libs from .deb packages (no root needed)
D=vendor/ankane/onnxruntime/lib/onnxruntime-linux-x64-*/lib
cd "$D"
for pkg in libcurand-13-2 libcufft-13-2; do
  apt-get download "$pkg"
  ar x ${pkg}_*.deb && tar xf data.tar.xz -C /tmp/ && find /tmp -name "*.so*" -exec cp {} . \; && rm -f *.deb control.tar.xz data.tar.xz debian-binary
done
# cuDNN — download for your CUDA version from https://developer.nvidia.com/cudnn,
# then extract the libcudnn*.so* files into the vendor lib dir:
dpkg-deb -x /path/to/cudnn.deb /tmp/cudnn_extract
find /tmp/cudnn_extract -name "libcudnn*.so*" -exec cp {} "$D" \;

# 3 — Add the vendor lib dir and the CUDA toolkit to LD_LIBRARY_PATH
export LD_LIBRARY_PATH="/usr/local/cuda/lib64:$LD_LIBRARY_PATH"

# Verify
cd /path/to/FerryAI
php -r "require 'vendor/autoload.php'; echo (new FerryAI\OnnxBackend\OnnxBackend())->availableDevices()[0]->value;"
# → cuda
```

> **Why this works:** the ONNX CUDA provider `.so` links against `libcurand.so.10`,
> `libcufft.so.12` and `libcudnn.so.9`. The CUDA dev toolkit (installed via `apt`)
> provides `cublas`/`cudart`; `apt-get download` + `ar x` + `tar xf` extracts the math
> libraries from their `.deb` packages without needing root. All `.so` files land
> in the vendor lib dir and `LD_LIBRARY_PATH` points the dynamic linker at them.

### ONNX GPU on Windows

The ORT Windows GPU zip ships `onnxruntime.dll` + provider DLLs but does **not**
bundle `curand`, `cufft`, or `cudnn`. Those must be obtained separately.

**Required dependencies:**

| DLL | Source | How to get |
|-----|--------|-----------|
| `onnxruntime.dll` + provider DLLs | ORT GPU zip (`onnxruntime-win-x64-gpu_cuda13-*.zip`) | github.com/microsoft/onnxruntime/releases |
| `cublas64_13.dll`, `cublasLt64_13.dll`, `cudart64_13.dll` | Shipped by `ankane/onnxruntime` | Already in `vendor/ankane/onnxruntime/lib/…/lib/` |
| `cudnn64_9.dll` + aux DLLs | **cuDNN** → https://developer.nvidia.com/cudnn | Download the Windows x64 zip for your CUDA version, extract the `bin/*/x64/*.dll` files |
| `curand64_10.dll` | pip `nvidia-curand-cu12` wheel | `pip download nvidia-curand-cu12 --no-deps` → unzip → `nvidia/curand/bin/curand64_10.dll` |
| `cufft64_11.dll`, `cufftw64_11.dll` | pip `nvidia-cufft-cu12` wheel | `pip download nvidia-cufft-cu12 --no-deps` → unzip → `nvidia/cufft/bin/cufft64_11.dll` |

**Setup steps:**

```powershell
# 1 — Replace the CPU ONNX Runtime with the GPU build
$vendorLib = "vendor\ankane\onnxruntime\lib\onnxruntime-win-x64-*\lib"
Copy-Item "path\to\onnxruntime-gpu\lib\onnxruntime.dll" -Destination $vendorLib -Force
Copy-Item "path\to\onnxruntime-gpu\lib\onnxruntime_providers_cuda.dll" -Destination $vendorLib -Force
Copy-Item "path\to\onnxruntime-gpu\lib\onnxruntime_providers_shared.dll" -Destination $vendorLib -Force

# 2 — Copy cuDNN DLLs from NVIDIA cuDNN zip
Copy-Item "C:\cudnn\bin\*\x64\cudnn*.dll" -Destination $vendorLib

# 3 — Download and extract curand + cufft via pip
pip download nvidia-curand-cu12 nvidia-cufft-cu12 --no-deps -d %TEMP%\cuda_dlls
# Rename .whl → .zip and extract; copy curand64_10.dll, cufft64_11.dll, cufftw64_11.dll
Copy-Item "%TEMP%\cuda_dlls\curand_extract\nvidia\curand\bin\curand64_10.dll" -Destination $vendorLib
Copy-Item "%TEMP%\cuda_dlls\cufft_extract\nvidia\cufft\bin\cufft64_11.dll" -Destination $vendorLib
Copy-Item "%TEMP%\cuda_dlls\cufft_extract\nvidia\cufft\bin\cufftw64_11.dll" -Destination $vendorLib

# 4 — Verify
php -r "require 'vendor/autoload.php'; var_dump((new FerryAI\OnnxBackend\OnnxRuntimeFactory())->availableProviders());"
# Expected: TensorrtExecutionProvider, CUDAExecutionProvider, CPUExecutionProvider
```

### Quick checks (native availability)

```bash
# ONNX Runtime available?
php -r "require 'vendor/autoload.php'; echo (new FerryAI\OnnxBackend\OnnxBackend())->isAvailable() ? 'OK' : 'FAIL';"

# ONNX GPU (should print "cuda,cpu")
LD_LIBRARY_PATH=vendor/ankane/onnxruntime/lib/onnxruntime-linux-x64-*/lib:/usr/local/cuda/lib64:$LD_LIBRARY_PATH \
php -r "require 'vendor/autoload.php'; \$b=new FerryAI\OnnxBackend\OnnxBackend(); echo implode(',',array_map(fn(\$d)=>\$d->value,\$b->availableDevices()));"

# llama.cpp available?
FERRY_AI_LLAMA_LIB=/path/to/llama/libllama.so LD_LIBRARY_PATH=/path/to/llama:$LD_LIBRARY_PATH \
php -r "require 'vendor/autoload.php'; echo (new FerryAI\LlamaBackend\LlamaBackend())->isAvailable() ? 'YES' : 'NO';"

# sqlite-vec available?
FERRY_AI_VEC_EXTENSION_LIB=/path/to/sqlite-vec/vec0.so php examples/23-sqlite-vec.php
```

On Windows use the corresponding `C:\llama\...` paths (backslashes + `PATH` instead of
`LD_LIBRARY_PATH`; the `OnnxBackend::load()` CPU-fallback handles missing GPU runtimes
automatically).

---

## 3. Architecture

```
PHP Application
    │
    AI Facade
    ├── Backend Registry ─── Task Router
    │   ├── OnnxBackend ──── FFI ──► onnxruntime.dll
    │   ├── LlamaBackend ─── FFI ──► ferry_llama.dll
    │   └── CpuNativeBackend ──── pure PHP
    │
    ├── Embedder ─── Tokenizer ─── Pipeline ─── VectorStore
    └── ModelHub ─── CacheManager ─── SignatureVerifier
```

**Packages:**

| Package | Namespace | Role |
|---------|-----------|------|
| `core` | `FerryAI\Core\` | Contracts, enums, value objects, exceptions, AIConfig |
| `tensor` | `FerryAI\Tensor\` | ArrayTensor, tensor factory |
| `onnx-backend` | `FerryAI\OnnxBackend\` | ONNX Runtime via `ankane/onnxruntime` |
| `llama-backend` | `FerryAI\LlamaBackend\` | llama.cpp samplers, grammar, ChatFormatter |
| `tokenizer` | `FerryAI\Tokenizer\` | Pure PHP BPE + WordPiece, native binding (optional) |
| `embedding` | `FerryAI\Embedding\` | Mean/CLS/EOS/Max pooling, Embedder |
| `vector` | `FerryAI\Vector\` | SQLite + PostgreSQL/pgvector vector store |
| `model-hub` | `FerryAI\ModelHub\` | HF download, cache, SHA-256+Ed25519 verify |
| `pipeline` | `FerryAI\Pipeline\` | 8 composable stages, Generator-based |
| `cpu-backend` | `FerryAI\CpuBackend\` | Always-available CPU fallback |
| `dataframe` | `FerryAI\DataFrame\` | Tabular data: Column-oriented, CSV/JSON I/O |
| `ai` | `FerryAI\` | Facade, factory, registry, metrics, profiler |
| `laravel` | `FerryAI\Laravel\` | Service provider + facade |
| `symfony` | `FerryAI\Symfony\` | Bundle + DI extension |

---

## 4. Facade API

```php
use FerryAI\AI;

// === Configuration ===
AI::config(['backend' => 'onnx', 'device' => 'cpu']);
AI::warmup(['sentence-transformers/all-MiniLM-L6-v2']);
AI::reset();
AI::backend('llama');         // 'onnx'|'llama'|'cpu'|'auto'
AI::device('cuda');           // 'cpu'|'cuda'|'metal'|'auto'

// === Inference ===
$vec = AI::embed('Hello world');              // → EmbeddingResult {vector, dimension, modelName}
$vecs = AI::embed(['a', 'b', 'c']);           // → EmbeddingResult[]
$sim = AI::similarity('cat', 'kitten');       // → float (cosine)
$ans = AI::chat([['role' => 'user', 'content' => 'Hi']]);        // → GenerationResult
foreach (AI::stream($messages) as $token) {}  // Generator<string>
$cls = AI::classify('positive review');       // → ClassificationResult
$mod = AI::moderate('some text');             // → {categories, flagged}
$pred = AI::predict(['feat_a' => 0.5]);       // → mixed

// === Subsystems ===
$pipeline = AI::pipeline()->pipe(new TransformStage(...))->pipe(new FilterStage(...));
$store = AI::vector('my-collection');
$hub = AI::hub();
$tok = AI::tokenizer('/path/to/tokenizer.json');
```

### AI::config() — full options

```php
AI::config([
    'backend'      => 'auto',           // 'onnx'|'llama'|'cpu'|'auto' → Onnx
    'device'       => 'auto',           // 'cpu'|'cuda'|'metal'|'auto'
    'model_cache'  => '/path/to/cache',
    'max_tokens'   => 2048,
    'temperature'  => 0.7,
    'top_p'        => 1.0,
    'verify_signatures' => true,
    'log_level'    => 'info',
    'stream_timeout' => 30,

    'backends' => [
        'onnx' => [
            'providers' => ['CUDA', 'CPU'],
            'graph_optimization' => 'ALL',
        ],
        'llama' => [
            'model_path' => '/path/to/model.gguf',
            'n_ctx' => 2048,
            'n_gpu_layers' => 0,
        ],
        'classify'  => ['model_path' => '/path/to/classifier.onnx'],
        'moderate'  => ['model_path' => '/path/to/moderation.onnx'],
        'predict'   => ['model_path' => '/path/to/model.rbm'],
    ],
    'embedding' => ['model' => 'all-MiniLM-L6-v2'],
    'vector'    => ['db_path' => ':memory:'],
]);
```

---

## 5. Core Types

### Enums

```php
FerryAI\Core\Enums\BackendType::Onnx    // 'onnx'
FerryAI\Core\Enums\BackendType::Llama   // 'llama'
FerryAI\Core\Enums\BackendType::CpuNative  // 'cpu_native'

FerryAI\Core\Enums\Device::CPU     // 'cpu', priority 10
FerryAI\Core\Enums\Device::CUDA    // 'cuda', priority 90
FerryAI\Core\Enums\Device::METAL   // 'metal'
FerryAI\Core\Enums\Device::VULKAN  // 'vulkan'
FerryAI\Core\Enums\Device::AUTO    // 'auto', priority 0 — auto-detect

FerryAI\Core\Enums\DType::Float32  // 4 bytes
FerryAI\Core\Enums\DType::Int64    // 8 bytes

FerryAI\Core\Enums\TokenizerType::BPE | WordPiece | SentencePiece | Unigram
FerryAI\Core\Enums\DistanceMetric::COSINE | EUCLIDEAN | DOT
FerryAI\Core\Enums\IndexType::HNSW | IVF | FLAT
```

### Value Objects

```php
new Shape([1, 3, 224, 224]) — rank()=4, size()=1×3×224×224, isStatic()=true

new ModelMetadata('MiniLM', '1.0', 'Sentence-Transformers', 'Apache-2.0', ['embedding'], 90_000_000)

new EmbeddingResult([0.1, -0.3, ...], 384, 'all-MiniLM-L6-v2')

new GenerationResult('Paris is the capital...', tokensGenerated: 5, tokensPrompt: 4, tokensTotal: 9, durationMs: 120.5)

new ClassificationResult('positive', 0.95, ['positive' => 0.95, 'negative' => 0.05])

new SamplingParams(temperature: 0.7, topP: 1.0, topK: 40, maxTokens: 2048)

ChatMessage::system('You are helpful.')
ChatMessage::user('What is PHP?')
ChatMessage::fromArray(['role' => 'user', 'content' => 'Hi'])
```

### Exceptions

All extend `FerryAI\Core\Exception\FerryAIException` (extends `\RuntimeException`).
Each has `errorCode(): string` returning `FERRY_AI_*`.

| Exception | `errorCode()` | Extra data |
|-----------|--------------|------------|
| `ModelNotFoundException` | `FERRY_AI_MODEL_NOT_FOUND` | `source()` |
| `ModelLoadException` | `FERRY_AI_MODEL_LOAD` | `path()`, `reason()` |
| `InferenceException` | `FERRY_AI_INFERENCE` | — |
| `ShapeMismatchException` | `FERRY_AI_SHAPE_MISMATCH` | `expected()`, `actual()` |
| `DeviceNotAvailableException` | `FERRY_AI_DEVICE_NOT_AVAILABLE` | `requestedDevice()` |
| `BackendNotAvailableException` | `FERRY_AI_BACKEND_NOT_AVAILABLE` | `backendType()`, `reason()` |
| `TokenizerException` | `FERRY_AI_TOKENIZER` | — |
| `ConfigurationException` | `FERRY_AI_CONFIGURATION` | `configKey()` |

---

## 6. Contracts (Interfaces)

All in `FerryAI\Core\Contracts\`. Implementations never deviate from these signatures.

### Backend

```php
interface Backend {
    public function availableDevices(): array;                    // Device[]
    public function load(string $source, ?Device $device = null): Model;
    public function version(): string;
    public function isAvailable(): bool;
}
```

### Model

```php
interface Model {
    public function run(array $inputs): array;
    public function inputs(): array;
    public function outputs(): array;
    public function metadata(): ModelMetadata;
    public function device(): Device;
    public function unload(): void;
}
```

### Tensor

```php
interface Tensor extends \ArrayAccess, \Countable, \JsonSerializable {
    public function shape(): Shape;
    public function dtype(): DType;
    public function to(Device $device): self;
    public function device(): Device;
    public function toArray(): array;
    public function data(): mixed;
    public function add(self $other): self;
    public function sub(self $other): self;
    public function mul(self $other): self;
    public function matmul(self $other): self;
    public function transpose(?array $axes = null): self;
    public function reshape(Shape $newShape): self;
    public function slice(array $slices): self;
}
```

### Tokenizer

```php
interface Tokenizer {
    public function encode(string $text, bool $addSpecialTokens = true): array;
    public function decode(array $ids): string;
    public function encodeBatch(array $texts, bool $padToMaxLength = true): array;
    public function vocabSize(): int;
    public function type(): TokenizerType;
    public function specialTokenId(string $tokenName): ?int;
    public function specialTokens(): array;
    public function countTokens(string $text): int;
    public function chunk(string $text, int $maxTokens = 512, int $overlap = 64): array;
}
```

### Embedder

```php
interface Embedder {
    public function embed(string $text): array;
    public function embedBatch(array $texts): array;
    public function dimension(): int;
    public function normalize(array $vector): array;
    public function cosineSimilarity(array $a, array $b): float;
    public function modelName(): string;
}
```

### VectorStore

```php
interface VectorStore {
    public function add(string $id, array $vector, ?array $metadata = null): void;
    public function addBatch(array $items): void;
    public function search(array $queryVector, int $k = 10, ?array $filter = null): array;
    public function delete(string $id): void;
    public function deleteByFilter(array $filter): int;
    public function update(string $id, ?array $vector = null, ?array $metadata = null): void;
    public function count(): int;
    public function dimension(): int;
    public function collectionName(): string;
    public function iterator(): \Iterator;
    public function export(): array;
    public function clear(): void;
}
```

### Pipeline + Stage

```php
interface Pipeline {
    public function pipe(Stage $stage): self;
    public function run(mixed $input): \Generator;
    public function stages(): array;
    public function __invoke(mixed $input): \Generator;
}

interface Stage {
    public function process(mixed $input): mixed;
    public function name(): string;
}
```

### ModelHub

```php
interface ModelHub {
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

---

## 7. Backends

### ONNX

```php
$onnx = new FerryAI\OnnxBackend\OnnxBackend();
$onnx->isAvailable();          // true if onnxruntime.dll found
$onnx->version();              // "1.27.0"
$onnx->availableDevices();     // [Device::CPU] or [Device::CUDA, Device::CPU]

$model = $onnx->load('/path/to/model.onnx');
$model->run(['input_ids' => [[101, 2023, 102]], 'attention_mask' => [[1, 1, 1]]]);
```

**Execution Providers:** `CPUExecutionProvider` (always) · `CUDAExecutionProvider` · `TensorrtExecutionProvider` · `CoreMLExecutionProvider` (macOS).

For GPU, use a GPU build of ONNX Runtime + CUDA Toolkit + cuDNN. See [backends/onnx](docs/backends/onnx.md).

### Llama

```php
$backend = new FerryAI\LlamaBackend\LlamaBackend();
$backend->isAvailable();
$backend->version();

// LLM with grammar:
$grammar = GbnfGrammar::fromJsonSchema([
    'type' => 'object',
    'properties' => ['name' => ['type' => 'string'], 'age' => ['type' => 'integer']],
]);
$sampler = (new SamplerFactory())->create('grammar', $grammar);
```

**Samplers:** `GreedySampler` · `TopKSampler` · `TopPSampler` · `GrammarSampler`
**Chat templates:** `llama3` · `chatml` · `mistral` · `gemma` · `phi`

See [backends/llama](docs/backends/llama.md).

### CPU Native (always available)

```php
$backend = new FerryAI\CpuBackend\CpuNativeBackend();
$backend->isAvailable();          // always true
$backend->availableDevices();     // [Device::CPU]
```

---

## 8. Embedding

```php
use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\Tokenizer\TokenizerFactory;
use FerryAI\Embedding\Embedder;

$tokenizer = (new TokenizerFactory())->createFromFile('/path/to/tokenizer.json');
$embedder = new Embedder('/path/to/model.onnx', new OnnxBackend(), $tokenizer, 'mean', normalize: true);

$vec = $embedder->embed('Hello world');       // 384 float values
$batch = $embedder->embedBatch(['a', 'b']);   // 2 × 384
$sim = $embedder->cosineSimilarity($vec, $embedder->embed('Hi'));  // 0.79
$norm = $embedder->normalize($vec);           // L2 = 1.0
```

**Pooling:** `'mean'` (default, attention-mask-aware) · `'cls'` (first token) ·
`'eos'` (last token) · `'max'` (element-wise max).

**Built-in models:** `all-MiniLM-L6-v2` (384d) · `all-mpnet-base-v2` (768d) ·
`multilingual-e5-small` (384d) · `bge-small-en` (384d).

---

## 9. Vector Store

```php
use FerryAI\Vector\CollectionManager;
use FerryAI\Vector\SQLiteStore;

$store = new SQLiteStore(':memory:');
$manager = new CollectionManager($store);
$col = $manager->create('docs', 384);

$col->add('doc1', [0.1, 0.2, ...], ['topic' => 'AI', 'year' => 2026]);
$col->addBatch([
    ['id' => 'doc2', 'vector' => [0.3, 0.4, ...], 'metadata' => ['topic' => 'PHP']],
]);
$results = $col->search($queryVec, k: 5);
```

**PostgreSQL + pgvector:** native ANN search with HNSW/IVFFlat indexes. See [vector-store](docs/vector-store.md).

---

## 10. Pipeline

```php
use FerryAI\Pipeline\Pipeline;
use FerryAI\Pipeline\Stages\{TransformStage, FilterStage, NormalizeStage, ChunkStage};

$pipeline = (new Pipeline())
    ->pipe(new TransformStage(strtoupper(...)))
    ->pipe(new FilterStage(fn(string $s): bool => strlen($s) > 3));

foreach ($pipeline->run(['hi', 'hello', 'greetings']) as $result) {
    echo $result;
}
```

**8 stages:** `ChunkStage` · `TokenizeStage` · `EmbedStage` · `NormalizeStage` ·
`StoreStage` · `ClassifyStage` · `FilterStage` · `TransformStage`.

---

## 11. Model Hub

```php
$hub = new FerryAI\ModelHub\Hub('/path/to/cache');

// HuggingFace API
$client = new FerryAI\ModelHub\HuggingFaceClient('hf_token');
$info = $client->getModelInfo('sentence-transformers/all-MiniLM-L6-v2');
$files = $client->listFiles('Qwen/Qwen3-0.6B');

// Verification
FerryAI\ModelHub\ModelVerifier::verify($path, $sha256);
FerryAI\ModelHub\Signature\Sha256Verifier::compute($path);

// Format detection
FerryAI\ModelHub\Format\FormatDetector::detect($path);
```

---

## 12. Infrastructure Services

```php
// Retry with backoff
$handler = new FerryAI\Core\RetryHandler();
$result = $handler->retry(fn() => riskyOperation(), maxAttempts: 3, backoff: 'exponential');

// Profiling
FerryAI\Profiler::start('inference');
// ... work ...
FerryAI\Profiler::end('inference');

// Metrics
FerryAI\Metrics::increment('requests', ['backend' => 'onnx']);

// Logging
$logger = new FerryAI\Core\Logger('/var/log/ferry-ai.log');

// Platform detection
FerryAI\Core\PlatformDetector::os();        // 'windows'|'macos'|'linux'
FerryAI\Core\PlatformDetector::arch();      // 'x86_64'|'aarch64'
```

---

## 13. Framework Integrations

### Laravel

```php
$provider = new FerryAI\Laravel\AIServiceProvider($app);
$provider->register();    // → AI::config() from env
\FerryAI\Laravel\Facades\AI::embed('text');
```

### Symfony

```php
$bundle = new FerryAI\Symfony\AIBundle();
$bundle->boot();          // → AI::config()
$extension = new FerryAI\Symfony\DependencyInjection\FerryAIExtension();
$extension->load([['backend' => 'llama', 'device' => 'cuda']]);
```

---

## 14. Testing

```bash
composer test                 # Unit tests — pure PHP, no native libs
composer test-integration     # Integration — ONNX Runtime + llama.cpp
composer check                # Gate: cs-fix + PHPStan lvl8 + Psalm lvl3 + tests
composer cs-fix               # Auto-fix style (PER-CS 2.0)
```

---

## 15. Documents

| File | Purpose |
|------|---------|
| `docs/TECHNICAL_SPECIFICATION.md` | Full architecture |
| `docs/INTERFACE_CONTRACTS.md` | Every method signature |
| `docs/FILE_TREE.md` | Complete file map |
| `docs/REPOSITORY_INFRASTRUCTURE.md` | CI/CD, composer, publishing |
| `docs/SOURCES.md` | External dependency audit |
| `docs/getting-started.md` | Installation & first run |
| `docs/configuration.md` | All config keys |
| `docs/api-reference.md` | Facade & contracts quick reference |
| `docs/backends/onnx.md` | ONNX Runtime setup & GPU |
| `docs/backends/llama.md` | llama.cpp setup & samplers |
| `docs/backends/cpu.md` | CPU backend (RubixML) |
| `docs/embedding.md` | Embeddings & pooling |
| `docs/vector-store.md` | Vector storage & search |
| `docs/pipeline.md` | Composable processing stages |
| `docs/model-hub.md` | HuggingFace download & cache |
| `docs/tokenizer.md` | Pure PHP BPE/WordPiece |
| `docs/tensor.md` | Tensor operations |
| `docs/core.md` | Enums, utilities, platform detection |
| `docs/streaming.md` | LLM token streaming |
| `docs/security.md` | Security model |
| `docs/troubleshooting.md` | Diagnostic guide |
| `docs/deployment.md` | Production deployment |
| `docs/laravel.md` | Laravel integration |
| `docs/symfony.md` | Symfony integration |
| `examples/` | 26 runnable examples |
