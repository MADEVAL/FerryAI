# Deployment

FerryAI is a library in your PHP process; "deployment" means making the native libraries and
models available and tuning the runtime.

## Runtime prerequisites

- PHP 8.5+ with `ext-ffi` enabled (`ffi.enable=1` in `php.ini`; for CLI it is usually on).
- The native shared libraries a feature needs, on `PATH` (Windows) / `LD_LIBRARY_PATH` (Linux):
  ONNX Runtime, `ferry_llama.dll` + llama/ggml DLLs, sqlite-vec, etc. See the
  [Dependencies & downloads](../README.md#dependencies--downloads) matrix.
- Models placed on disk; point config at them (never bundle in the image unless intended).

## Long-running workers (FPM / RoadRunner / FrankenPHP)

- **Reuse the process**: model loading is expensive. FerryAI pools models (`AI::warmup()` +
  `ModelPool`) and caches embedders, so a long-lived worker loads each model once.
- **Shared weights**: set `model_pool.shared_memory=true` (needs `ext-shmop`) to share read-only
  weights across workers.
- **Memory**: keep `model_pool.max_memory_bytes` within the worker's limit; large GGUF/ONNX models
  use `StreamLoader`/mmap so they are not fully copied into PHP memory.

## GPU

Ship a CUDA-enabled build (ONNX Runtime GPU or a llama.cpp CUDA build) plus the NVIDIA CUDA
Toolkit and cuDNN on the host, and set `device: cuda`. Verified for llama.cpp (RTX 4060).

## Streaming behind a proxy

For SSE, disable buffering: PHP (`ob_end_flush`), FPM, and the proxy
(`X-Accel-Buffering: no` for Nginx). See [streaming](streaming.md).

## Docker

A reference `Dockerfile` (php:8.5-cli + ffi/pdo_sqlite/zip/sodium) and `docker-compose.yaml` are in
the repo root. Add the native libraries + models via volumes or build steps for the features you
use.

## CI

`composer check` (cs-fix + PHPStan L8 + Psalm L3 + unit tests) is the gate. Native integration
tests require the libraries/models and are skipped otherwise (`FERRY_AI_SKIP_NATIVE=1`).

## Linux / WSL

Supported target for Linux/WSL.
