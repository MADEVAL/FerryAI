<p align="center">
  <img src="img/ferryai_banner.svg" alt="FerryAI" width="100%">
</p>

# FerryAI — native AI inference for PHP

**Run ONNX, GGUF, and RubixML models directly in PHP — no Python, no HTTP microservices, no Docker sidecars.**
One API, full FFI bridge to native engines. Inference-only. PHP 8.5+.

```php
use FerryAI\AI;

AI::config([
    'backend' => 'onnx',
    'device' => 'cpu',
    'backends' => ['embedding' => ['model_path' => '/path/to/all-MiniLM-L6-v2-onnx']],
]);

$vec = AI::embed('Hello world');              // reads model.onnx + tokenizer.json from the dir
echo $vec->dimension;                         // 384

$sim = AI::similarity('cat', 'kitten');       // 0.79

$store = AI::vector('docs');
$store->add('doc1', $vec->vector, ['title' => 'Getting Started']);
$hits = $store->search(AI::embed('hello')->vector, k: 5);

$results = AI::pipeline()
    ->pipe(new TransformStage(strtoupper(...)))
    ->pipe(new FilterStage(fn($x) => strlen($x) > 3))
    ->run(['hi', 'hello', 'hey']);
```

---

## Why FerryAI

| | FerryAI | Python sidecar |
|---|---|---|
| Deployment | One PHP process. `composer require` | Python runtime + HTTP server + process manager |
| Latency | Zero-copy FFI → sub-ms overhead | HTTP round-trip per inference |
| Memory | Shared weights across workers (shmop) | Duplicated per process |
| Debugging | PHP stack traces, xdebug | Cross-process tracing |
| Type safety | PHPStan level 8 + Psalm level 3 | mypy (optional) |

FerryAI loads native shared libraries (`onnxruntime.dll`, `llama.dll`) directly via PHP FFI —
the same C APIs that Python uses. No subprocess, no shell_exec, no Python.

---

## Backends

| Backend | Status | What it does |
|---------|--------|-------------|
| **ONNX** | 🟢 Production | Embeddings, classification, any `.onnx`. Runs on CPU (and CUDA GPU when the runtime + cuDNN/curand/cufft are present); e.g. all-MiniLM-L6-v2 produces 384d vectors. |
| **Llama** | 🟢 CPU + GPU | Real chat/generation via a thin `ferry_llama` C wrapper over llama.cpp; runs on CPU and CUDA GPU on Windows and Linux. See [`native/llama-wrapper`](native/llama-wrapper) and "LLM on CPU & GPU" below. |
| **CPU Native** | 🟢 Always | Pure-PHP tensor math (add/sub/mul/matmul/transpose/reshape/slice) + RubixML `.rbm` inference (optional). No native deps for tensor ops. |

---

## Vector store

Two interchangeable backends behind the same `VectorStore` contract — pick per environment:

| Backend | Status | Search | Best for |
|---------|--------|--------|----------|
| **SQLite** | 🟢 Production | PHP brute-force, or native KNN via **sqlite-vec** (vec0) when `FERRY_AI_VEC_EXTENSION_LIB` is set | Dev, demos, embedded, single-file |
| **PostgreSQL + pgvector** | 🟢 Production | Native `<=>` / `<->` / `<#>`, HNSW / IVFFlat indexes | Production, large collections, concurrent access |

```php
use FerryAI\AI;

// Opt in via config or FERRY_AI_VECTOR_DRIVER=pgsql (default: sqlite)
AI::config(['vector' => [
    'driver' => 'pgsql',
    'dsn' => 'pgsql:host=127.0.0.1;port=5432',
    'user' => 'postgres', 'password' => 'postgres',
]]);

$store = AI::vector('docs');                       // PostgresCollection (implements VectorStore)
$store->add('doc1', $vec->vector, ['lang' => 'en']);
$hits = $store->search($query, k: 5, filter: ['lang' => ['eq' => 'en']]);
```

Vectors live in native `vector(dim)` columns with `jsonb` metadata, using PostgreSQL +
pgvector. The SQLite backend transparently uses **sqlite-vec**
(vec0 virtual tables) for native KNN when the extension is available, and falls back to a
pure-PHP brute-force scan otherwise — filters always work. See
[`examples/21-postgres-vector.php`](examples/21-postgres-vector.php) and
[`examples/23-sqlite-vec.php`](examples/23-sqlite-vec.php).

---

## Observability & model pool

Cross-cutting instrumentation is applied at the facade layer (backends stay isolated) and is
**off by default** — enable per channel via config:

```php
AI::config(['observability' => ['metrics' => true, 'profiling' => true, 'logging' => true]]);

AI::embed('hello');                 // automatically timed, counted and logged
print_r(FerryAI\Metrics::report()); // counters + timing histograms
print_r(FerryAI\Profiler::report());// per-operation count/avg/min/max
```

`AI::warmup([...])` preloads models into a shared `ModelPool` (memory-bounded LRU eviction);
`classify()`/`moderate()`/`predict()`/`chat()` reuse pooled instances. Model downloads
(`Downloader`, `HuggingFaceClient`) retry transient failures via `RetryHandler`, and
`ModelPool` can opt into cross-worker weight sharing (`ext-shmop`). See
[`examples/22-observability.php`](examples/22-observability.php).

---

## Install

```bash
composer require ferry-ai/php-inference
```

Base requirements: **PHP 8.5+**, `ext-ffi`, `ext-json`, `ext-hash`, `ext-fileinfo`.

Everything else is **optional and on-demand** — install only what a feature needs.
FerryAI degrades gracefully (pure-PHP fallback or a clear "not available") when a native
library, extension, or model is missing. Canonical, version-checked source list:
[`docs/SOURCES.md`](docs/SOURCES.md).

## Dependencies & downloads

What each capability needs, why, where it goes, and the exact source. Versions intentionally
omitted — always take the latest compatible build from the linked source.

| Capability | PHP side (composer / ext) | Native artifact to download & why | Where it goes / how to enable | Source |
|-----------|---------------------------|-----------------------------------|-------------------------------|--------|
| Embeddings / classification (ONNX) | `ankane/onnxruntime` (auto) + `ext-ffi` | ONNX Runtime shared lib — CPU by default. On Linux run `php -r 'OnnxRuntime\\Vendor::check();'` to auto-download. | Extract into `vendor/ankane/onnxruntime/lib/…/lib/` | github.com/microsoft/onnxruntime/releases · onnxruntime.ai |
| ONNX model file | — | `model.onnx` + `tokenizer.json` — the actual network + vocab | Any dir; point config / `FERRY_AI_MODEL_DIR` at it | huggingface.co (e.g. `sentence-transformers/all-MiniLM-L6-v2`) |
| GPU for ONNX (CUDA / TensorRT) | — | ONNX Runtime **GPU** build **+** CUDA Toolkit **+** cuDNN (**+** TensorRT for the TRT provider). **cuDNN** requires manual download from https://developer.nvidia.com/cudnn | Replace the CPU libs with the GPU package; see ONNX GPU on Windows / Linux sections | onnxruntime releases (gpu zip) · developer.nvidia.com/cuda-downloads · developer.nvidia.com/cudnn · developer.nvidia.com/tensorrt |
| LLM chat / streaming (llama.cpp) | `ext-ffi` | llama.cpp shared libs `llama.*` + `ggml*.*` (+ deps) — the inference engine | Extract to a dir; set `FERRY_AI_LLAMA_LIB` and add the dir to `PATH` | github.com/ggml-org/llama.cpp/releases |
| GGUF model file | — | `*.gguf` quantized weights + tokenizer | Any dir; `backends.llama.model_path` | huggingface.co (e.g. `bartowski/*-GGUF`) |
| GPU for llama.cpp | — | CUDA-enabled llama.cpp build (`*-bin-win-cuda-*`) **+** NVIDIA CUDA Toolkit | Same as llama.cpp above; set `backends.llama.n_gpu_layers` | github.com/ggml-org/llama.cpp/releases · developer.nvidia.com/cuda-downloads |
| Native HuggingFace tokenizer (**optional**; pure-PHP BPE/WordPiece works without) | `ext-ffi` | tokenizers-cpp shared lib — optional, pure-PHP BPE/WordPiece covers all types | `FERRY_AI_TOKENIZERS_LIB` | github.com/mlc-ai/tokenizers-cpp |
| Vector store — SQLite (default) | `ext-pdo_sqlite` (bundled with PHP) | — (pure-PHP brute-force search) | works out of the box | sqlite.org (bundled) |
| Vector ANN — sqlite-vec | `ext-pdo_sqlite` | `vec0.{dll,so,dylib}` loadable extension — native KNN in SQLite | `FERRY_AI_VEC_EXTENSION_LIB` = path to the lib | github.com/asg017/sqlite-vec/releases |
| Vector store — PostgreSQL | `ext-pdo_pgsql` | PostgreSQL server **+** the **pgvector** extension — production ANN (`<=>`, HNSW/IVFFlat) | `FERRY_AI_VECTOR_DRIVER=pgsql` + `FERRY_AI_PG_DSN/USER/PASSWORD` (or `vector.*` config) | postgresql.org/download · github.com/pgvector/pgvector |
| CPU tabular ML (RubixML) | `rubix/ml` via `composer require` — **isolated** (its amphp/parallel ^1 conflicts with psalm's amphp) | `.rbm` serialized estimator | `FERRY_AI_RUBIXML_AUTOLOAD` = path to the isolated `vendor/autoload.php` | github.com/RubixML/ML · github.com/RubixML/Tensor |
| Model Hub / HuggingFace download | `ext-curl`, `ext-zip`, `ext-sodium` (Ed25519 verify) | models pulled from the Hub on demand | `FERRY_AI_MODEL_CACHE` = cache dir | huggingface.co |
| Safetensors models (conversion) | Python 3.10+, `torch`, `safetensors` (`pip install --user`) | `convert_hf_to_gguf.py` from llama.cpp — converts HuggingFace safetensors to GGUF | Run once; then point `backends.llama.model_path` at the `.gguf` output | [docs/safetensors-conversion.md](docs/safetensors-conversion.md) |
| Shared model weights across workers | `ext-shmop` | — | `model_pool.shared_memory=true` | PHP bundled |

> CUDA note: GPU support means shipping a **CUDA-enabled native build** alongside the
> **NVIDIA CUDA Toolkit**. llama.cpp GPU runs on both Windows and Linux.
> ONNX GPU additionally needs **cuDNN + curand + cufft** (see the ONNX GPU on Windows / Linux sections).

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

### Quick checks

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

> Full llama.cpp inference through PHP is done via a thin `ferry_llama` C wrapper (it hides
> llama.cpp's by-value struct params, which PHP FFI cannot pass safely). See the section below.

## LLM on CPU & GPU (llama.cpp)

llama.cpp inference runs through PHP on both CPU and CUDA GPU, on Windows and Linux:

| Path | Support |
|------|---------|
| Native `llama-cli` / `llama-bench` (CPU / CUDA) | ✅ standard llama.cpp tooling |
| **`AI::chat()` / `AI::stream()`** (CPU) | ✅ real chat via `LlamaBackend` + wrapper, Windows and Linux |
| **`AI::chat()` / `AI::stream()`** (GPU, CUDA) | ✅ layer offload via a CUDA-enabled llama.cpp build (`GGML_CUDA=ON`), Windows and Linux |
| **`AI::chat()`** (safetensors→GGUF models, e.g. Qwen3-0.6B f16) | ✅ after conversion to GGUF |
| **ONNX embeddings** (GPU, CUDA) | ✅ CUDA provider (`availableDevices = cuda,cpu`) when the runtime + math libs are present |

`LlamaBackend` uses `NativeLlamaRuntime`, which drives llama.cpp through the flat
`ferry_llama` wrapper (real CPU + GPU). Point it at the wrapper via
`FERRY_AI_LLAMA_WRAPPER=…\ferry_llama.dll` (or `FERRY_AI_LLAMA_LIB=…\llama.dll` in the same
dir) and add that dir to `PATH`; select the device with config `device: cpu|cuda`.
See [`examples/03-chat.php`](examples/03-chat.php), [`examples/04-streaming.php`](examples/04-streaming.php).

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
`cc`); Linux CUDA needs a CUDA-enabled llama.cpp build.

Details, flat API and limits: [`native/llama-wrapper/README.md`](native/llama-wrapper/README.md).
The wrapper is wired into `FerryAI\LlamaBackend` — `AI::chat()`/`AI::stream()` work on CPU and
GPU. Sampling is per request: `temperature: 0` → greedy, `> 0` → nucleus; force one with
`AI::chat($msgs, ['sampler' => 'top_k'])` or `['grammar' => '<gbnf>']`. A native top-k pre-filter
keeps sampling fast.

---

## Capabilities

| Component | Status |
|-----------|--------|
| ONNX Runtime FFI load | ✅ `isAvailable()`, version, CPU device |
| ONNX inference e2e | ✅ Embed text → 384d vector (all-MiniLM-L6-v2), cosine similarity |
| llama.cpp FFI load | ✅ shared lib loads, `llama_backend_init()`, `supports_mmap()` |
| llama.cpp inference via PHP FFI | ✅ CPU + GPU through the `ferry_llama` wrapper |
| GPU (CUDA) — llama.cpp | ✅ layer offload with a CUDA-enabled llama.cpp build |
| GPU (CUDA) — ONNX | ✅ CUDA provider on Windows and Linux (see the ONNX GPU sections below) |
| HuggingFace API | ✅ model search + download |
| Vector store | ✅ SQLite CRUD, brute-force + sqlite-vec (vec0) native KNN, metadata filter |
| Vector store (Postgres) | ✅ pgvector native `<=>` search, HNSW index, metadata filter |
| CPU backend | ✅ Tensor math (matmul/transpose/reshape/slice); RubixML `.rbm` predict/proba (isolated) |
| Shared memory (shmop) | ✅ Allocate, attach, detach |
| Async fibers | ✅ Suspend/resume, parallel tasks, timeout |
| Windows / Linux | ✅ Unit tests + static analysis; native backends run on both (llama CPU+CUDA, ONNX CPU+GPU, sqlite-vec, RubixML) |

---

## Packages

```
packages/
├── core/          Contracts, enums, value objects, exceptions, AIConfig
├── tensor/        ArrayTensor (pure PHP), BackedTensor, TensorFactory
├── onnx-backend/  ONNX Runtime via ankane/onnxruntime FFI
├── llama-backend/ llama.cpp FFI, samplers (greedy/top-k/top-p/grammar),
│                  GBNF grammar, JSON Schema→GBNF, ChatFormatter (5 templates)
├── tokenizer/     Pure PHP BPE + WordPiece (round-tripping, chunking)
├── embedding/     Mean/CLS/EOS/Max pooling, 4 built-in models
├── vector/        SQLite + PostgreSQL/pgvector store, brute-force & native ANN, metadata filtering
├── model-hub/     HF download, LRU cache, SHA-256+Ed25519, format detection
├── pipeline/      Generator-based stages (8 types)
├── cpu-backend/   Pure-PHP tensor math + optional RubixML (.rbm) tabular inference
├── ai/            Facade (AI::), backend registry, model pool, metrics, profiler
├── laravel/       Service provider + facade (env-based config)
└── symfony/       Bundle + DI extension
```

---

## Testing

```bash
composer test                # Unit tests — pure PHP
composer test-integration    # Integration — needs ONNX Runtime / llama.cpp / PostgreSQL
composer check               # cs-fix + PHPStan lvl8 + Psalm lvl3 + tests — fully green
```

---

## Examples

See [`examples/`](examples/) — 26 standalone scripts covering every capability:
embedding, tokenizer, chat, streaming, RAG, pipeline, vector store (SQLite +
sqlite-vec & PostgreSQL/pgvector), grammar, model hub, profiling, async, model pool,
observability, retry, CPU tensor math + RubixML, benchmarks, Laravel, Symfony.

```bash
set FERRY_AI_MODEL_DIR=C:\llama\all-MiniLM-L6-v2-onnx
php examples/01-hello-embedding.php
```

---

## Documents

Guides: [getting-started](docs/getting-started.md) ·
[configuration](docs/configuration.md) ·
[ONNX](docs/backends/onnx.md) / [llama.cpp](docs/backends/llama.md) ·
[embedding](docs/embedding.md) · [vector store](docs/vector-store.md) ·
[pipeline](docs/pipeline.md) · [model hub](docs/model-hub.md) ·
[safetensors → GGUF](docs/safetensors-conversion.md) ·
[tokenizer](docs/tokenizer.md) · [streaming](docs/streaming.md) ·
[security](docs/security.md) · [deployment](docs/deployment.md) ·
[Laravel](docs/laravel.md) / [Symfony](docs/symfony.md) ·
[troubleshooting](docs/troubleshooting.md) · [API reference](docs/api-reference.md) ·
[CHANGELOG](CHANGELOG.md)

| Document | Purpose |
|----------|---------|
| [`docs/TECHNICAL_SPECIFICATION.md`](docs/TECHNICAL_SPECIFICATION.md) | Architecture |
| [`docs/FILE_TREE.md`](docs/FILE_TREE.md) | Complete file map |
| [`docs/INTERFACE_CONTRACTS.md`](docs/INTERFACE_CONTRACTS.md) | Interface signatures |
| [`docs/SOURCES.md`](docs/SOURCES.md) | External stack reference |
| [`docs/README.md`](docs/README.md) | Full navigator |


