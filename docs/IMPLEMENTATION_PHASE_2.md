# Implementation Phase 2 — LLM (llama-backend, tokenizer)

> **Status: fully implemented.** The step-by-step ТЗ for this phase has been removed because it
> is done. The implemented surface lives in the code and in the living documentation:
>
> - Code: `packages/llama-backend`, `packages/tokenizer`.
> - Interfaces: `packages/core/src/Contracts/Tokenizer.php`, `Backend`, `Model`.
> - Usage & API: `docs/backends/llama.md`, `docs/tokenizer.md`, `docs/streaming.md`, `docs/api-reference.md`.
> - Native wrapper build: `native/llama-wrapper/` + `docs/backends/llama.md`.
>
> **Not implemented from this phase** (tracked in `docs/DEBT_REPORT.md`, §1):
> - HuggingFace native tokenizer (`tokenizers-cpp` FFI binding) — optional accelerator; the
>   pure-PHP BPE/WordPiece tokenizers cover all needed types.
