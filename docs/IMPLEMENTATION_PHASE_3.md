# Implementation Phase 3 — Ecosystem (embedding, vector, model-hub, pipeline, cpu-backend)

> **Status: fully implemented.** The step-by-step ТЗ for this phase has been removed because it
> is done. The implemented surface lives in the code and in the living documentation:
>
> - Code: `packages/embedding`, `packages/vector`, `packages/model-hub`, `packages/pipeline`,
>   `packages/cpu-backend`.
> - Interfaces: `packages/core/src/Contracts/{Embedder,VectorStore,ModelHub,Pipeline,Stage}.php`.
> - Usage & API: `docs/embedding.md`, `docs/vector-store.md`, `docs/model-hub.md`,
>   `docs/pipeline.md`, `docs/backends/cpu.md`, `docs/api-reference.md`.
>
> **Nothing from this phase is outstanding.** Environment/verification notes (sqlite-vec, RubixML
> isolation, PostgreSQL from WSL) are in `docs/DEBT_REPORT.md`.
