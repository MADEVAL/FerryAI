# FerryAI — Documentation Navigator

> **FerryAI** is a complete, production-grade inference runtime for PHP 8.5+.
> ONNX Runtime, llama.cpp, and RubixML backends — one API, zero Python.
> All 4 implementation phases complete. 568 tests. 20 examples.

---

## Quick Start

```bash
composer require ferry-ai/php-inference
```

| You want to... | Read |
|---|---|
| Install and run first inference | `README.md` (repo root) |
| See all capabilities in action | [`examples/`](../examples/) — 20 runnable scripts |
| Understand the architecture | `TECHNICAL_SPECIFICATION.md` |
| Look up a method signature | `INTERFACE_CONTRACTS.md` |
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
├── packages/                     # 14 packages (monorepo)
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
| `TECHNICAL_SPECIFICATION.md` | Architecture bible — layers, packages, components | Everyone |
| `INTERFACE_CONTRACTS.md` | Exact method signatures for every contract | Developers |
| `FILE_TREE.md` | Complete file map — 137 files in 14 packages | Developers |
| `RESEARCH_ARCHITECTURE.md` | Why decisions were made — ecosystem analysis | Architects |
| `REPOSITORY_INFRASTRUCTURE.md` | CI/CD, composer, testing, publishing | DevOps |
| `SOURCES.md` | External dependency audit — versions, URLs | Maintainers |
| `SKILL.md` | AI agent coding conventions and rules | AI agents |
| `BUILD_LOG.md` | Development journal — what, when, why | Historians |
| `DEBT_REPORT.md` | Technical debt inventory — stubs, mocks, gates | Maintainers |
| `IMPLEMENTATION_PHASE_1.md` | Phase 1 build record (MVP: ONNX inference) | Archive |
| `IMPLEMENTATION_PHASE_2.md` | Phase 2 build record (LLM: llama.cpp) | Archive |
| `IMPLEMENTATION_PHASE_3.md` | Phase 3 build record (Ecosystem) | Archive |
| `IMPLEMENTATION_PHASE_4.md` | Phase 4 build record (Production) | Archive |
| `EXAMPLES_PLAN.md` | Coverage matrix for 20 examples | Contributors |

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
composer test                # 568 unit tests (pure PHP)
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
| ONNX Runtime inference | ✅ Production — tested on Windows x64, ONNX 1.27.0 |
| llama.cpp probe | ✅ Library loads, init works. Full inference needs C-wrapper DLL (§DEBT_REPORT) |
| Pure-PHP tokenizer | ✅ BPE + WordPiece, round-tripping, chunking |
| Vector store | ✅ SQLite brute-force + metadata filter |
| Model Hub | ✅ HuggingFace API, SHA-256, Ed25519 |
| Pipeline | ✅ 8 composable stages, Generator-based |
| CPU fallback | ✅ Always available |
| Framework integrations | ✅ Laravel + Symfony (standalone adapters) |
| Unit tests | ✅ 568/568 |
| Examples | ✅ 20/20 runnable |

---

> **Implementation phases 1–4 are complete.** Phase documents (`IMPLEMENTATION_PHASE_1-4.md`) are preserved as build records. For current project status, see `DEBT_REPORT.md`.
