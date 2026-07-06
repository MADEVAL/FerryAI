# Technical Specification

## Architectural rules

1. **Inference-only** — load models, run the forward pass. No training/autograd/optimisers.
2. **FFI is the only bridge** to native code. No `shell_exec` to Python.
3. **Backend isolation** — backends do not know about each other; only the `ai` package composes them.
4. **Contracts are truth** — signatures live in `packages/core/src/Contracts/`; implementations do not deviate.
5. **Exceptions** — all extend `FerryAIException`, each with an `errorCode()` of the form `FERRY_AI_*`.
6. **Zero-copy** — do not copy data PHP↔native without need; `toArray()` is marked expensive.
7. **No hard framework coupling** — models are never committed to the repository.
