# ferry-ai/php-inference

Expert skill for architecting, developing, and testing the FerryAI — a unified inference runtime for PHP applications with pluggable native backends (ONNX Runtime, llama.cpp, OpenBLAS).

## Core Identity

You are building **FerryAI**: a multi-backend AI inference library for PHP 8.5+. It is NOT a training framework, NOT "TensorFlow for PHP", NOT an autograd engine. It is a **bridge** — a unified PHP API that lets any PHP app run modern AI models (LLM, embeddings, classification) without leaving the language.

Three native runtimes under the hood, accessed exclusively through FFI:
- **ONNX Runtime** (primary) — all-purpose inference, GPU via CUDA/TensorRT/CoreML
- **llama.cpp** — LLM chat + streaming, GPU via CUDA/Metal/Vulkan
- **RubixML/Tensor** (OpenBLAS) — CPU fallback for classic ML

Users interact with a single facade: `AI::chat()`, `AI::embed()`, `AI::classify()`, `AI::stream()`.

## Project Conventions

### PHP Version
- **Target:** PHP 8.5 (minimum). All 8.5 features are available: Pipe Operator `|>`, Clone as a function (`clone($obj, [...])` with reassignable readonly props), `#[\NoDiscard]`, Closures in Constant Expressions, Static Asymmetric Visibility, Final Property Promotion, `#[\Override]` on properties. (Property Hooks and instance Asymmetric Visibility are 8.4.)
- Forward-compatible with 8.6 but never depend on unreleased features.

### Type System
- **All code is strict-typed.** Every file starts with `declare(strict_types=1);`
- All method signatures have parameter and return types. No mixed unless genuinely mixed (FFI boundaries).
- Use intersection types for clean contracts: `Tensor&Serializable`, `Tensor&Iterator`.
- Nullable via `?Type`, never via default-null without type.

### Immutability
- All value objects are `readonly class` (PHP 8.2+).
- `readonly` properties on non-readonly classes where full immutability is impractical.
- Immutable config via `set()` returning new instance.
- `clone with` (8.5) for tensor device transfer — never mutate in place.

### Enums
- String-backed enums for all fixed sets: `Device`, `DType`, `BackendType`, `TokenizerType`, `GraphOptimizationLevel`, `DistanceMetric`, `IndexType`, `QuantizationType`.
- Each enum is in its own file under `packages/core/src/Enums/`.

### Directory & Namespace
- Monorepo root: `php-inference/`
- Packages: `packages/{name}/`
- Root namespace: `FerryAI\{Package}\`
- Exact file tree map: always reference `FILE_TREE.md` before creating or moving any file.

### Composer Package Names
- `ferry-ai/inference-core`, `ferry-ai/inference-tensor`, `ferry-ai/inference-onnx-backend`, `ferry-ai/inference-llama-backend`, `ferry-ai/inference-cpu-backend`, `ferry-ai/inference-tokenizer`, `ferry-ai/inference-embedding`, `ferry-ai/inference-pipeline`, `ferry-ai/inference-model-hub`, `ferry-ai/inference-vector`, `ferry-ai/inference-dataframe`, `ferry-ai/inference-ai`, `ferry-ai/inference-laravel`, `ferry-ai/inference-symfony`
- Root meta-package: `ferry-ai/php-inference`

## Architecture Rules (Non-Negotiable)

### 1. Backend Isolation
Every backend lives in its own package. Backends never know about each other. The `ai` package is the only package that imports multiple backends. Backends implement `FerryAI\Core\Contracts\Backend`. If you find yourself importing `OnnxBackend` from `LlamaBackend` — stop, you're breaking isolation.

### 2. FFI is the Only Bridge to Native Code
No native PHP extensions (except RubixML/Tensor for cpu-backend fallback). No shell_exec to Python. All native interaction goes through `\FFI`. Every FFI-bound package has an interface layer so tests can mock it. Example: `LlamaCppInterface` wraps C API calls — `NativeLlamaCpp` is the real implementation, `MockLlamaCpp` is for tests.

### 3. Zero-Copy Mindset
Tensors wrap FFI buffers (OrtValue, llama tokens). When passing between same-backend operations, pass the pointer, not the data. Copy only when crossing device boundaries (CPU↔GPU) or exporting to PHP arrays. Every `toArray()` call should come with a docblock warning: "EXPENSIVE — copies all data from native memory."

### 4. Contracts Define Truth
All interfaces live in `packages/core/src/Contracts/`. No concrete implementation may deviate from a contract signature. The canonical signatures are in `INTERFACE_CONTRACTS.md`. Before writing any class that implements an interface, open that file and verify the exact signature.

### 5. Exceptions Hierarchy
- Base: `FerryAI\Core\Exception\FerryAIException extends \RuntimeException`
- All platform exceptions extend `FerryAIException`
- Each exception has an `errorCode(): string` method (machine-readable, e.g. `FERRY_AI_SHAPE_MISMATCH`)
- Never throw generic `\Exception` or `\RuntimeException` from platform code — use the typed exceptions.

### 6. Package Dependency Graph
Core depends on nothing. Everything depends on Core. `ai` depends on everything. No circular dependencies between packages. The full graph is in `FILE_TREE.md` and `TECHNICAL_SPECIFICATION.md` Section 9.2. Before adding a `require` to any composer.json, verify it doesn't create a cycle.

### 7. Inference-Only
We load models and run forward passes. We never train, never compute gradients, never optimize. If someone asks for backpropagation, autograd, or an optimizer — the answer is "out of scope". ONNX Runtime handles the forward pass; we wrap it.

## Testing Doctrine (TDD)

### Always Write Tests First
1. Read the relevant contract in `INTERFACE_CONTRACTS.md`
2. Read the implementation step in the phase plan (`IMPLEMENTATION_PHASE_X.md`)
3. Write the test that will validate the step's acceptance criteria
4. Run the test — it MUST fail (red)
5. Write the minimal implementation (green)
6. Refactor (clean)
7. Run full suite: `composer test && composer lint`

### Three Test Layers

**Layer 1: Contract Tests** (`packages/*/tests/Unit/Contracts/`)
Abstract test classes that validate any implementation of an interface. Example: `BackendContractTest` defines `test_available_devices_returns_array_of_device()`, `test_load_throws_model_not_found_for_missing_file()`. Every backend's test extends this and provides `createBackend(): Backend`.

**Layer 2: Unit Tests** (`packages/*/tests/Unit/`)
Test concrete classes in isolation. Mock all FFI dependencies. Never call real native libraries from unit tests. Use the interface-and-mock pattern: inject `LlamaCppInterface` rather than calling `\FFI` directly.

**Layer 3: Integration Tests** (`tests/Integration/`)
Test real native libraries with real models. These are in the root `tests/` directory (not inside packages). Marked with `@group integration`. CI runs these only on Linux with libraries installed. Local dev can skip them via `FERRY_AI_SKIP_NATIVE=1`.

### FFI Mocking Strategy
Every native C API gets a thin PHP interface:
```php
interface LlamaCppInterface {
    public function modelLoadFromFile(string $path): object;
    public function contextInitFromModel(object $model, object $params): object;
}
```
Production: `NativeLlamaCpp implements LlamaCppInterface` — real FFI calls.
Test: `MockLlamaCpp implements LlamaCppInterface` — returns hardcoded CData stubs.
Inject via constructor with default to native:
```php
class LlamaBackend {
    public function __construct(
        private LlamaCppInterface $llama = new NativeLlamaCpp(),
    ) {}
}
```

### Coverage Standards
- Contracts/Enums/ValueObjects/Exceptions: **100%**
- Backend implementations: **≥ 90%** (FFI glue is hard to fully cover without hardware)
- Mutation testing (Infection): MSI ≥ 70%

## Quality Gates (Pre-Commit)

Before committing any code, run in order:
1. `composer cs-fix` — auto-fix code style (PER-CS 2.0)
2. `composer stan` — PHPStan level 8
3. `composer psalm` — Psalm level 3
4. `composer test` — all unit tests
5. `composer test-integration` — if native libs available

The pre-commit hook (CaptainHook) enforces this. Do not skip hooks. If a false positive from static analysis, add an `@phpstan-ignore` or `@psalm-suppress` comment with explanation — never lower the global level.

## Implementation Workflow

### When Creating a New File
1. Find the exact path in `FILE_TREE.md`
2. Verify all dependencies already exist (check the dependency column)
3. Read the implementation step in the relevant `IMPLEMENTATION_PHASE_X.md`
4. Read the contract in `INTERFACE_CONTRACTS.md` if implementing an interface
5. Write the test
6. Write the code
7. Add to git with commit message: `feat(package): add ClassName`

### When Modifying an Existing File
1. Read the file and its dependencies
2. Check if the contract in INTERFACE_CONTRACTS.md allows the change
3. Update the test first
4. Make the change
5. Run the full suite

### When Brainstorming / Designing
1. Check `TECHNICAL_SPECIFICATION.md` for existing decisions
2. Check `RESEARCH_ARCHITECTURE.md` for the rationale behind decisions
3. Propose changes by discussing tradeoffs, not just solutions
4. Every design decision must answer: "What existing library/tool solves this? Why not use it?"

## Key Technical Constraints

### What We Use (Existing)
- `phpmlkit/onnxruntime` — ONNX Runtime FFI wrapper (zero-copy, NDArray interop)
- `codewithkyrian/huggingface-php` — HuggingFace Hub HTTP client
- `rubix/ml` + `RubixML/Tensor` — CPU native ML (C extension, OpenBLAS+LAPACKE)
- `ankane/onnxruntime-php` — fallback ONNX option (more stars, less zero-copy)

### What We NEVER Do
- No training, autograd, optimizers — out of scope
- No custom CUDA kernels — use ONNX Runtime / llama.cpp GPU backends
- No FANN — dead library, confirmed useless
- No shell_exec to Python scripts — must be pure PHP + FFI
- No hard framework coupling — the `ai` facade works without Laravel or Symfony
- No model files in the repo — excluded via .gitignore

### Threading & Concurrency
- PHP is single-threaded. Use Fibers (PHP 8.1+) for cooperative multitasking — never pcntl_fork.
- Streaming: Generator + Fiber. Each `yield` suspends, allows HTTP response to flush.
- Shared memory: System V shmop for read-only model weights between FPM workers (Phase 4).

### Memory Management
- Models 18+ GB loaded via mmap (llama.cpp) or streamed chunks (ONNX).
- Never load entire model into PHP memory.
- Destructors MUST free native resources (OrtReleaseValue, llama_free, cudaFree).
- Use WeakMap for gradient/graph tracking to prevent circular references.

## Documents Reference

Always open these when working on the project:

| Document | When to Use |
|---|---|
| `RESEARCH_ARCHITECTURE.md` | Understanding *why* decisions were made, ecosystem analysis |
| `TECHNICAL_SPECIFICATION.md` | Architecture reference, component responsibilities, phase scope |
| `INTERFACE_CONTRACTS.md` | Exact method signatures, type hints, thrown exceptions |
| `FILE_TREE.md` | File paths, namespaces, dependency order, build sequence |
| `IMPLEMENTATION_PHASE_1.md` | Step-by-step implementation for MVP (ONNX) |
| `IMPLEMENTATION_PHASE_2.md` | Step-by-step implementation for LLM (llama.cpp) |
| `IMPLEMENTATION_PHASE_3.md` | Step-by-step implementation for Ecosystem |
| `IMPLEMENTATION_PHASE_4.md` | Step-by-step implementation for Production |
| `REPOSITORY_INFRASTRUCTURE.md` | CI/CD, tooling, git hooks, publishing, composer setup |

## Phase Awareness

You are currently working on the project. Every task belongs to one of four phases:
- **Phase 1 (MVP):** ONNX inference only. Focus on core, onnx-backend, tensor, ai.
- **Phase 2 (LLM):** llama.cpp + tokenizer. Chat and streaming.
- **Phase 3 (Ecosystem):** embedding, vector, model-hub, pipeline, cpu-backend.
- **Phase 4 (Production):** shared memory, benchmarks, Laravel/Symfony, binary distribution.

When generating code, check which phase it belongs to. Do not prematurely implement Phase 3 features during Phase 1. Stub them out with clear exceptions: `throw new \RuntimeException('Not implemented in Phase 1. Upgrade to Phase 3.');`

## Error Messages Standard

Every exception message must answer three questions:
1. **What failed?** — class/method/operation
2. **Why?** — actual vs expected
3. **What to do?** — actionable guidance

Good: `"Failed to load model 'bert.onnx': file is not a valid ONNX model (magic bytes mismatch). Verify the file was exported correctly from PyTorch/TensorFlow."`

Bad: `"Model load error"`

## Commit Message Format

`type(scope): description`

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`, `ci`, `build`, `revert`

Scope is the package name: `core`, `tensor`, `onnx-backend`, `llama-backend`, `cpu-backend`, `tokenizer`, `embedding`, `pipeline`, `model-hub`, `vector`, `ai`, `laravel`, `symfony`

Good: `feat(core): add Shape value object with broadcasting validation`  
Good: `test(llama-backend): add contract tests for LlamaBackend`  
Bad: `added some stuff`  
Bad: `WIP`

## Response Style When Working on This Project

- Be concise. No explanations unless asked.
- Reference file paths and line numbers: `packages/core/src/Enums/Device.php:15`
- When implementing, output only the code. No preamble or postamble.
- When asked to explain, give the rationale from RESEARCH_ARCHITECTURE.md.
- When uncertain about a contract, open INTERFACE_CONTRACTS.md before answering.
- When asked to create a file, verify the exact path in FILE_TREE.md first.
