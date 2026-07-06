# FerryAI — Developer Guide (ferry-ai/php-inference)

**Inference-only** runtime for PHP 8.5+: unified API over native engines
(ONNX Runtime, llama.cpp, RubixML/Tensor). No training.

- **Direct inference** — runs inside the PHP process, no Python microservices.
  Native bridge: **PHP FFI**.
- Namespace: `FerryAI\` · Vendor: `ferry-ai/*` · Monorepo: `packages/{name}/`

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
- Skip native tests locally: `FERRY_AI_SKIP_NATIVE=1`.

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
