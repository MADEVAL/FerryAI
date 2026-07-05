# FerryAI — Audit Prompt for AI Code Review

> Use this prompt in a fresh AI session to run a deep, methodical audit of the entire repository.
> It covers architecture, API contracts, test coverage, static analysis, documentation,
> cross-platform consistency, performance invariants, and debt honesty. Execute thoroughly.

---

## Context

You are auditing **FerryAI** (`D:\_DEV\FerryAI`), a monorepo PHP 8.5+ inference library. It has four
completed phases (MVP → Production). Core principles: **inference-only** (no training), **FFI is
the only bridge** to native code, **backend isolation**, **contract-first TDD**, and **zero-copy
mindset**.

The monorepo holds ~15 packages under `packages/`. Root namespace `FerryAI\`. All contracts live
in `packages/core/src/Contracts/`. Backends (`onnx-backend`, `llama-backend`, `cpu-backend`) must
not depend on `ai`. The `ai` package is the facade layer. Full architecture in
`docs/TECHNICAL_SPECIFICATION.md`. Canonical file map: `docs/FILE_TREE.md`.

---

## 1. Gate & Tooling

Run these commands in order and verify **every one exits 0 with zero errors**:

```powershell
composer cs-fix        # PER-CS 2.0 auto-fix
composer cs-check      # must report 0 fixable
composer stan          # PHPStan level 8 → must print "[OK] No errors"
composer psalm         # Psalm level 3 → must print "No errors found!"
composer test          # unit tests → must report "OK (630 tests, ...)"
composer check         # = cs-check + stan + psalm + test; must be fully green
```

Relevant config files for each tool:

- `phpstan.neon` — level 8, paths cover all packages under `packages/*/src`; excluded FFI
  boundaries: `OnnxRuntimeFactory`, `NativeOnnx*`, `FerryLlama`, `NativeLlama*`, `HuggingFaceTokenizer`,
  `RubixMLAdapter`.
- `psalm.xml` — level 3, same exclusions.
- `.php-cs-fixer.dist.php` — excludes `FFI/` and `Runtime/` directories from style checking.
- `phpunit.xml.dist` — three test suites: `unit` (`packages/*/tests/Unit`), `integration`
  (`tests/Integration`), `verification` (`tests/Verification`). Native tests are gated by
  `FERRY_AI_SKIP_NATIVE=1` in the PHP config section.

**Expected:** all gates green on both Windows and Linux/WSL. Unit count ≈ 620–630 (no drift
downwards — that signals deleted tests without replacement). If any gate fails, **do not proceed**
until the failure is understood and the root cause is documented.

---

## 2. Architecture Integrity

### 2.1 Backend Isolation

- No backend package (`onnx-backend`, `llama-backend`, `cpu-backend`) may `use` or `import` any
  class from another backend or from the `ai` package. Check with:
  ```powershell
  Select-String -Path "packages/llama-backend/src/**/*.php" -Pattern "use FerryAI\\OnnxBackend|use FerryAI\\CpuBackend|use FerryAI\\Ai\\"
  Select-String -Path "packages/onnx-backend/src/**/*.php" -Pattern "use FerryAI\\LlamaBackend|use FerryAI\\CpuBackend|use FerryAI\\Ai\\"
  ```
- The `ai` package is the **only** package allowed to depend on multiple backends. Verify that
  `packages/ai/composer.json` `require`s all backend packages.
- `packages/core/composer.json` must `require` **nothing** (zero external deps — it's the root of
  the dependency graph).

### 2.2 Interface Contracts

- Every interface in `packages/core/src/Contracts/` must match its canonical signature in
  `docs/INTERFACE_CONTRACTS.md`. Spot-check at least these contracts:
  - `Backend` — `load(string $source, ?Device $device): Model`
  - `Model` — `run(array $inputs): array`, `metadata(): ModelMetadata`, `unload(): void`
  - `VectorStore` — `add`, `addBatch`, `search`, `delete`, `deleteByFilter`, `update`, `count`,
    `dimension`, `collectionName`, `iterator`, `export`, `clear`
  - `Embedder` — `embed`, `embedBatch`, `dimension`, `cosineSimilarity`, `normalize`, `modelName`
  - `Sampler` — `sample(array $logits, SamplingParams $params): int`
- No concrete class may deviate from a contract signature in return type, parameter type, or
  thrown exception hierarchy.

### 2.3 Exception Hierarchy

- **Every** exception thrown by the platform must extend `FerryAI\Core\Exception\FerryAIException`.
  Verify with:
  ```powershell
  Select-String -Path "packages/**/src/**/*.php" -Pattern "throw new \\RuntimeException|throw new \\InvalidArgumentException" -Exclude "*tests*"
  ```
  There should be **zero** results — all throws that are still `\RuntimeException` or
  `\InvalidArgumentException` without a FerryAI-typed subclass are violations.
  (Exceptions: test doubles, vendor code, `native/llama-wrapper` C code.)

- Each exception must expose `errorCode(): string` returning a `FERRY_AI_*` machine-readable code.

### 2.4 Dependency Graph

Verify `packages/*/composer.json` require chains: Core ← Tensor, Tokenizer, Embedding, Vector,
ModelHub, Pipeline, CpuBackend, OnnxBackend, LlamaBackend ← AI ← Laravel, Symfony. No circular
dependencies. Spot-check with a manual trace: pick a package → follow its require → verify it
doesn't loop back.

### 2.5 Namespace & File Layout

Check that every class file path under `packages/*/src/` matches its namespace per PSR-4, and
that the file tree matches `docs/FILE_TREE.md`. Any file present but not in FILE_TREE is
undocumented debt; any file in FILE_TREE but missing from disk is a packaging gap.

---

## 3. Test & Coverage Audit

### 3.1 Unit Tests

- 100% coverage target for contracts, enums, value objects, and exceptions. Check
  `packages/core/tests/` for every `Contracts/` file, every `Enums/` file, every
  `ValueObjects/` file.
- Backend implementations: ≥ 90% line coverage. Run:
  ```powershell
  php vendor/bin/phpunit --testsuite unit --coverage-text | Select-String -Pattern "Classes:|Methods:|Lines:"
  ```
- FFI boundaries (`FerryLlama`, `NativeLlamaRuntime`, `NativeOnnxRuntime`, `OnnxRuntimeFactory`,
  `HuggingFaceTokenizer`, `RubixMLAdapter`) are **excluded by design** from unit testing — they are
  covered by the integration suite. Verify they appear in the phpstan/psalm exclude lists.

### 3.2 Integration Tests

Every integration test must be in `tests/Integration/`, marked `#[Group('integration')]` and
`#[CoversNothing]`. They must gracefully skip when the native library is absent (check for
`markTestSkipped` or a `skip` guard). Spot-check:

| Test file | What it covers | Skip condition |
|-----------|---------------|----------------|
| `Integration/Onnx/OnnxRuntimeIntegrationTest.php` | ORT version, devices, providers | `FERRY_AI_SKIP_NATIVE` or ORT DLL absent |
| `Integration/Onnx/AiEmbedIntegrationTest.php` | Facade embed/similarity/batch | Same + `model.onnx` missing |
| `Integration/Llama/LlamaBackendIntegrationTest.php` | Chat on CPU, GPU, nucleus, pooling, grammar | `ferry_llama` wrapper absent; runs harness in subprocess |
| `Integration/Postgres/PostgresVectorIntegrationTest.php` | CRUD, native cosine, filter, HNSW | `ext-pdo_pgsql` absent or PG unreachable |
| `Integration/Sqlite/SqliteVecIntegrationTest.php` | Real vec0 KNN + Collection ANN | `Pdo\Sqlite` absent or vec0 lib absent |
| `Integration/Rubix/RubixCpuIntegrationTest.php` | .rbm load + predict + AI::predict via facade | RubixML autoloader absent |

Verify the counts: `composer test-integration` should show **32 tests** total, with only
PostgreSQL tests skipping when the WSL auth is not configured (14 skipped on Linux).

### 3.3 TDD Red-Green Evidence

Check `docs/BUILD_LOG.md` — every feature entry should mention red-green TDD. Spot-check a
recent test file to verify it asserts real behavior (not `assertTrue(true)` or meaningless
coverage padding).

### 3.4 Contract Tests

The contract test pattern: `packages/core/tests/Unit/Contracts/` should contain abstract test
classes that test **any** implementation of an interface. Examples: `BackendContractTest`,
`VectorStoreContractTest`. Verify they are actually extended by backend test suites.

---

## 4. Documentation Completeness

### 4.1 Per-Capability Guides

Every capability must have a doc in `docs/`. Verify these files exist and have non-trivial
content (not stubs):

- `getting-started.md` — install → config → first embed → first chat → next steps
- `configuration.md` — every config key with default, meaning, and env fallback
- `api-reference.md` — complete facade API, core contracts, value objects, exceptions
- `backends/onnx.md` — what to download, how to check availability, providers, GPU note
- `backends/llama.md` — wrapper concept, setup, build, chat/stream, sampling, grammar, ABI appendix
- `embedding.md` — setup, pooling strategies, performance, built-in model dims
- `vector-store.md` — SQLite vs PostgreSQL, metadata filters, sqlite-vec, pgvector setup, metric map
- `pipeline.md` — basics, built-in stages, RAG example, async
- `model-hub.md` — HF client, download/cache, verification, format detection, stream loading
- `tokenizer.md` — BPE/WordPiece, round-tripping, native binding, use in embeddings
- `streaming.md` — token stream, SSE, NDJSON, PSR-7, real-time flushing
- `security.md` — model provenance, deserialization risks, FFI boundary, input handling,
  grammar-constrained output, secrets
- `deployment.md` — runtime prereqs, long-running workers, GPU, proxy for SSE, Docker, CI, Linux/WSL
- `troubleshooting.md` — actionable errors for every common failure mode
- `laravel.md` — registration, env config, use in controllers, decoupling note
- `symfony.md` — bundle boot, YAML config, use in services, decoupling note
- `CHANGELOG.md` — all notable changes with links to BUILD_LOG entries

### 4.2 README Cross-Reference

- The **Verified** table must list every backend + platform combination that has been tested.
  Current rows: ONNX CPU, llama FFI CPU+GPU, HuggingFace API, vector store (SQLite + Postgres),
  CPU backend, shared memory, async fibers, Linux/WSL.
- The **Dependencies & downloads** matrix must list exactly the native artifacts required per
  capability, with an accurate source URL and the env var that enables it.
- The **LLM on CPU & GPU** section must show build steps for Windows and Linux and performance
  numbers for both platforms.
- Test count and example count must be accurate (currently: 630 tests, 26 examples).

### 4.3 `docs/SOURCES.md`

Verify every external dependency (ONNX Runtime, llama.cpp, sqlite-vec, pgvector, PostgreSQL,
CUDA, cuDNN, TensorRT, HuggingFace, RubixML, tokenizers-cpp, Laravel, Symfony, PHPStan, Psalm,
PHP-CS-Fixer, PHPUnit) has an entry with a live URL. Flag any broken links (run a quick HEAD
check on each).

### 4.4 BUILD_LOG Completeness

Every major feature must have a dated entry in `docs/BUILD_LOG.md` describing what was done,
why, which files were created/modified, test results, and the gate status. The log must cover
all four phases.

---

## 5. Performance Invariants

### 5.1 Model Pooling

- `AI::chat()` must reuse a pooled `LlamaModel` (2nd call in a process must be **markedly faster**
  than the first). Verified by `LlamaBackendIntegrationTest::testChatModelIsPooledAcrossCalls`.
- `AI::embed()` must cache the `Embedder` in `AIFactory` (2nd call to `createEmbedder` returns
  the same instance). Verified by `AiEmbedIntegrationTest::testEmbedderIsCachedPerModel`.
- `ModelPool` must evict oldest models when `maxMemoryBytes` is exceeded. Verified by
  `ModelPoolTest::testEvictsOldestWhenOverMemoryLimit`.

### 5.2 Native Top-K Pre-Filter

- Greedy/top-k/top-p sampling must route through `evaluateTopK` (returns sparse top-k logits from
  the native wrapper, not the full ~152k vocab). Grammar must use `evaluate` (full vocab).
- Spot-check `LlamaModel::nextLogits()` — it must branch on `$sampler instanceof GrammarSampler`.
- Verify that TopPSampler/TopKSampler work correctly with sparse token-id-keyed logit arrays.
  Unit tests: `TopPSamplerTest`, `TopKSamplerTest` — all pass.

### 5.3 Observability Off by Default

`Observability` must be **off by default** (no metrics/profiling/logging unless explictly enabled
in config). Verify the `ObservabilityTest::testDisabledByDefaultRunsWithoutRecording` test passes.

---

## 6. Cross-Platform Consistency

### 6.1 Platform Detection

`FerryAI\Core\PlatformDetector` must return correct `os()`, `arch()`, `libExtension()`, and
`platformKey()` on Windows, Linux, and macOS. Verify the mapping: Windows→`dll`, Linux→`so`,
Darwin→`dylib`.

### 6.2 Env-Variable & Path Handling

- Check that **no file hardcodes** Windows-only path separators (`\\`) in application code.
  Test files and harnesses may use them, but source files must use `DIRECTORY_SEPARATOR`.
- `putenv('PATH=...')` and `putenv('LD_LIBRARY_PATH=...')` must be platform-aware. Check
  `FerryLlama::__construct` — it sets the right env var per OS.

### 6.3 Native Library Loading

- `FerryLlama::resolveWrapperPath()` must use `PlatformDetector::libExtension()` to build the
  wrapper file name (not hard-coded `.dll`).
- `OnnxRuntime\Vendor::check()` must be callable on any platform and download the correct archive.
- `NativeLlamaRuntime::isAvailable()` must check for `ext-ffi` + the wrapper file existence;
  it must not load the DLL during the probe (lazy, in `createSession`).

### 6.4 Gateway Commands

Verify `composer.json` scripts (`test`, `stan`, `psalm`, `cs-fix`, `cs-check`, `lint`, `check`)
work on both Windows (PowerShell) and Linux (bash). The `@putenv` syntax used in scripts is
cross-platform (Composer handles it).

---

## 7. Dependency & Security Audit

### 7.1 Composer Dependencies

- Run `composer audit` — must report 0 known vulnerabilities.
- Verify that `rubix/ml` is in `suggest` (NOT `require`) because of the `amphp/parallel` conflict
  with psalm. It must remain an isolated, opt-in dependency.
- Verify `psr/http-factory` is in `require` of `ai/composer.json`, and PSR-17 factories
  (`nyholm/psr7`, `guzzlehttp/psr7`) are in `suggest`.

### 7.2 Native Binary Verification

- `StreamResponse::create()` must auto-detect a PSR-17 factory and throw a clear error when none
  is installed.
- `NativeBinaryManager` must implement `LibraryResolver` and resolve `llama` → `FERRY_AI_LLAMA_LIB`
  best-effort, guarded (no download, no crash if absent).

### 7.3 Hardcoded Secrets

Search for anything resembling a password, API token, or private key in the source tree:
```powershell
Select-String -Path "packages/**/src/**/*.php" -Pattern "password\s*=|token\s*=\s*'[^']{8,}|secret" -CaseSensitive:$false
```
There should be zero hits (all credentials must come from env vars).

---

## 8. Documentation & Code Consistency

### 8.1 Environment Variables

Every `getenv('FERRY_AI_*')` call must be documented somewhere — either in the README matrix,
`docs/configuration.md`, or the per-capability guide. Check:
```powershell
Select-String -Path "packages/**/src/**/*.php" -Pattern "getenv\('FERRY_AI_[A-Z_]+'\)" | ForEach-Object { $_.Matches.Value } | Sort-Object -Unique
```
Cross-reference each against the docs.

### 8.2 Config Key Documentation

Every key used via `$config->get('...')` or `$this->config->get('...')` must appear in
`docs/configuration.md`. Spot-check by searching for `->get(` in `AIFactory`, `AI`, and
`AIConfig`.

### 8.3 Enum Consistency

Every enum in `packages/core/src/Enums/` must be string-backed (or int-backed) and must be used
consistently. Verify that no magic string or integer replaces an enum value where the enum type
is expected (e.g., `BackendType`, `Device`, `DType`, `GraphOptimizationLevel`, `TokenizerType`,
`DistanceMetric`, `IndexType`).

### 8.4 Readonly / Immutability

Value objects must be `readonly class` or have `readonly` properties. `AIConfig` must be
immutable (`set()` returns a new instance). Spot-check `ModelMetadata`, `Shape`, `EmbeddingResult`,
`GenerationResult`, `SamplingParams`, `ChatMessage`.

---

## 9. Specific Danger Zones

### 9.1 llama.cpp + PHPUnit

Loading `ferry_llama.dll`/`.so` under PHPUnit crashes (ggml global ctor conflict). Verify that
`LlamaBackendIntegrationTest` runs chat in a **subprocess** (calls `shell_exec` to spawn
`llama_chat_harness.php`), not directly. The harness must be a standalone script, not a PHPUnit
test.

### 9.2 RubixML + amphp Collision

Loading the isolated RubixML autoloader **in the same process** as the main vendor crashes
(`Amp\delay()` redeclaration). Verify that `RubixCpuIntegrationTest` runs through a subprocess
harness (`rubix_harness.php` or `rubix_predict_harness.php`), not directly.

### 9.3 StreamResponse PSR-7 Detection

`StreamResponse::psr17Factory()` uses string class names (`'Nyholm\Psr7\Factory\Psr17Factory'`,
`'GuzzleHttp\Psr7\HttpFactory'`) and `instanceof` checks. The static-analysis `@phpstan-ignore` on
the `instanceof` line is intentional (PHPStan knows Nyholm implements the interfaces, but the guard
is needed for the Guzzle branch). Verify the annotation is present and correctly formatted.

### 9.4 Grammar Sampling Performance

`GrammarSampler` loops over all candidate tokens by descending logit and checks `isViable()` for
each — this is **O(vocab)** per token and intentionally slow. It must NOT use the native top-k path
(`evaluateTopK`). Verify `LlamaModel::nextLogits()` routes grammar to `evaluate()` (full vocab).

---

## 10. Final Sanity Checks

- Run the full integration suite on both platforms (set `FERRY_AI_SKIP_NATIVE=0`).
  Expected: 32 tests, 14–15 skipped only due to PG WSL auth (environment).
- Compare `docs/DEBT_REPORT.md` against the codebase: every "Resolved" item removed, every
  "by-design" item correctly explained, every remaining item truly unresolved.
- Check that `CHANGELOG.md` lists all major features shipped.
- Verify `examples/README.md` list count matches the actual files in `examples/` (26 files).
- Verify `docs/EXAMPLES_PLAN.md` says "All 26 examples are implemented".

---

## Deliverable

After the audit, produce a concise report with these sections:

1. **Gate status**: each tool + exit code + error count. If any fail, the root cause and whether
   it's pre-existing or newly introduced.
2. **Architecture violations**: any cross-backend import, circular dependency, contract deviation,
   or generic exception throw.
3. **Test gap summary**: untested classes, missing contract tests, integration tests that never
   run because the skip guard is too aggressive.
4. **Documentation gaps**: missing capability guides, stale counts, broken links, undocumented env
   vars or config keys.
5. **Performance concerns**: anything that violates pooling/caching invariants or uses full-vocab
   eval where top-k should be used.
6. **Cross-platform issues**: Windows-only paths, hardcoded `.dll`, missing Linux build artifacts.
7. **Security findings**: hardcoded credentials, unsafe deserialization, unverified model loading.
8. **Honest debt check**: every unresolved item in `DEBT_REPORT.md` is actually unresolved and
   accurately described.
9. **Actionable recommendations**: a prioritised list of what to fix, in order.

Be exhaustive. Flag everything, even minor inconsistencies. The project has zero known runtime
bugs — any finding is valuable.
