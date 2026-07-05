# Changelog

All notable changes to FerryAI will be documented in this file.

## [Unreleased]

### Added
- ONNX Runtime backend with full inference (embeddings, classification)
- llama.cpp FFI probe and backend initialization
- Pure-PHP tokenizer (BPE, WordPiece) with round-tripping and chunking
- Embedding system with Mean/CLS/EOS/Max pooling strategies
- SQLite vector store with brute-force search and metadata filtering
- Model Hub: HuggingFace download, LRU cache, SHA-256 + Ed25519 verification
- Composable pipeline with 8 stages (Transform, Filter, Normalize, Chunk, etc.)
- CPU-native backend (always-available fallback)
- Shared memory model loading via ext-shmop
- Async inference with PHP Fibers
- Metrics and Profiler
- RetryHandler with exponential/linear backoff
- PlatformDetector for OS/arch detection
- Laravel integration (ServiceProvider + Facade)
- Symfony integration (Bundle + DI Extension)
- 568 unit tests, 20 runnable examples
- CI/CD pipeline (GitHub Actions)
- Docker image and docker-compose configuration
