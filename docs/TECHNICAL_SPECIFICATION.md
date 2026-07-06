# Technical Specification

> **The architecture described here is fully implemented.** The detailed specification has been
> reduced: implemented content now lives with the code and the living documentation, and anything
> not yet built is tracked in `docs/DEBT_REPORT.md`.
>
> Where to look now:
>
> | You want… | Read |
> |---|---|
> | The layered architecture at a glance | `docs/README.md` (Architecture at a Glance) |
> | Why the design decisions were made | `docs/RESEARCH_ARCHITECTURE.md` |
> | Exact interfaces (source of truth) | `packages/core/src/Contracts/` + `docs/api-reference.md` |
> | Where every class lives | `docs/FILE_TREE.md` |
> | Per-capability usage guides | `docs/*.md` (onnx, llama, embedding, vector-store, pipeline,
>   model-hub, tokenizer, tensor, core, backends/cpu, streaming, laravel, symfony) |
> | What is NOT yet implemented | `docs/DEBT_REPORT.md` |

## Architectural rules (unchanged, enforced)

1. **Inference-only** — load models, run the forward pass. No training/autograd/optimisers.
2. **FFI is the only bridge** to native code. No `shell_exec` to Python.
3. **Backend isolation** — backends do not know about each other; only the `ai` package composes them.
4. **Contracts are truth** — signatures live in `packages/core/src/Contracts/`; implementations do not deviate.
5. **Exceptions** — all extend `FerryAIException`, each with an `errorCode()` of the form `FERRY_AI_*`.
6. **Zero-copy** — do not copy data PHP↔native without need; `toArray()` is marked expensive.
7. **No hard framework coupling** — models are never committed to the repository.
