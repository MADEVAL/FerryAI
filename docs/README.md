# FerryAI — Documentation Navigator

> **FerryAI** is a complete inference runtime for PHP 8.3+.
> ONNX Runtime, llama.cpp, and RubixML backends — one API, zero Python.

---

## Quick Start

```bash
composer require ferry-ai/php-inference
```

| You want to... | Read |
|---|---|
| Install and run first inference | `README.md` (repo root) |
| See all capabilities in action | [`examples/`](../examples/) — 26 runnable scripts |
| Look up a method / facade signature | `api-reference.md` (+ interfaces in `packages/core/src/Contracts/`) |
| Read a per-capability guide | the guides below (onnx, llama, cpu, embedding, vector-store, pipeline, model-hub, tokenizer, tensor, core, streaming, laravel, symfony) |
| Find where a class lives | `FILE_TREE.md` |
| Set up CI/CD or dev tooling | `REPOSITORY_INFRASTRUCTURE.md` |
| Check external dependency versions | `SOURCES.md` |

---

## Project Structure

```
FerryAI/
├── README.md                     # Project pitch, quick start, verified status
├── composer.json                 # Root meta-package
├── packages/                     # 14 packages (monorepo)
│   ├── core/                     # Contracts, enums, value objects, exceptions
│   ├── tensor/                   # ArrayTensor, tensor factory
│   ├── onnx-backend/             # ONNX Runtime FFI backend
│   ├── llama-backend/            # llama.cpp FFI backend
│   ├── tokenizer/                # Pure-PHP BPE/WordPiece tokenizers
│   ├── embedding/                # Pooling strategies, Embedder
│   ├── vector/                   # SQLite + PostgreSQL/pgvector vector store
│   ├── model-hub/                # HuggingFace download, cache, verify
│   ├── pipeline/                 # Composable stages (8 types)
│   ├── cpu-backend/              # Always-available CPU fallback
│   ├── dataframe/                # Tabular data: Column-oriented, CSV/JSON I/O
│   ├── ai/                       # Facade, factory, registry, metrics, profiler
│   ├── laravel/                  # Service provider + facade
│   └── symfony/                  # Bundle + DI extension
├── examples/                     # 26 standalone runnable examples
├── tests/                        # Integration + verification suites
└── docs/                         # You are here
```

---

## Document Map

| Document | Role | Audience |
|----------|------|----------|
| `api-reference.md` | Facade + contracts/value-objects/exceptions quick reference | Developers |
| `TECHNICAL_SPECIFICATION.md` | Architecture rules and design decisions | Everyone |
| `INTERFACE_CONTRACTS.md` | All method signatures | Developers |
| `FILE_TREE.md` | Complete file map across all packages | Developers |
| `REPOSITORY_INFRASTRUCTURE.md` | CI/CD, composer, testing, publishing | DevOps |
| `SOURCES.md` | External dependency versions and URLs | Maintainers |

### Per-capability guides

`getting-started.md`, `configuration.md`, `api-reference.md`, `backends/onnx.md`,
`backends/llama.md`, `backends/cpu.md`, `embedding.md`, `vector-store.md`, `pipeline.md`,
`model-hub.md`, `tokenizer.md`, `tensor.md`, `core.md`, `streaming.md`, `security.md`,
`safetensors-conversion.md`, `laravel.md`, `symfony.md`, `deployment.md`, `troubleshooting.md`.

---

## Architecture at a Glance

```
PHP Application
    │
    ▼
AI Facade (FerryAI\AI)
    │
    ├─ Backend Registry ── Task Router
    │      ├─ OnnxBackend ──── FFI ──► onnxruntime.dll
    │      ├─ LlamaBackend ─── FFI ──► ferry_llama.dll
    │      └─ CpuNativeBackend ── pure PHP
    │
    ├─ Embedder ─── Tokenizer ─── Pipeline ─── VectorStore
    └─ ModelHub ─── CacheManager ─── SignatureVerifier
```

**Rules:** inference-only. FFI is the only bridge to native code. Backends never know about each other.
Contracts in `packages/core/src/Contracts/` define truth — implementations never deviate.

---

## Key Commands

```bash
composer test                # Unit tests (pure PHP)
composer test-integration    # Integration tests (needs ONNX/libllama)
composer check               # Pre-commit: cs-fix + PHPStan lvl8 + Psalm lvl3 + tests
composer cs-fix              # Auto-fix code style (PER-CS 2.0)
composer stan                # PHPStan static analysis
composer psalm               # Psalm static analysis
```
