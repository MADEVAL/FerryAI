# FerryAI — Developer Guide (ferry-ai/php-inference)

**Inference-only** runtime for PHP 8.5+: unified API over native engines
(ONNX Runtime, llama.cpp, RubixML/Tensor). No training.

- **Direct inference** — runs inside the PHP process, no Python microservices.
  Native bridge: **PHP FFI**.
- Namespace: `FerryAI\` · Vendor: `ferry-ai/*` · Monorepo: `packages/{name}/`

## Packages (dependency direction)

`core` is the base and depends on nothing internal; every other package depends on `core`. Backends
never depend on each other — only `ai` composes them. Framework adapters depend only on `ai`.

```
core  ◄──  tensor · onnx-backend · llama-backend · cpu-backend · tokenizer ·
           embedding · pipeline · model-hub · vector · dataframe
ai    ──►  core + all backends + tokenizer + embedding + pipeline + model-hub + vector
laravel · symfony  ──►  ai
```

| Package | Entry points / purpose |
|---|---|
| `core` | Contracts, Enums, ValueObjects, Exceptions; `PlatformDetector`, `RetryHandler`, `AIConfig`, `FFI\CdefGenerator`, `Tensor\CommonTensorOps` (trait shared with cpu-backend) |
| `tensor` | `ArrayTensor` (pure-PHP, row-major), `TensorFactory` |
| `onnx-backend` | `OnnxBackend` → `OnnxRuntimeInterface`/`NativeOnnxRuntime`; `OnnxModel`, `OnnxTensor`, `OnnxRuntimeFactory` |
| `llama-backend` | `LlamaBackend` → `LlamaRuntimeInterface`/`NativeLlamaRuntime`; `LlamaModel` (`runStream()` Generator), `FFI\FerryLlama`, `ChatFormatter`, GBNF grammar + samplers |
| `cpu-backend` | `CpuNativeBackend`/`CpuNativeModel`/`CpuNativeTensor`; optional `RubixMLAdapter` |
| `tokenizer` | `TokenizerFactory`, `PureBpeTokenizer`/`PureWordPieceTokenizer`, native `HuggingFaceTokenizer` |
| `embedding` | `Embedder` + `Pooling\*` (mean/cls/eos/max, `AbstractPooling`), `EmbeddedModels` registry |
| `vector` | `Collection`/`SQLiteStore`, `PostgresCollection`/`PostgresStore`, `SqliteVecExtension`, `MetadataFilter`, `ExportImport`, `CollectionManager` |
| `model-hub` | `Hub`, `HuggingFaceClient`, `CacheManager`, `Downloader`/`HttpStream`, `Format\*` inspectors, `Signature\*` (SHA-256 + Ed25519) |
| `pipeline` | `Pipeline`/`FiberPipeline` + `Stages\*` (Chunk/Tokenize/Embed/Classify/Normalize/Filter/Store/Transform) |
| `dataframe` | `DataFrame`/`Column`, `IO\{CsvReader,CsvWriter,JsonReader,ParquetReader}` |
| `ai` | `AI` facade, `AIFactory`, `BackendRegistry`, `ModelPool`, `TaskRouter`, `Observability`, `NativeBinaryManager`, `SharedMemoryManager`, `FrameworkConfig`, `AsyncInference` |
| `laravel` / `symfony` | thin adapters that build config via `FrameworkConfig` and call `AI::config()` |

## Core Primitives (`packages/core/src/`)

- **Contracts** (`Contracts/`): `Backend`, `Model`, `Tensor`, `Tokenizer`, `Embedder`, `VectorStore`, `Pipeline`, `Stage`, `DataFrame`, `ModelHub`.
- **Enums** (`Enums/`, backed strings): `BackendType` (`onnx|llama|cpu_native`), `Device` (`cpu|cuda|rocm|metal|vulkan|directml|openvino|opencl|auto`), `DType`, `DistanceMetric`, `TokenizerType`, `IndexType`, `GraphOptimizationLevel`, `QuantizationType`.
- **Value objects** (`ValueObjects/`, all `readonly`): `Shape`, `SamplingParams`, `ModelMetadata`, `GenerationResult`, `EmbeddingResult`, `ClassificationResult`, `ChatMessage`.
- **Exceptions** (`Exception/`): all extend `FerryAIException`; 11 subclasses (`Inference`, `ModelLoad`, `ModelNotFound`, `BackendNotAvailable`, `DeviceNotAvailable`, `ShapeMismatch`, `Configuration`, `Validation`, `Tokenizer`, `Io`, `InvalidState`).

## Public API & CLI

- **Facade `FerryAI\AI`** (all-static; call `AI::config()` first): `chat`, `stream`, `streamResponse`,
  `embed`, `similarity`, `classify`, `moderate`, `predict`, `pipeline`, `vector`, `hub`, `tokenizer`,
  `warmup`, `backend`, `device`, `activeBackend`, `activeDevice`, `resetBackend`, `reset`.
- **`bin/ferry-ai`**: `check` (default — env/extension/backend diagnostics, `--json`), `models:list`,
  `models:download <id> [version]`, `models:prune`, `tokenize <text>`, `chat <message> [--stream] [--max=N]`, `version`.
- **`bin/generate-ffi`**: turns a C header into an `\FFI::cdef()` string via `FerryAI\Core\FFI\CdefGenerator` (used to regenerate FFI bindings).
- **CI** (`.github/workflows/`): `ci.yml` (lint + tests across OS/PHP matrix), `release.yml` (native-binary build + Packagist), `docs.yml`.

## Commands

| Task | Command |
|---|---|
| Style (auto-fix, PER-CS 2.0) | `composer cs-fix` |
| Style (check) | `composer cs-check` |
| PHPStan (level 8) | `composer stan` |
| Psalm (level 3) | `composer psalm` |
| Both static analysers | `composer analyse` |
| All linters (cs-check + analyse) | `composer lint` |
| Unit tests | `composer test` |
| Integration tests (needs native libs) | `composer test-integration` |
| Runtime verification (bug/audit) | `composer verify` |
| Mutation testing (Infection) | `composer mutation` |
| Coverage (HTML + text) | `composer coverage` |
| **Pre-commit gate: lint + test** | `composer check` |

## Testing

Three layers:
- **Contract** — `packages/*/tests/Unit/Contracts/` — abstract tests for every interface.
- **Unit** — `packages/*/tests/Unit/` — isolation, FFI mocked.
- **Integration** — `tests/Integration/`, `@group integration`, real models.

**Verification** (`tests/Verification/`, `@coversNothing`, `composer verify`): runtime tests
reproducing bugs and validating audit findings.

Rules:
- FFI boundary is mocked in unit tests by design (interface-and-mock pattern).
  Production FFI classes are exercised by the integration suite.
- **Native tests are skipped by default.** `phpunit.xml.dist` sets `FERRY_AI_SKIP_NATIVE=1`
  (`force="false"`, so it is overridable). To actually run the integration/native suites you
  MUST export `FERRY_AI_SKIP_NATIVE=0` and have the required native libs + models present:
  `FERRY_AI_SKIP_NATIVE=0 vendor/bin/phpunit tests/Integration/Postgres` (or `--testsuite integration`).
  Without it `composer test-integration` reports mostly *skipped*, not passed.
- Run a single integration group by path: `vendor/bin/phpunit tests/Integration/{Onnx,Llama,Postgres,Sqlite,Vector,Rubix,ModelHub,Tokenizer}`.
- The native llama runtime is **standalone-process only** — ggml's global constructors clash with the
  PHPUnit runner, so `NativeLlamaRuntime`/`FerryLlama` are covered by a subprocess harness, never in-process.
- `rubix/ml` integration also runs in a **subprocess with its own autoloader** (`tests/Integration/Rubix/*harness.php`)
  because rubix's `amphp/amp` pin collides with the dev toolchain — never load `rubix/ml` in the main test process.

## Local Development (native libs, models, environment)

Backends bridge to native shared libraries via FFI, so examples/benchmarks/integration need those
libraries and model files locally. None of them are committed.

**Machine-local config — `.ferry-ai.local.php`** (git-ignored):
- Copy `.ferry-ai.local.php.dist` → `.ferry-ai.local.php` and set absolute paths for your machine.
- It is auto-loaded for **every** entry point that includes the autoloader (examples, benchmarks,
  `bin/`, PHPUnit) via the Composer `autoload-dev` `files` entry `tests/local_env.php`.
- It `putenv()`s the `FERRY_AI_*` paths below; when absent, code falls back to the repo-relative
  `models/` directory and **skips gracefully** if assets are missing.
- Note: because it runs during autoload, its values **override** any pre-set shell env. To force a
  different path (e.g. Linux paths when the file holds Windows paths), `putenv()` again *after*
  `require vendor/autoload.php` in a standalone script.

**Prebuilt native binaries — `native-binaries/<platform>/`** (git-ignored):
- Distributed via GitHub Releases (`MADEVAL/ferry-ai-native-binaries`), not committed; layout is
  `native-binaries/{windows-x86_64,linux-x86_64,macos-*}/` with a `.sha256` next to each artifact.
- `NativeBinaryManager` resolves a lib from `PATH`, then the cache `~/.ferry-ai/bin`, else downloads
  from the release (override the URL pattern with `FERRY_AI_NATIVE_BINARIES_URL`) and verifies SHA-256.
- `.gitattributes` marks `native-binaries` `export-ignore`.

**Per-backend native requirements:**

| Backend / feature | Needs |
|---|---|
| ONNX (`onnx-backend`) | `libonnxruntime` — bundled per-platform under `vendor/ankane/onnxruntime/lib/`; CUDA provider is optional and falls back to CPU if its deps (cuBLAS/cuDNN/cuRAND) are absent |
| llama (`llama-backend`) | `ferry_llama.{dll,so,dylib}` wrapper **+** `libllama` + `libggml*` next to it **+** a `.gguf` model. Wrapper found via `FERRY_AI_LLAMA_WRAPPER` (full path) or `FERRY_AI_LLAMA_LIB` (wrapper derived from its dir) |
| tokenizer (HF native) | `libtokenizers_cpp` via `FERRY_AI_TOKENIZERS_LIB` (pure-PHP BPE/WordPiece work without it) |
| vector / sqlite-vec | `vec0` loadable extension via `FERRY_AI_VEC_EXTENSION_LIB` + `Pdo\Sqlite` (PHP 8.4+); brute-force SQLite store needs no extension |
| vector / postgres | running PostgreSQL + `pgvector` + `ext-pdo_pgsql`; creds via `FERRY_AI_PG_DSN`/`FERRY_AI_PG_USER`/`FERRY_AI_PG_PASSWORD` |
| cpu (`cpu-backend`) | optional `rubix/ml` (Composer `suggest`; not in the dev toolchain) |

**Key environment variables:**

| Variable | Purpose |
|---|---|
| `FERRY_AI_SKIP_NATIVE` | `1` (test default) skips native/integration; set `0` to run them |
| `FERRY_AI_MODEL_DIR` | ONNX embedding model dir (`model.onnx` + `tokenizer.json`) |
| `FERRY_AI_LLAMA_DIR` | dir with the wrapper + llama/ggml libs + default `qwen-0.5b.Q4_K_M.gguf` |
| `FERRY_AI_LLAMA_MODEL` | explicit `.gguf` path (overrides the dir default) |
| `FERRY_AI_LLAMA_WRAPPER` / `FERRY_AI_LLAMA_LIB` | explicit wrapper path / llama lib path |
| `FERRY_AI_TOKENIZERS_LIB`, `FERRY_AI_VEC_EXTENSION_LIB` | native tokenizer / sqlite-vec extension |
| `FERRY_AI_PG_DSN`, `FERRY_AI_PG_USER`, `FERRY_AI_PG_PASSWORD` | Postgres integration |
| `FERRY_AI_RUBIXML_AUTOLOAD` | path to an **isolated** `rubix/ml` `vendor/autoload.php` (rubix runs in a subprocess) |
| `FERRY_AI_NATIVE_BINARIES_URL` | override prebuilt-binary download URL (`printf` pattern: version, lib, platform, ext) |
| `FERRY_AI_TESTING` | set to `1` by the PHPUnit config |

Data files (`.gguf`, `.onnx`, tokenizer JSON) are platform-independent and run on any OS with the
matching native runtime; the `.env` files under `tests/` document expected values but are **not**
auto-loaded (the PHPUnit config and `.ferry-ai.local.php` are the effective sources).

## Architecture Rules

1. **Inference-only** — load models, run forward pass. No training, no autograd, no optimizers.
2. **FFI is the only bridge** to native code. No `shell_exec` to Python.
3. **Backend isolation** — backends never know about each other; only the `ai` package composes them.
4. **Contracts define truth** — signatures in `packages/core/src/Contracts/`; implementations never deviate.
5. **Exceptions** — all extend `FerryAIException`, each has `errorCode()` returning `FERRY_AI_*`.
6. **Zero-copy** — avoid copying data PHP↔native; `toArray()` is marked as expensive.
7. **No hard framework coupling** — no mandatory Laravel/Symfony base classes; model weights not committed.

## Code Conventions

- Comments and PHPDoc in `.php` files and config files (`.neon`, `.xml`, `.dist`, `.gitattributes`) are written in English only.
- All project documentation in `docs/` is English-only.
- `declare(strict_types=1)` in **every** `.php` file. Concrete classes are `final` by default — known
  non-final: `Pipeline`/`FiberPipeline`, FFI session handles (`*Session`), `AbstractPooling`, `FerryLlama`.
- Every interface-method implementation carries `#[\Override]` (the analysers rely on it).
- Value objects are `readonly`; prefer the `core` enums over raw string/int constants.
- **FFI seam pattern** — each native backend hides FFI behind an interface (`OnnxRuntimeInterface`,
  `LlamaRuntimeInterface`) injected into the `*Backend`, with a `Native*` production impl and a mock
  double in unit tests. Only plain PHP values cross the seam; **never** pass `\FFI\CData` across it.
- **Static analysis skips the FFI-boundary files** in both `phpstan.neon` and `psalm.xml`:
  `FerryLlama`, `NativeLlama{Runtime,Session}`, `NativeOnnx{Runtime,Session}`, `OnnxRuntimeFactory`,
  `HuggingFaceTokenizer`, `RubixMLAdapter` (plus `SharedMemoryManager` in Psalm). Code there is **not**
  type-checked, so keep it thin; a small baseline of `ignoreErrors` also exists (don't "fix" it blindly).
- Streaming is `\Generator`-based (`LlamaModel::runStream()`, `Pipeline::run()`); `FiberPipeline` and
  `ai/AsyncInference` add cooperative **Fiber** scheduling.
- GPU→CPU fallback is automatic and **silent**: `OnnxBackend::load()` retries on CPU when a non-CPU
  device fails, so an incomplete CUDA/cuDNN install runs on CPU with no error.

## Commits

`type(scope): description` — types: feat/fix/docs/style/refactor/perf/test/chore/ci/build/revert;
scope — package name (`core`, `onnx-backend`, `ai`, …).
Example: `feat(core): add Shape value object with broadcasting validation`.

## Document Map

| Need | File |
|---|---|
| Architecture | `docs/TECHNICAL_SPECIFICATION.md` |
| Interface signatures | `docs/INTERFACE_CONTRACTS.md` |
| File map + package structure | `docs/FILE_TREE.md` |
| CI/CD, composer, publishing | `docs/REPOSITORY_INFRASTRUCTURE.md` |
| External dependencies and versions | `docs/SOURCES.md` |
| Full documentation navigator | `docs/README.md` |
| API reference | `docs/api-reference.md` |
| Configuration guide | `docs/configuration.md` |
| Getting started | `docs/getting-started.md` |
