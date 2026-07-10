# Changelog

All notable changes to FerryAI. Format loosely follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

## [0.1.1] - 2026-07-09

### Security
- Harden `.ai` archive deserialization and native-binary integrity checks
  (`ai`, `cpu-backend`).

### Changed
- Reorganize repository root: move community-health files to `.github/` and
  documentation into `docs/`.
- Drop the PHP baseline to 8.3 (replace `\Pdo\Sqlite` with `\PDO` +
  `getAttribute()` + `method_exists()` runtime checks).
- Remove Docker files (native FFI runtime needs no container sidecars).

### Fixed
- CI: auto-detect the VS2022 toolchain path, correct the release asset name and
  line-ending normalization, and exclude `SharedMemoryManager` from Psalm
  (ext-shmop `Shmop` class not recognized).
- Documentation: correct the supported-versions table for the 0.1.x pre-1.0 line.

## [0.1.0] - 2026-07-08

First public release. Inference-only runtime for PHP 8.3+ with a unified API over
ONNX Runtime, llama.cpp and RubixML/Tensor engines.

### Added
- PostgreSQL + pgvector vector store (`PostgresStore`, `PostgresCollection`, `PostgresVecIndex`)
  with native ANN (`<=>`/`<->`/`<#>`) and HNSW/IVFFlat indexes; driver switch in `AIFactory`.
- SQLite native KNN via the sqlite-vec (`vec0`) extension, opt-in, with brute-force fallback.
- `Observability` wrapper wiring `Metrics`/`Profiler`/`Logger` into the facade (opt-in);
  `ModelPool` integration (pooled model loading, LRU eviction, real `warmup`).
- `NativeBinaryManager` library resolution in `AIFactory`; `RetryHandler`+`Logger` in the model hub.
- CPU backend: real pure-PHP tensor arithmetic and RubixML `.rbm` inference (isolated).
- FFI CDEF generator (`Core\FFI\CdefGenerator`, `bin/generate-ffi.php`).
- Facade config-wiring for `embed`/`similarity`; PSR-7 `StreamResponse::create()`.
- llama.cpp CPU + GPU inference via the `ferry_llama` wrapper, wired into `LlamaBackend`
  (`AI::chat()`/`AI::stream()`), with a native top-k pre-filter for fast sampling.
- Strict grammar-constrained sampling (`GbnfMatcher` + `GrammarSampler`); `sampler`/`grammar`
  options on `AI::chat()`.
- Per-capability documentation under `docs/` and this changelog.

### Changed
- `AIFactory` caches embedders per model; `AI::chat` pools its model (no reload per call).
- `SamplerFactory::forParams()` selects greedy/nucleus by temperature; `softmax` honours temperature.

### Fixed
- `RubixMLAdapter::isAvailable()` used `class_exists` for the `Estimator` interface (now `interface_exists`).
- All PHPStan level 8 findings resolved (gate fully green).
