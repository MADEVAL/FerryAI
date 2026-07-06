# Implementation Phase 1 — MVP (core, tensor, onnx-backend, ai)

> **Status: fully implemented.** The step-by-step ТЗ for this phase has been removed because it
> is done. The implemented surface lives in the code and in the living documentation:
>
> - Code: `packages/core`, `packages/tensor`, `packages/onnx-backend`, `packages/ai`.
> - Interfaces: `packages/core/src/Contracts/` (the source of truth).
> - Usage & API: `docs/api-reference.md`, `docs/backends/onnx.md`, `docs/tensor.md`,
>   `docs/core.md`, `docs/getting-started.md`.
> - File map: `docs/FILE_TREE.md`.
>
> **Not implemented from this phase** (tracked in `docs/DEBT_REPORT.md`):
> - `tensor/src/BackedTensor.php` arithmetic — Phase-1 stub (`ArrayTensor` is the working tensor). §3
> - ONNX GPU execution providers (TensorRT/DirectML/OpenVINO/ROCm, CoreML wiring, CUDA/cuDNN). §2/§13
