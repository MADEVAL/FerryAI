# FerryAI — Documentation Navigator

> **FerryAI** is a complete, production-grade inference runtime for PHP 8.5+.
> ONNX Runtime, llama.cpp, and RubixML backends — one API, zero Python.
> All 4 implementation phases complete. 686 tests. 20 examples.

---

## Quick Start

```bash
composer require ferry-ai/php-inference
```

| You want to... | Read |
|---|---|
| Install and run first inference | `README.md` (repo root) |
| See all capabilities in action | [`examples/`](../examples/) — 20 runnable scripts |
| Look up a method / facade signature | `api-reference.md` (+ interfaces in `packages/core/src/Contracts/`) |
| Read a per-capability guide | the guides below (onnx, llama, cpu, embedding, vector-store, pipeline, model-hub, tokenizer, tensor, core, streaming, laravel, symfony) |
| Find where a class lives | `FILE_TREE.md` |
| Understand a design decision | `RESEARCH_ARCHITECTURE.md` |
| Set up CI/CD or dev tooling | `REPOSITORY_INFRASTRUCTURE.md` |
| Check external dependency versions | `SOURCES.md` |
| Review what's stubbed / not yet done | `DEBT_REPORT.md` |
| See what was built and when | `BUILD_LOG.md` (development journal) |
| Write AI-assisted code | `SKILL.md` (AI agent conventions) |

---

## Project Structure

```
FerryAI/
├── README.md                     # Project pitch, quick start, verified status
├── AGENTS.md                     # AI agent instruction set
├── composer.json                 # Root meta-package
├── packages/                     # 13 packages (monorepo; dataframe deferred — see DEBT_REPORT)
│   ├── core/                     # Contracts, enums, value objects, exceptions
│   ├── tensor/                   # ArrayTensor, BackedTensor, TensorFactory
│   ├── onnx-backend/             # ONNX Runtime FFI backend
│   ├── llama-backend/            # llama.cpp FFI backend
│   ├── tokenizer/                # Pure-PHP BPE/WordPiece tokenizers
│   ├── embedding/                # Pooling strategies, Embedder
│   ├── vector/                   # SQLite vector store, brute-force search
│   ├── model-hub/                # HuggingFace download, cache, verify
│   ├── pipeline/                 # Composable stages (8 types)
│   ├── cpu-backend/              # Always-available CPU fallback
│   ├── ai/                       # Facade, factory, registry, metrics, profiler
│   ├── laravel/                  # Service provider + facade
│   └── symfony/                  # Bundle + DI extension
├── examples/                     # 20 standalone runnable examples
├── benchmarks/                   # Performance measurement scripts
├── bin/ferry-ai                  # CLI entry point
├── tests/                        # Integration + verification suites
└── docs/                         # You are here
```

---

## Document Map

| Document | Role | Audience |
|----------|------|----------|
| `api-reference.md` | Facade + contracts/value-objects/exceptions quick reference | Developers |
| `TECHNICAL_SPECIFICATION.md` | **Pruned** — implemented; redirects to code + guides + DEBT | Everyone |
| `INTERFACE_CONTRACTS.md` | **Pruned** — signatures live in `packages/core/src/Contracts/` | Developers |
| `FILE_TREE.md` | Complete file map across the built packages | Developers |
| `RESEARCH_ARCHITECTURE.md` | Why decisions were made — ecosystem analysis | Architects |
| `REPOSITORY_INFRASTRUCTURE.md` | CI/CD, composer, testing, publishing | DevOps |
| `SOURCES.md` | External dependency audit — versions, URLs | Maintainers |
| `SKILL.md` | AI agent coding conventions and rules | AI agents |
| `BUILD_LOG.md` | Development journal — what, when, why | Historians |
| `DEBT_REPORT.md` | Technical debt + everything not yet implemented | Maintainers |
| `IMPLEMENTATION_PHASE_1..4.md` | **Pruned** — phases done; redirect to code/guides/DEBT | Archive |
| `EXAMPLES_PLAN.md` | Coverage matrix for 20 examples | Contributors |

### Per-capability guides

`getting-started.md`, `configuration.md`, `api-reference.md`, `backends/onnx.md`,
`backends/llama.md`, `backends/cpu.md`, `embedding.md`, `vector-store.md`, `pipeline.md`,
`model-hub.md`, `tokenizer.md`, `tensor.md`, `core.md`, `streaming.md`, `security.md`,
`laravel.md`, `symfony.md`, `deployment.md`, `troubleshooting.md`.

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
    │      ├─ LlamaBackend ─── FFI ──► llama.dll
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
composer test                # 686 unit tests (pure PHP)
composer test-integration    # Integration tests (needs ONNX/libllama)
composer check               # Pre-commit: cs-fix + PHPStan lvl8 + Psalm lvl3 + tests
composer cs-fix              # Auto-fix code style (PER-CS 2.0)
composer stan                # PHPStan static analysis
composer psalm               # Psalm static analysis
```

---

## Status

| Component | Status |
|-----------|--------|
| ONNX Runtime inference | ✅ Production — CPU + GPU verified (Windows + Linux/WSL, ONNX 1.27.0, CUDA 13, cuDNN 9) (DEBT §13) |
| llama.cpp chat/stream | ✅ Real inference on CPU + GPU via the `ferry_llama` wrapper (Windows + Linux) |
| Pure-PHP tokenizer | ✅ BPE + WordPiece, round-tripping, chunking |
| Vector store | ✅ SQLite brute-force + sqlite-vec + PostgreSQL/pgvector + metadata filter |
| Model Hub | ✅ HuggingFace API, streaming download, SHA-256, Ed25519 |
| Pipeline | ✅ 8 composable stages, Generator-based |
| CPU fallback | ✅ Always available (pure PHP; real `.rbm` via RubixML) |
| Framework integrations | ✅ Laravel + Symfony (standalone adapters) |
| Unit tests | ✅ 686/686 |
| Examples | ✅ 20/20 runnable |

---

> **Implementation phases 1–4 are complete.** Phase documents (`IMPLEMENTATION_PHASE_1-4.md`) are preserved as build records. For current project status, see `DEBT_REPORT.md`.
