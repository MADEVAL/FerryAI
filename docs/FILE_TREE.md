# FerryAI — Complete File Tree

> Version: 1.0  
> Purpose: per-file map of each package, namespaces, dependencies  
> Rule: the structure here is canon. Every file, class, interface is in its place.

---

## MONOREPO STRUCTURE

```
php-inference/
├── composer.json                    # Root meta-package
├── README.md
├── phpunit.xml.dist                 # Root tests config
├── packages/
│   ├── core/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── Contracts/
│   │       │   ├── Backend.php
│   │       │   ├── Model.php
│   │       │   ├── Tensor.php
│   │       │   ├── Tokenizer.php
│   │       │   ├── Embedder.php
│   │       │   ├── VectorStore.php
│   │       │   ├── Pipeline.php
│   │       │   ├── Stage.php
│   │       │   ├── ModelHub.php
│   │       │   └── DataFrame.php
│   │       ├── Enums/
│   │       │   ├── Device.php
│   │       │   ├── DType.php
│   │       │   ├── BackendType.php
│   │       │   ├── TokenizerType.php
│   │       │   ├── GraphOptimizationLevel.php
│   │       │   ├── DistanceMetric.php
│   │       │   ├── IndexType.php
│   │       │   └── QuantizationType.php
│   │       ├── ValueObjects/
│   │       │   ├── Shape.php
│   │       │   ├── ModelMetadata.php
│   │       │   ├── ChatMessage.php
│   │       │   ├── SamplingParams.php
│   │       │   ├── GenerationResult.php
│   │       │   ├── EmbeddingResult.php
│   │       │   └── ClassificationResult.php
│   │       ├── Exception/
│   │       │   ├── FerryAIException.php
│   │       │   ├── BackendNotAvailableException.php
│   │       │   ├── ModelNotFoundException.php
│   │       │   ├── ModelLoadException.php
│   │       │   ├── InferenceException.php
│   │       │   ├── ShapeMismatchException.php
│   │       │   ├── DeviceNotAvailableException.php
│   │       │   ├── TokenizerException.php
│   │       │   ├── ConfigurationException.php
│   │       │   ├── InvalidStateException.php
│   │       │   ├── IoException.php
│   │       │   └── ValidationException.php
│   │       ├── AIConfig.php
│   │       ├── PlatformDetector.php     # OS/arch detection
│   │       ├── Logger.php               # PSR-3 logger
│   │       ├── RetryHandler.php         # Retry logic
│   │       ├── Tensor/
│   │       │   └── CommonTensorOps.php  # Shared tensor helpers (trait)
│   │       └── FFI/
│   │           └── CdefGenerator.php    # C header → \FFI::cdef() string
│   ├── tensor/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── TensorFactory.php
│   │       └── ArrayTensor.php          # Pure-PHP implementation for CPU fallback
│   ├── onnx-backend/
│   │   ├── composer.json
│   │   └── src/
│   │       ├── OnnxBackend.php
│   │       ├── OnnxModel.php
│   │       ├── OnnxTensor.php
│   │       ├── Provider/
│   │       │   ├── ExecutionProvider.php
│   │       │   └── CpuProvider.php
│   │       ├── OnnxRuntimeFactory.php
│   │       ├── OnnxTypeMapper.php        # ONNX type ↔ FerryAI enum mapping
│   │       └── Runtime/
│   │           ├── OnnxRuntimeInterface.php  # Mockable seam
│   │           ├── OnnxSession.php           # Session handle marker
│   │           ├── NativeOnnxRuntime.php     # Production FFI implementation
│   │           └── NativeOnnxSession.php     # Production session wrapper
│   ├── llama-backend/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── LlamaBackend.php
│   │       ├── LlamaModel.php
│   │       ├── FFI/
│   │       │   └── FerryLlama.php        # Single FFI wrapper for llama.cpp C API
│   │       ├── Runtime/
│   │       │   ├── LlamaRuntimeInterface.php  # Mockable seam
│   │       │   ├── LlamaSession.php           # Session handle marker
│   │       │   ├── NativeLlamaRuntime.php     # Production FFI implementation
│   │       │   └── NativeLlamaSession.php     # Production session wrapper
│   │       ├── Sampling/
│   │       │   ├── Sampler.php
│   │       │   ├── GreedySampler.php
│   │       │   ├── TopPSampler.php
│   │       │   ├── TopKSampler.php
│   │       │   ├── GrammarSampler.php
│   │       │   ├── SamplerMath.php            # Softmax/argmax/weighted pick
│   │       │   └── SamplerFactory.php
│   │       ├── Grammar/
│   │       │   ├── GbnfGrammar.php
│   │       │   ├── GbnfMatcher.php
│   │       │   ├── GbnfNode.php
│   │       │   └── JsonSchemaConverter.php
│   │       ├── LlamaContextParams.php     # Value Object for llama_context_params
│   │       ├── LlamaModelParams.php       # Value Object for llama_model_params
│   │       └── ChatFormatter.php          # ChatML → LLM format
│   ├── cpu-backend/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── CpuNativeBackend.php
│   │       ├── CpuNativeModel.php
│   │       ├── CpuNativeTensor.php
│   │       ├── Predictor.php             # Predictor interface (RubixML seam)
│   │       └── RubixMLAdapter.php        # Adapter to RubixML/ML
│   ├── tokenizer/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── TokenizerFactory.php
│   │       ├── HuggingFaceTokenizer.php   # Primary: binding to tokenizers-cpp via FFI
│   │       ├── PureBpeTokenizer.php       # Fallback: Pure PHP BPE
│   │       ├── PureWordPieceTokenizer.php # Fallback: Pure PHP WordPiece
│   │       ├── SpecialTokens.php            # Bos/eos/unk/pad role extraction
│   │       └── TokenizerLoader.php        # Load tokenizer.json + type detection
│   ├── embedding/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── Embedder.php               # Main implementation
│   │       ├── EmbeddedModels.php         # Built-in models (all-MiniLM-L6-v2, etc.)
│   │       └── Pooling/
│   │           ├── PoolingStrategy.php     # Interface
│   │           ├── ClsPooling.php
│   │           ├── MeanPooling.php
│   │           ├── EosPooling.php
│   │           └── MaxPooling.php
│   ├── pipeline/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── Pipeline.php               # Contracts\Pipeline implementation
│   │       ├── FiberPipeline.php          # Fibers-based implementation
│   │       └── Stages/
│   │           ├── ChunkStage.php
│   │           ├── TokenizeStage.php
│   │           ├── EmbedStage.php
│   │           ├── NormalizeStage.php
│   │           ├── StoreStage.php
│   │           ├── ClassifyStage.php
│   │           ├── FilterStage.php
│   │           └── TransformStage.php
│   ├── model-hub/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── Hub.php                    # Contracts\ModelHub implementation
│   │       ├── HuggingFaceClient.php      # HTTP client for HuggingFace API
│   │       ├── CacheManager.php           # LRU cache, cleanup, size
│   │       ├── ModelVerifier.php          # SHA-256, Ed25519, magic bytes
│   │       ├── ModelIntrospector.php      # Read metadata without loading
│   │       ├── Downloader.php             # Download with progress, retries, resume
│   │       ├── StreamLoader.php           # Streaming/mmap loading of large models
│   │       ├── Format/
│   │       │   ├── FormatDetector.php     # Format detection (.onnx, .gguf, .safetensors, .ai)
│   │       │   ├── AiArchive.php          # Read/write .ai archives
│   │       │   ├── OnnxInspector.php      # .onnx introspection
│   │       │   ├── GgufInspector.php      # .gguf introspection
│   │       │   └── SafetensorsInspector.php # .safetensors header introspection
│   │       └── Signature/
│   │           ├── SignatureVerifier.php  # Ed25519 verification
│   │           └── Sha256Verifier.php     # SHA-256 verification
│   ├── vector/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── Collection.php             # Contracts\VectorStore implementation
│   │       ├── CollectionManager.php      # Collection management (create, delete)
│   │       ├── SQLiteStore.php            # PDO wrapper over SQLite
│   │       ├── SqliteVecExtension.php     # FFI binding to sqlite-vec extension
│   │       ├── PostgresStore.php          # PDO wrapper over PostgreSQL + pgvector
│   │       ├── PostgresCollection.php     # Contracts\VectorStore over pgvector (native ANN)
│   │       ├── PostgresVecIndex.php       # HNSW/IVFFlat pgvector indexes
│   │       ├── BruteForceIndex.php        # Fallback brute force (PHP)
│   │       ├── MetadataFilter.php         # WHERE-like filter parser
│   │       └── ExportImport.php           # Export/Import to JSON/Parquet
│   ├── dataframe/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── DataFrame.php              # Contracts\DataFrame implementation
│   │       ├── Column.php                 # Typed column
│   │       └── IO/
│   │           ├── CsvReader.php
│   │           ├── CsvWriter.php
│   │           ├── JsonReader.php
│   │           └── ParquetReader.php
│   ├── ai/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── AI.php                     # Facade
│   │       ├── AIFactory.php              # Factory
│   │       ├── BackendRegistry.php        # Backend registry
│   │       ├── TaskRouter.php             # Task routing by backends
│   │       ├── StreamResponse.php        # HTTP streaming response
│   │       ├── SharedMemoryManager.php    # System V shared memory
│   │       ├── ModelPool.php              # Preloaded model pool
│   │       ├── AsyncInference.php         # Fibers-based async inference
│   │       ├── NativeBinaryManager.php    # Auto-download native binaries
│   │       ├── Metrics.php                # Prometheus/StatsD metrics
│   │       ├── Profiler.php               # Profiling and benchmarks
│   │       ├── Observability.php          # Metrics/profiling/logging wrapper
│   │       ├── LibraryResolver.php        # Native library resolution interface
│   │       └── SharedMemory.php           # Shared memory interface
│   ├── laravel/                           # Laravel integration
│   │   ├── composer.json
│   │   └── src/
│   │       ├── AIServiceProvider.php
│   │       └── Facades/
│   │           └── AI.php                 # Laravel Facade
│   └── symfony/                           # Symfony integration
│       ├── composer.json
│       └── src/
│           ├── AIBundle.php
│           └── DependencyInjection/
│               ├── Configuration.php
│               └── FerryAIExtension.php
```

---

## FILE INDEX BY PACKAGE

### Package `core` (39 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `Contracts/Backend.php` | Interface `Backend` | Enums\Device, ValueObjects\ModelMetadata |
| 2 | `Contracts/Model.php` | Interface `Model` | Enums\Device, ValueObjects\ModelMetadata |
| 3 | `Contracts/Tensor.php` | Interface `Tensor` (extends ArrayAccess, Countable, JsonSerializable) | Enums\Device, Enums\DType, ValueObjects\Shape |
| 4 | `Contracts/Tokenizer.php` | Interface `Tokenizer` | Enums\TokenizerType |
| 5 | `Contracts/Embedder.php` | Interface `Embedder` | (none) |
| 6 | `Contracts/VectorStore.php` | Interface `VectorStore` | (none) |
| 7 | `Contracts/Pipeline.php` | Interface `Pipeline` | Contracts\Stage |
| 8 | `Contracts/Stage.php` | Interface `Stage` | (none) |
| 9 | `Contracts/ModelHub.php` | Interface `ModelHub` | ValueObjects\ModelMetadata |
| 10 | `Contracts/DataFrame.php` | Interface `DataFrame` (extends Iterator, Countable) | Contracts\Tensor |
| 11 | `Enums/Device.php` | Enum `Device` (CPU, CUDA, ROCM, METAL, VULKAN, DIRECTML, OPENVINO, OPENCL, AUTO) | (none) |
| 12 | `Enums/DType.php` | Enum `DType` (Float32, Float16, Int32, Int64, String) | (none) |
| 13 | `Enums/BackendType.php` | Enum `BackendType` (Onnx, Llama, CpuNative) | (none) |
| 14 | `Enums/TokenizerType.php` | Enum `TokenizerType` (BPE, WordPiece, SentencePiece, Unigram) | (none) |
| 15 | `Enums/GraphOptimizationLevel.php` | Enum `GraphOptimizationLevel` | (none) |
| 16 | `Enums/DistanceMetric.php` | Enum `DistanceMetric` (COSINE, EUCLIDEAN, DOT) | (none) |
| 17 | `Enums/IndexType.php` | Enum `IndexType` (HNSW, IVF, FLAT) | (none) |
| 18 | `Enums/QuantizationType.php` | Enum `QuantizationType` (FLOAT32, FLOAT16, INT8, BINARY) | (none) |
| 19 | `ValueObjects/Shape.php` | readonly class `Shape` | (none) |
| 20 | `ValueObjects/ModelMetadata.php` | readonly class `ModelMetadata` | (none) |
| 21 | `ValueObjects/ChatMessage.php` | readonly class `ChatMessage` | (none) |
| 22 | `ValueObjects/SamplingParams.php` | readonly class `SamplingParams` | (none) |
| 23 | `ValueObjects/GenerationResult.php` | readonly class `GenerationResult` | (none) |
| 24 | `ValueObjects/EmbeddingResult.php` | readonly class `EmbeddingResult` | (none) |
| 25 | `ValueObjects/ClassificationResult.php` | readonly class `ClassificationResult` | (none) |
| 26 | `Exception/FerryAIException.php` | Base exception class (extends \RuntimeException) | (none) |
| 27 | `Exception/BackendNotAvailableException.php` | Exception | Exception\FerryAIException |
| 28 | `Exception/ModelNotFoundException.php` | Exception | Exception\FerryAIException |
| 29 | `Exception/ModelLoadException.php` | Exception | Exception\FerryAIException |
| 30 | `Exception/InferenceException.php` | Exception | Exception\FerryAIException |
| 31 | `Exception/ShapeMismatchException.php` | Exception | Exception\FerryAIException, ValueObjects\Shape |
| 32 | `Exception/DeviceNotAvailableException.php` | Exception | Exception\FerryAIException, Enums\Device |
| 33 | `Exception/TokenizerException.php` | Exception | Exception\FerryAIException |
| 34 | `Exception/ConfigurationException.php` | Exception | Exception\FerryAIException |
| 35 | `Exception/InvalidStateException.php` | Exception (`FERRY_AI_INVALID_STATE`) | Exception\FerryAIException |
| 36 | `Exception/IoException.php` | Exception (`FERRY_AI_IO`) | Exception\FerryAIException |
| 37 | `Exception/ValidationException.php` | Exception (`FERRY_AI_VALIDATION`) | Exception\FerryAIException |
| 38 | `AIConfig.php` | final class `AIConfig` (implements ArrayAccess) | Enums\Device, Enums\BackendType |
| 39 | `PlatformDetector.php` | class `PlatformDetector` — OS/arch detection | (none) |
| 40 | `Logger.php` | class `Logger` — PSR-3 compatible | (none) |
| 41 | `RetryHandler.php` | class `RetryHandler` — retry logic | (none) |
| 42 | `Tensor/CommonTensorOps.php` | trait `CommonTensorOps` — shared tensor helpers (shape inference, strides, slicing) | (none) |
| 43 | `FFI/CdefGenerator.php` | class `CdefGenerator` — C header → `\FFI::cdef()` string | (none) |

**Total: 43 files**

---

### Package `tensor` (3 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `TensorFactory.php` | final class `TensorFactory` | core Contracts\Tensor, Enums\Device, Enums\DType, ValueObjects\Shape |
| 2 | `ArrayTensor.php` | class `ArrayTensor` implements Tensor | core Contracts\Tensor (Pure PHP fallback, no FFI) |

**Total: 2 files**

---

### Package `onnx-backend` (11 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `OnnxBackend.php` | class `OnnxBackend` implements Backend | core Contracts\Backend, Runtime\OnnxRuntimeInterface |
| 2 | `OnnxModel.php` | class `OnnxModel` implements Model | core Contracts\Model |
| 3 | `OnnxTensor.php` | class `OnnxTensor` implements Tensor | core Contracts\Tensor (wraps native OrtValue) |
| 4 | `OnnxRuntimeFactory.php` | class `OnnxRuntimeFactory` | Runtime\OnnxRuntimeInterface |
| 5 | `OnnxTypeMapper.php` | final class `OnnxTypeMapper` — ONNX type/provider ↔ FerryAI enum mapping | core Enums\DType, Enums\Device |
| 6 | `Provider/ExecutionProvider.php` | interface `ExecutionProvider` | core Enums\Device |
| 7 | `Provider/CpuProvider.php` | class `CpuProvider` implements ExecutionProvider | Provider\ExecutionProvider |
| 8 | `Runtime/OnnxRuntimeInterface.php` | interface `OnnxRuntimeInterface` — mockable seam | Runtime\OnnxSession |
| 9 | `Runtime/OnnxSession.php` | class `OnnxSession` — session handle marker | (none) |
| 10 | `Runtime/NativeOnnxRuntime.php` | class `NativeOnnxRuntime` implements OnnxRuntimeInterface — production FFI impl | Runtime\OnnxRuntimeInterface, Runtime\NativeOnnxSession |
| 11 | `Runtime/NativeOnnxSession.php` | class `NativeOnnxSession` extends OnnxSession — production session wrapper | Runtime\OnnxSession |

**Total: 11 files**

> Device selection uses `OnnxTypeMapper::providerNamesForDevice()`, which maps each
> `Device` to ordered ONNX Runtime provider strings (e.g. `CUDAExecutionProvider`,
> `CoreMLExecutionProvider`, `DmlExecutionProvider`, `ROCMExecutionProvider`,
> `OpenVINOExecutionProvider`) with a `CPUExecutionProvider` fallback. There are no
> per-provider PHP classes beyond `CpuProvider`.

---

### Package `llama-backend` (21 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `LlamaBackend.php` | class `LlamaBackend` implements Backend | core Contracts\Backend, FFI\FerryLlama |
| 2 | `LlamaModel.php` | class `LlamaModel` implements Model | core Contracts\Model, Runtime\LlamaSession |
| 3 | `FFI/FerryLlama.php` | class `FerryLlama` — single FFI wrapper for llama.cpp C API | PHP FFI |
| 4 | `Runtime/LlamaRuntimeInterface.php` | interface `LlamaRuntimeInterface` — mockable seam | core Enums\GraphOptimizationLevel |
| 5 | `Runtime/LlamaSession.php` | class `LlamaSession` — session handle marker | (none) |
| 6 | `Runtime/NativeLlamaRuntime.php` | class `NativeLlamaRuntime` implements LlamaRuntimeInterface — production FFI impl | FFI\FerryLlama, Runtime\LlamaSession, Runtime\LlamaRuntimeInterface |
| 7 | `Runtime/NativeLlamaSession.php` | class `NativeLlamaSession` extends LlamaSession — production session wrapper | Runtime\LlamaSession, FFI\FerryLlama |
| 8 | `Sampling/Sampler.php` | interface `Sampler` | core ValueObjects\SamplingParams |
| 9 | `Sampling/GreedySampler.php` | class `GreedySampler` implements Sampler | Sampling\Sampler |
| 10 | `Sampling/TopPSampler.php` | class `TopPSampler` implements Sampler | Sampling\Sampler |
| 11 | `Sampling/TopKSampler.php` | class `TopKSampler` implements Sampler | Sampling\Sampler |
| 12 | `Sampling/GrammarSampler.php` | class `GrammarSampler` implements Sampler | Sampling\Sampler, Grammar\GbnfGrammar |
| 13 | `Sampling/SamplerMath.php` | class `SamplerMath` — softmax/argmax/weighted pick | (none) |
| 14 | `Sampling/SamplerFactory.php` | class `SamplerFactory` | Sampling\Sampler |
| 15 | `Grammar/GbnfGrammar.php` | final readonly class `GbnfGrammar` | (none) |
| 16 | `Grammar/GbnfMatcher.php` | class `GbnfMatcher` — incremental GBNF character-level matcher | Grammar\GbnfNode |
| 17 | `Grammar/GbnfNode.php` | class `GbnfNode` — GBNF grammar AST node | (none) |
| 18 | `Grammar/JsonSchemaConverter.php` | class `JsonSchemaConverter` | Grammar\GbnfGrammar |
| 19 | `LlamaContextParams.php` | readonly class `LlamaContextParams` — value object | (none) |
| 20 | `LlamaModelParams.php` | readonly class `LlamaModelParams` — value object | (none) |
| 21 | `ChatFormatter.php` | class `ChatFormatter` — ChatML → llama format | core ValueObjects\ChatMessage |

**Total: 21 files**

---

### Package `cpu-backend` (4 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `CpuNativeBackend.php` | class `CpuNativeBackend` implements Backend | core Contracts\Backend, RubixML\ML |
| 2 | `CpuNativeModel.php` | class `CpuNativeModel` implements Model | core Contracts\Model, Predictor |
| 3 | `CpuNativeTensor.php` | class `CpuNativeTensor` implements Tensor | core Contracts\Tensor |
| 4 | `Predictor.php` | interface `Predictor` — `isAvailable`/`predict`/`proba` seam over RubixML | (none) |
| 5 | `RubixMLAdapter.php` | class `RubixMLAdapter` implements Predictor — adapter to RubixML API | Predictor, RubixML\ML |

**Total: 5 files**

---

### Package `tokenizer` (6 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `TokenizerFactory.php` | class `TokenizerFactory` | core Contracts\Tokenizer, HuggingFaceTokenizer, PureBpeTokenizer |
| 2 | `HuggingFaceTokenizer.php` | class `HuggingFaceTokenizer` implements Tokenizer | core Contracts\Tokenizer, FFI binding to tokenizers-cpp |
| 3 | `PureBpeTokenizer.php` | class `PureBpeTokenizer` implements Tokenizer | core Contracts\Tokenizer, SpecialTokens |
| 4 | `PureWordPieceTokenizer.php` | class `PureWordPieceTokenizer` implements Tokenizer | core Contracts\Tokenizer, SpecialTokens |
| 5 | `SpecialTokens.php` | final class `SpecialTokens` — `extract()` bos/eos/unk/pad roles from config | (none) |
| 6 | `TokenizerLoader.php` | class `TokenizerLoader` — load tokenizer.json + type detection | core Enums\TokenizerType |

**Total: 6 files**

---

### Package `embedding` (7 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `Embedder.php` | class `Embedder` implements EmbedderContract | core Contracts\Embedder, core Contracts\Tokenizer, onnx-backend OnnxBackend |
| 2 | `EmbeddedModels.php` | class `EmbeddedModels` — built-in model registry | model-hub Hub |
| 3 | `Pooling/PoolingStrategy.php` | interface `PoolingStrategy` | (none) |
| 4 | `Pooling/ClsPooling.php` | class `ClsPooling` implements PoolingStrategy | Pooling\PoolingStrategy |
| 5 | `Pooling/MeanPooling.php` | class `MeanPooling` implements PoolingStrategy | Pooling\PoolingStrategy |
| 6 | `Pooling/EosPooling.php` | class `EosPooling` implements PoolingStrategy | Pooling\PoolingStrategy |
| 7 | `Pooling/MaxPooling.php` | class `MaxPooling` implements PoolingStrategy | Pooling\PoolingStrategy |

**Total: 7 files**

---

### Package `pipeline` (10 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `Pipeline.php` | class `Pipeline` implements Contracts\Pipeline | core Contracts\Pipeline, Contracts\Stage |
| 2 | `FiberPipeline.php` | class `FiberPipeline` extends Pipeline | Pipeline\Pipeline |
| 3 | `Stages/ChunkStage.php` | class `ChunkStage` implements Stage | core Contracts\Stage, core Contracts\Tokenizer |
| 4 | `Stages/TokenizeStage.php` | class `TokenizeStage` implements Stage | core Contracts\Stage, core Contracts\Tokenizer |
| 5 | `Stages/EmbedStage.php` | class `EmbedStage` implements Stage | core Contracts\Stage, core Contracts\Embedder |
| 6 | `Stages/NormalizeStage.php` | class `NormalizeStage` implements Stage | core Contracts\Stage |
| 7 | `Stages/StoreStage.php` | class `StoreStage` implements Stage | core Contracts\Stage, core Contracts\VectorStore |
| 8 | `Stages/ClassifyStage.php` | class `ClassifyStage` implements Stage | core Contracts\Stage, core Contracts\Backend |
| 9 | `Stages/FilterStage.php` | class `FilterStage` implements Stage | core Contracts\Stage |
| 10 | `Stages/TransformStage.php` | class `TransformStage` implements Stage | core Contracts\Stage |

**Total: 10 files**

---

### Package `model-hub` (14 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `Hub.php` | class `Hub` implements ModelHubContract | core Contracts\ModelHub |
| 2 | `HuggingFaceClient.php` | class `HuggingFaceClient` — native HF Hub API client | core Logger, RetryHandler |
| 3 | `CacheManager.php` | class `CacheManager` — LRU, cleanup, size | (none) |
| 4 | `ModelVerifier.php` | class `ModelVerifier` | Signature\*, Format\* |
| 5 | `ModelIntrospector.php` | class `ModelIntrospector` — static `introspect()` → ModelMetadata | Format\* |
| 6 | `Downloader.php` | class `Downloader` — progress, retries, resume | HuggingFaceClient |
| 7 | `StreamLoader.php` | class `StreamLoader` — mmap/stream for large models | (none) |
| 8 | `Format/FormatDetector.php` | class `FormatDetector` — format detection by magic bytes | (none) |
| 9 | `Format/AiArchive.php` | class `AiArchive` — read/write .ai | ext-zip |
| 10 | `Format/OnnxInspector.php` | class `OnnxInspector` — .onnx introspection | (none) |
| 11 | `Format/GgufInspector.php` | class `GgufInspector` — .gguf introspection | (none) |
| 12 | `Format/SafetensorsInspector.php` | final class `SafetensorsInspector` — `.safetensors` header introspection | (none) |
| 13 | `Signature/SignatureVerifier.php` | class `SignatureVerifier` — Ed25519 | ext-sodium |
| 14 | `Signature/Sha256Verifier.php` | class `Sha256Verifier` — SHA-256 | ext-hash |

**Total: 14 files**

---

### Package `vector` (10 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `Collection.php` | class `Collection` implements VectorStore | core Contracts\VectorStore, SQLiteStore |
| 2 | `CollectionManager.php` | class `CollectionManager` | Collection |
| 3 | `SQLiteStore.php` | class `SQLiteStore` — PDO wrapper | ext-pdo_sqlite or ext-sqlite3 |
| 4 | `SqliteVecExtension.php` | class `SqliteVecExtension` — FFI binding to sqlite-vec | PHP FFI |
| 5 | `BruteForceIndex.php` | class `BruteForceIndex` — fallback brute force search | (none) |
| 6 | `MetadataFilter.php` | class `MetadataFilter` — WHERE parser for JSON | (none) |
| 7 | `ExportImport.php` | class `ExportImport` — JSON/Parquet export/import | (none) |
| 8 | `PostgresStore.php` | class `PostgresStore` — PDO wrapper over PostgreSQL+pgvector | ext-pdo_pgsql |
| 9 | `PostgresCollection.php` | class `PostgresCollection` implements VectorStore (native ANN) | core Contracts\VectorStore, PostgresStore, MetadataFilter |
| 10 | `PostgresVecIndex.php` | class `PostgresVecIndex` — HNSW/IVFFlat indexes | PostgresStore |

**Total: 10 files**

---

### Package `ai` (14 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `AI.php` | final class `AI` — facade | AIFactory, BackendRegistry, TaskRouter, Observability, ModelPool |
| 2 | `AIFactory.php` | class `AIFactory` — factory | All backends, tokenizer, embedding, pipeline, model-hub, vector, LibraryResolver |
| 3 | `BackendRegistry.php` | class `BackendRegistry` — backend registry | core Contracts\Backend, core Enums\BackendType |
| 4 | `TaskRouter.php` | class `TaskRouter` — task routing | BackendRegistry |
| 5 | `StreamResponse.php` | class `StreamResponse` — PSR-7 streaming response | (PSR-7 interface) |
| 6 | `SharedMemoryManager.php` | class `SharedMemoryManager` implements SharedMemory — System V shared memory | ext-shmop |
| 7 | `ModelPool.php` | class `ModelPool` — model pool + LRU eviction + opt-in shared memory | core Contracts\Model, SharedMemory |
| 8 | `AsyncInference.php` | class `AsyncInference` — Fibers-based async | (none) |
| 9 | `NativeBinaryManager.php` | class `NativeBinaryManager` implements LibraryResolver — resolve/download binaries | core PlatformDetector |
| 10 | `Metrics.php` | class `Metrics` — Prometheus/StatsD metrics | (none) |
| 11 | `Profiler.php` | class `Profiler` — profiling/benchmarks | (none) |
| 12 | `Observability.php` | class `Observability` — metrics/profiling/logging wrapper for facade | Metrics, Profiler, core Logger |
| 13 | `LibraryResolver.php` | interface `LibraryResolver` — resolve native library path | (none) |
| 14 | `SharedMemory.php` | interface `SharedMemory` — shared memory abstraction for ModelPool | (none) |

**Total: 14 files**

---

### Package `dataframe` (6 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `DataFrame.php` | class `DataFrame` implements Contracts\DataFrame | core Contracts\DataFrame |
| 2 | `Column.php` | class `Column` — typed column | (none) |
| 3 | `IO/CsvReader.php` | class `CsvReader` | (none) |
| 4 | `IO/CsvWriter.php` | class `CsvWriter` | (none) |
| 5 | `IO/JsonReader.php` | class `JsonReader` | (none) |
| 6 | `IO/ParquetReader.php` | class `ParquetReader` | (none) |

**Total: 6 files**

---

### Package `laravel` (2 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `AIServiceProvider.php` | class `AIServiceProvider` extends ServiceProvider | ai\AI, illuminate/support |
| 2 | `Facades/AI.php` | class `AI` extends Facade | ai\AI, illuminate/support |

**Total: 2 files**

---

### Package `symfony` (3 files)

| # | Path | Contains | Depends on |
|---|---|---|---|
| 1 | `AIBundle.php` | class `AIBundle` extends Bundle | ai\AI, symfony/http-kernel |
| 2 | `DependencyInjection/Configuration.php` | class `Configuration` | symfony/config |
| 3 | `DependencyInjection/FerryAIExtension.php` | class `FerryAIExtension` | symfony/dependency-injection |

**Total: 3 files**

---

## SUMMARY STATISTICS

| Package | Files | Composer name |
|---|---|---|
| `core` | 43 | `ferry-ai/inference-core` |
| `tensor` | 2 | `ferry-ai/inference-tensor` |
| `onnx-backend` | 11 | `ferry-ai/inference-onnx-backend` |
| `ai` | 14 | `ferry-ai/inference-ai` |
| `llama-backend` | 21 | `ferry-ai/inference-llama-backend` |
| `tokenizer` | 6 | `ferry-ai/inference-tokenizer` |
| `embedding` | 7 | `ferry-ai/inference-embedding` |
| `vector` | 10 | `ferry-ai/inference-vector` |
| `model-hub` | 14 | `ferry-ai/inference-model-hub` |
| `pipeline` | 10 | `ferry-ai/inference-pipeline` |
| `cpu-backend` | 5 | `ferry-ai/inference-cpu-backend` |
| `dataframe` | 6 | `ferry-ai/inference-dataframe` |
| `laravel` | 2 | `ferry-ai/inference-laravel` |
| `symfony` | 3 | `ferry-ai/inference-symfony` |
| **TOTAL** | **154** | `ferry-ai/php-inference` (root) |

---

## FILE CREATION ORDER

Topological order: a file is created only when all its dependencies already exist.

```
 1. core/src/Enums/Device.php                              (0 dependencies)
 2. core/src/Enums/DType.php                               (0 dependencies)
 3. core/src/Enums/BackendType.php                         (0 dependencies)
 4. core/src/Enums/TokenizerType.php                       (0 dependencies)
 5. core/src/Enums/GraphOptimizationLevel.php              (0 dependencies)
 6. core/src/Enums/DistanceMetric.php                      (0 dependencies)
 7. core/src/Enums/IndexType.php                           (0 dependencies)
 8. core/src/Enums/QuantizationType.php                    (0 dependencies)
 9. core/src/ValueObjects/Shape.php                        (0 dependencies)
10. core/src/ValueObjects/ModelMetadata.php                (0 dependencies)
11. core/src/ValueObjects/ChatMessage.php                  (0 dependencies)
12. core/src/ValueObjects/SamplingParams.php               (0 dependencies)
13. core/src/ValueObjects/GenerationResult.php             (0 dependencies)
14. core/src/ValueObjects/EmbeddingResult.php              (0 dependencies)
15. core/src/ValueObjects/ClassificationResult.php         (0 dependencies)
16. core/src/Exception/FerryAIException.php                  (0 dependencies)
17. core/src/Exception/BackendNotAvailableException.php    (16)
18. core/src/Exception/ModelNotFoundException.php          (16)
19. core/src/Exception/ModelLoadException.php              (16)
20. core/src/Exception/InferenceException.php              (16)
21. core/src/Exception/ShapeMismatchException.php          (16, 9)
22. core/src/Exception/DeviceNotAvailableException.php     (16, 1)
23. core/src/Exception/TokenizerException.php              (16)
24. core/src/Exception/ConfigurationException.php          (16)
25. core/src/AIConfig.php                                  (1, 3)
26. core/src/Contracts/Stage.php                           (0 dependencies)
27. core/src/Contracts/Backend.php                         (1, 11)
28. core/src/Contracts/Model.php                           (1, 11)
29. core/src/Contracts/Tensor.php                          (1, 2, 9)
30. core/src/Contracts/Tokenizer.php                       (4)
31. core/src/Contracts/Embedder.php                        (0 dependencies)
32. core/src/Contracts/VectorStore.php                     (0 dependencies)
33. core/src/Contracts/Pipeline.php                        (26)
34. core/src/Contracts/ModelHub.php                        (11)
35. core/src/Contracts/DataFrame.php                       (29)
36. onnx-backend/src/Provider/ExecutionProvider.php        (1)
37. onnx-backend/src/Provider/CpuProvider.php              (36)
38. onnx-backend/src/OnnxTypeMapper.php                    (1, 12)
39. onnx-backend/src/Runtime/OnnxSession.php               (0 dependencies)
40. onnx-backend/src/Runtime/OnnxRuntimeInterface.php      (39)
41. onnx-backend/src/OnnxRuntimeFactory.php                (40)
42. onnx-backend/src/OnnxTensor.php                        (29)
43. onnx-backend/src/OnnxModel.php                         (28, 42)
44. onnx-backend/src/OnnxBackend.php                       (27, 43)
45. tensor/src/ArrayTensor.php                             (29)
46. tensor/src/TensorFactory.php                           (29, 45)
47. ai/src/BackendRegistry.php                             (27, 3)
48. ai/src/TaskRouter.php                                  (47)
49. ai/src/AIFactory.php                                   (all backends)
50. ai/src/AI.php                                          (48, 49)
```
