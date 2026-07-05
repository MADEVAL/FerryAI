# FerryAI — Полное дерево файлов

> Версия: 1.0  
> Назначение: пофайловая карта каждого пакета, namespace'ы, зависимости  
> Основание: TECHNICAL_SPECIFICATION.md разделы 9–21, INTERFACE_CONTRACTS.md  
> Правило: структура здесь — канон. Каждый файл, класс, интерфейс на своём месте.

---

## СТРУКТУРА МОНОРЕПО

```
php-inference/
├── composer.json                    # Root-метапакет
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
│   │       │   └── ConfigurationException.php
│   │       ├── AIConfig.php
│   │       ├── PlatformDetector.php     # Определение ОС/архитектуры (Фаза 4)
│   │       ├── Logger.php               # PSR-3 логгер (Фаза 4)
│   │       └── RetryHandler.php         # Retry-логика (Фаза 4)
│   ├── tensor/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── TensorFactory.php
│   │       ├── BackedTensor.php         # Реализация Tensor над бэкенд-тензором
│   │       └── ArrayTensor.php          # Pure-PHP реализация для CPU-fallback
│   ├── onnx-backend/
│   │   ├── composer.json
│   │   └── src/
│   │       ├── OnnxBackend.php
│   │       ├── OnnxModel.php
│   │       ├── OnnxTensor.php
│   │       ├── Provider/
│   │       │   ├── ExecutionProvider.php
│   │       │   ├── CpuProvider.php
│   │       │   ├── CudaProvider.php
│   │       │   ├── TensorRtProvider.php
│   │       │   ├── CoreMlProvider.php
│   │       │   ├── DirectMlProvider.php
│   │       │   ├── OpenVinoProvider.php
│   │       │   └── RocmProvider.php
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
│   │       │   ├── LlamaCpp.php
│   │       │   ├── LlamaContext.php
│   │       │   └── LlamaBatch.php
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
│   │       │   └── JsonSchemaConverter.php
│   │       ├── LlamaContextParams.php     # Value Object для llama_context_params
│   │       ├── LlamaModelParams.php       # Value Object для llama_model_params
│   │       └── ChatFormatter.php          # ChatML → LLM формат
│   ├── cpu-backend/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── CpuNativeBackend.php
│   │       ├── CpuNativeModel.php
│   │       ├── CpuNativeTensor.php
│   │       └── RubixMLAdapter.php        # Адаптер к RubixML/ML
│   ├── tokenizer/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── TokenizerFactory.php
│   │       ├── HuggingFaceTokenizer.php   # Приоритетный: биндинг к tokenizers-cpp через FFI
│   │       ├── PureBpeTokenizer.php       # Fallback: Pure PHP BPE
│   │       ├── PureWordPieceTokenizer.php # Fallback: Pure PHP WordPiece
│   │       ├── SpecialTokens.php            # Bos/eos/unk/pad role extraction
│   │       └── TokenizerLoader.php        # Загрузка tokenizer.json + определение типа
│   ├── embedding/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── Embedder.php               # Основная реализация
│   │       ├── EmbeddedModels.php         # Встроенные модели (all-MiniLM-L6-v2 и др.)
│   │       └── Pooling/
│   │           ├── PoolingStrategy.php     # Интерфейс
│   │           ├── ClsPooling.php
│   │           ├── MeanPooling.php
│   │           ├── EosPooling.php
│   │           └── MaxPooling.php
│   ├── pipeline/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── Pipeline.php               # Реализация Contracts\Pipeline
│   │       ├── FiberPipeline.php          # Fibers-based реализация
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
│   │       ├── Hub.php                    # Реализация Contracts\ModelHub
│   │       ├── HuggingFaceClient.php      # HTTP-клиент для HuggingFace API
│   │       ├── CacheManager.php           # LRU-кэш, очистка, размер
│   │       ├── ModelVerifier.php          # SHA-256, Ed25519, magic bytes
│   │       ├── ModelIntrospector.php      # Чтение метаданных без загрузки
│   │       ├── Downloader.php             # Загрузка с прогрессом, ретраями, resume
│   │       ├── StreamLoader.php           # Потоковая/mmap загрузка больших моделей (Фаза 4)
│   │       ├── Format/
│   │       │   ├── FormatDetector.php     # Определение формата (.onnx, .gguf, .safetensors, .ai)
│   │       │   ├── AiArchive.php          # Чтение/запись .ai архивов
│   │       │   ├── OnnxInspector.php      # Интроспекция .onnx
│   │       │   └── GgufInspector.php      # Интроспекция .gguf
│   │       └── Signature/
│   │           ├── SignatureVerifier.php  # Ed25519 верификация
│   │           └── Sha256Verifier.php     # SHA-256 верификация
│   ├── vector/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── Collection.php             # Реализация Contracts\VectorStore
│   │       ├── CollectionManager.php      # Управление коллекциями (создание, удаление)
│   │       ├── SQLiteStore.php            # PDO-обёртка над SQLite
│   │       ├── SqliteVecExtension.php     # FFI-биндинг к sqlite-vec extension
│   │       ├── PostgresStore.php          # PDO-обёртка над PostgreSQL + pgvector
│   │       ├── PostgresCollection.php     # Contracts\VectorStore поверх pgvector (native ANN)
│   │       ├── PostgresVecIndex.php       # HNSW/IVFFlat индексы pgvector
│   │       ├── BruteForceIndex.php        # Fallback brute force (PHP)
│   │       ├── MetadataFilter.php         # WHERE-подобный парсер фильтров
│   │       └── ExportImport.php           # Экспорт/Импорт в JSON/Parquet
│   ├── dataframe/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── DataFrame.php              # Реализация Contracts\DataFrame
│   │       ├── Column.php                 # Типизированная колонка
│   │       └── IO/
│   │           ├── CsvReader.php
│   │           ├── CsvWriter.php
│   │           ├── JsonReader.php
│   │           └── ParquetReader.php
│   ├── ai/
│   │   ├── composer.json
│   │   ├── phpunit.xml.dist
│   │   └── src/
│   │       ├── AI.php                     # Фасад
│   │       ├── AIFactory.php              # Фабрика
│   │       ├── BackendRegistry.php        # Реестр бэкендов
│   │       ├── TaskRouter.php             # Роутинг задач по бэкендам
│   │       ├── StreamResponse.php        # Готовый HTTP streaming response (заглушка Фаза 1 → доработка Фаза 4)
│   │       ├── SharedMemoryManager.php    # System V shared memory (Фаза 4)
│   │       ├── ModelPool.php              # Пул предзагруженных моделей (Фаза 4)
│   │       ├── AsyncInference.php         # Fibers-based асинхронный инференс (Фаза 4)
│   │       ├── NativeBinaryManager.php    # Автоскачивание нативных бинарников (Фаза 4)
│   │       ├── Metrics.php                # Prometheus/StatsD метрики (Фаза 4)
│   │       └── Profiler.php               # Профилирование и бенчмарки (Фаза 4)
│   ├── laravel/                           # Интеграция с Laravel
│   │   ├── composer.json
│   │   └── src/
│   │       ├── AIServiceProvider.php
│   │       └── Facades/
│   │           └── AI.php                 # Laravel Facade
│   └── symfony/                           # Интеграция с Symfony
│       ├── composer.json
│       └── src/
│           ├── AIBundle.php
│           └── DependencyInjection/
│               ├── Configuration.php
│               └── FerryAIExtension.php
```

---

## ФАЙЛОВЫЙ УКАЗАТЕЛЬ ПО ПАКЕТАМ

### Пакет `core` (38 файлов)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `Contracts/Backend.php` | Интерфейс `Backend` | Enums\Device, ValueObjects\ModelMetadata |
| 2 | `Contracts/Model.php` | Интерфейс `Model` | Enums\Device, ValueObjects\ModelMetadata |
| 3 | `Contracts/Tensor.php` | Интерфейс `Tensor` (extends ArrayAccess, Countable, JsonSerializable) | Enums\Device, Enums\DType, ValueObjects\Shape |
| 4 | `Contracts/Tokenizer.php` | Интерфейс `Tokenizer` | Enums\TokenizerType |
| 5 | `Contracts/Embedder.php` | Интерфейс `Embedder` | (нет) |
| 6 | `Contracts/VectorStore.php` | Интерфейс `VectorStore` | (нет) |
| 7 | `Contracts/Pipeline.php` | Интерфейс `Pipeline` | Contracts\Stage |
| 8 | `Contracts/Stage.php` | Интерфейс `Stage` | (нет) |
| 9 | `Contracts/ModelHub.php` | Интерфейс `ModelHub` | ValueObjects\ModelMetadata |
| 10 | `Contracts/DataFrame.php` | Интерфейс `DataFrame` (extends Iterator, Countable) | Contracts\Tensor |
| 11 | `Enums/Device.php` | Enum `Device` (CPU, CUDA, ROCM, METAL, VULKAN, DIRECTML, OPENVINO, OPENCL, AUTO) | (нет) |
| 12 | `Enums/DType.php` | Enum `DType` (Float32, Float16, Int32, Int64, String) | (нет) |
| 13 | `Enums/BackendType.php` | Enum `BackendType` (Onnx, Llama, CpuNative) | (нет) |
| 14 | `Enums/TokenizerType.php` | Enum `TokenizerType` (BPE, WordPiece, SentencePiece, Unigram) | (нет) |
| 15 | `Enums/GraphOptimizationLevel.php` | Enum `GraphOptimizationLevel` | (нет) |
| 16 | `Enums/DistanceMetric.php` | Enum `DistanceMetric` (COSINE, EUCLIDEAN, DOT) | (нет) |
| 17 | `Enums/IndexType.php` | Enum `IndexType` (HNSW, IVF, FLAT) | (нет) |
| 18 | `Enums/QuantizationType.php` | Enum `QuantizationType` (FLOAT32, FLOAT16, INT8, BINARY) | (нет) |
| 19 | `ValueObjects/Shape.php` | readonly class `Shape` | (нет) |
| 20 | `ValueObjects/ModelMetadata.php` | readonly class `ModelMetadata` | (нет) |
| 21 | `ValueObjects/ChatMessage.php` | readonly class `ChatMessage` | (нет) |
| 22 | `ValueObjects/SamplingParams.php` | readonly class `SamplingParams` | (нет) |
| 23 | `ValueObjects/GenerationResult.php` | readonly class `GenerationResult` | (нет) |
| 24 | `ValueObjects/EmbeddingResult.php` | readonly class `EmbeddingResult` | (нет) |
| 25 | `ValueObjects/ClassificationResult.php` | readonly class `ClassificationResult` | (нет) |
| 26 | `Exception/FerryAIException.php` | Базовый класс исключений (extends \RuntimeException) | (нет) |
| 27 | `Exception/BackendNotAvailableException.php` | Исключение | Exception\FerryAIException |
| 28 | `Exception/ModelNotFoundException.php` | Исключение | Exception\FerryAIException |
| 29 | `Exception/ModelLoadException.php` | Исключение | Exception\FerryAIException |
| 30 | `Exception/InferenceException.php` | Исключение | Exception\FerryAIException |
| 31 | `Exception/ShapeMismatchException.php` | Исключение | Exception\FerryAIException, ValueObjects\Shape |
| 32 | `Exception/DeviceNotAvailableException.php` | Исключение | Exception\FerryAIException, Enums\Device |
| 33 | `Exception/TokenizerException.php` | Исключение | Exception\FerryAIException |
| 34 | `Exception/ConfigurationException.php` | Исключение | Exception\FerryAIException |
| 35 | `AIConfig.php` | final class `AIConfig` (implements ArrayAccess) | Enums\Device, Enums\BackendType |
| 36 | `PlatformDetector.php` | class `PlatformDetector` — OS/arch detection (Фаза 4) | (нет) |
| 37 | `Logger.php` | class `Logger` — PSR-3 compatible (Фаза 4) | (нет) |
| 38 | `RetryHandler.php` | class `RetryHandler` — retry logic (Фаза 4) | (нет) |

**Всего: 38 файлов**

---

### Пакет `tensor` (3 файла)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `TensorFactory.php` | final class `TensorFactory` | core Contracts\Tensor, Enums\Device, Enums\DType, ValueObjects\Shape |
| 2 | `BackedTensor.php` | class `BackedTensor` implements Tensor | core Contracts\Tensor (все зависимости интерфейса) |
| 3 | `ArrayTensor.php` | class `ArrayTensor` implements Tensor | core Contracts\Tensor (Pure PHP fallback, без FFI) |

**Всего: 3 файла**

---

### Пакет `onnx-backend` (12 файлов)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `OnnxBackend.php` | class `OnnxBackend` implements Backend | core Contracts\Backend, phpmlkit/onnxruntime |
| 2 | `OnnxModel.php` | class `OnnxModel` implements Model | core Contracts\Model |
| 3 | `OnnxTensor.php` | class `OnnxTensor` implements Tensor | core Contracts\Tensor, phpmlkit/onnxruntime (OrtValue) |
| 4 | `OnnxRuntimeFactory.php` | class `OnnxRuntimeFactory` | phpmlkit/onnxruntime |
| 5 | `Provider/ExecutionProvider.php` | interface `ExecutionProvider` | core Enums\Device |
| 6 | `Provider/CpuProvider.php` | class `CpuProvider` implements ExecutionProvider | Provider\ExecutionProvider |
| 7 | `Provider/CudaProvider.php` | class `CudaProvider` implements ExecutionProvider | Provider\ExecutionProvider |
| 8 | `Provider/TensorRtProvider.php` | class `TensorRtProvider` implements ExecutionProvider | Provider\ExecutionProvider |
| 9 | `Provider/CoreMlProvider.php` | class `CoreMlProvider` implements ExecutionProvider | Provider\ExecutionProvider |
| 10 | `Provider/DirectMlProvider.php` | class `DirectMlProvider` implements ExecutionProvider (планируется — phpmlkit не отдаёт DirectML) | Provider\ExecutionProvider |
| 11 | `Provider/OpenVinoProvider.php` | class `OpenVinoProvider` implements ExecutionProvider (Фаза 4, планируется) | Provider\ExecutionProvider |
| 12 | `Provider/RocmProvider.php` | class `RocmProvider` implements ExecutionProvider (Фаза 4, планируется) | Provider\ExecutionProvider |

**Всего: 12 файлов**

---

### Пакет `llama-backend` (16 файлов)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `LlamaBackend.php` | class `LlamaBackend` implements Backend | core Contracts\Backend, FFI\LlamaCpp |
| 2 | `LlamaModel.php` | class `LlamaModel` implements Model | core Contracts\Model, FFI\LlamaContext |
| 3 | `FFI/LlamaCpp.php` | class `LlamaCpp` — FFI-определения C API llama.cpp | PHP FFI |
| 4 | `FFI/LlamaContext.php` | class `LlamaContext` — обёртка llama_context | FFI\LlamaCpp |
| 5 | `FFI/LlamaBatch.php` | class `LlamaBatch` — обёртка llama_batch | FFI\LlamaCpp |
| 6 | `Sampling/Sampler.php` | interface `Sampler` | core ValueObjects\SamplingParams |
| 7 | `Sampling/GreedySampler.php` | class `GreedySampler` implements Sampler | Sampling\Sampler |
| 8 | `Sampling/TopPSampler.php` | class `TopPSampler` implements Sampler | Sampling\Sampler |
| 9 | `Sampling/TopKSampler.php` | class `TopKSampler` implements Sampler | Sampling\Sampler |
| 10 | `Sampling/GrammarSampler.php` | class `GrammarSampler` implements Sampler | Sampling\Sampler, Grammar\GbnfGrammar |
| 11 | `Sampling/SamplerFactory.php` | class `SamplerFactory` | Sampling\Sampler |
| 12 | `Grammar/GbnfGrammar.php` | final readonly class `GbnfGrammar` | (нет) |
| 13 | `Grammar/JsonSchemaConverter.php` | class `JsonSchemaConverter` | Grammar\GbnfGrammar |
| 14 | `LlamaContextParams.php` | readonly class `LlamaContextParams` — value object | (нет) |
| 15 | `LlamaModelParams.php` | readonly class `LlamaModelParams` — value object | (нет) |
| 16 | `ChatFormatter.php` | class `ChatFormatter` — ChatML → llama формат | core ValueObjects\ChatMessage |

**Всего: 16 файлов**

---

### Пакет `cpu-backend` (4 файла)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `CpuNativeBackend.php` | class `CpuNativeBackend` implements Backend | core Contracts\Backend, RubixML\ML |
| 2 | `CpuNativeModel.php` | class `CpuNativeModel` implements Model | core Contracts\Model, RubixML\ML |
| 3 | `CpuNativeTensor.php` | class `CpuNativeTensor` implements Tensor | core Contracts\Tensor, RubixML\Tensor |
| 4 | `RubixMLAdapter.php` | class `RubixMLAdapter` — адаптер к API RubixML | RubixML\ML |

**Всего: 4 файла**

---

### Пакет `tokenizer` (5 файлов)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `TokenizerFactory.php` | class `TokenizerFactory` | core Contracts\Tokenizer, HuggingFaceTokenizer, PureBpeTokenizer |
| 2 | `HuggingFaceTokenizer.php` | class `HuggingFaceTokenizer` implements Tokenizer | core Contracts\Tokenizer, FFI биндинг к tokenizers-cpp |
| 3 | `PureBpeTokenizer.php` | class `PureBpeTokenizer` implements Tokenizer | core Contracts\Tokenizer |
| 4 | `PureWordPieceTokenizer.php` | class `PureWordPieceTokenizer` implements Tokenizer | core Contracts\Tokenizer |
| 5 | `TokenizerLoader.php` | class `TokenizerLoader` — загрузка tokenizer.json | core Enums\TokenizerType |

**Всего: 5 файлов**

---

### Пакет `embedding` (7 файлов)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `Embedder.php` | class `Embedder` implements EmbedderContract | core Contracts\Embedder, core Contracts\Tokenizer, onnx-backend OnnxBackend |
| 2 | `EmbeddedModels.php` | class `EmbeddedModels` — реестр встроенных моделей | model-hub Hub |
| 3 | `Pooling/PoolingStrategy.php` | interface `PoolingStrategy` | (нет) |
| 4 | `Pooling/ClsPooling.php` | class `ClsPooling` implements PoolingStrategy | Pooling\PoolingStrategy |
| 5 | `Pooling/MeanPooling.php` | class `MeanPooling` implements PoolingStrategy | Pooling\PoolingStrategy |
| 6 | `Pooling/EosPooling.php` | class `EosPooling` implements PoolingStrategy | Pooling\PoolingStrategy |
| 7 | `Pooling/MaxPooling.php` | class `MaxPooling` implements PoolingStrategy | Pooling\PoolingStrategy |

**Всего: 7 файлов**

---

### Пакет `pipeline` (10 файлов)

| # | Путь | Содержит | Зависит от |
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

**Всего: 10 файлов**

---

### Пакет `model-hub` (13 файлов)

> Примечание: 12 файлов создаются в Фазе 3; `StreamLoader.php` — в Фазе 4 (потоковая загрузка >18GB моделей, production-доработка).

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `Hub.php` | class `Hub` implements ModelHubContract | core Contracts\ModelHub |
| 2 | `HuggingFaceClient.php` | class `HuggingFaceClient` | codewithkyrian/huggingface-php |
| 3 | `CacheManager.php` | class `CacheManager` — LRU, очистка, размер | (нет) |
| 4 | `ModelVerifier.php` | class `ModelVerifier` | Signature\*, Format\* |
| 5 | `ModelIntrospector.php` | class `ModelIntrospector` | Format\* |
| 6 | `Downloader.php` | class `Downloader` — прогресс, ретраи, resume | HuggingFaceClient |
| 7 | `StreamLoader.php` | class `StreamLoader` — mmap/stream для >18GB моделей (Фаза 4) | (нет) |
| 8 | `Format/FormatDetector.php` | class `FormatDetector` — определение формата по magic bytes | (нет) |
| 9 | `Format/AiArchive.php` | class `AiArchive` — чтение/запись .ai | ext-zip |
| 10 | `Format/OnnxInspector.php` | class `OnnxInspector` — интроспекция .onnx | (нет) |
| 11 | `Format/GgufInspector.php` | class `GgufInspector` — интроспекция .gguf | (нет) |
| 12 | `Signature/SignatureVerifier.php` | class `SignatureVerifier` — Ed25519 | ext-sodium |
| 13 | `Signature/Sha256Verifier.php` | class `Sha256Verifier` — SHA-256 | ext-hash |

**Всего: 13 файлов**

---

### Пакет `vector` (10 файлов)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `Collection.php` | class `Collection` implements VectorStore | core Contracts\VectorStore, SQLiteStore |
| 2 | `CollectionManager.php` | class `CollectionManager` | Collection |
| 3 | `SQLiteStore.php` | class `SQLiteStore` — PDO-обёртка | ext-pdo_sqlite или ext-sqlite3 |
| 4 | `SqliteVecExtension.php` | class `SqliteVecExtension` — FFI-биндинг к sqlite-vec | PHP FFI |
| 5 | `BruteForceIndex.php` | class `BruteForceIndex` — fallback brute force поиск | (нет) |
| 6 | `MetadataFilter.php` | class `MetadataFilter` — WHERE-парсер для JSON | (нет) |
| 7 | `ExportImport.php` | class `ExportImport` — JSON/Parquet экспорт/импорт | (нет) |
| 8 | `PostgresStore.php` | class `PostgresStore` — PDO-обёртка над PostgreSQL+pgvector | ext-pdo_pgsql |
| 9 | `PostgresCollection.php` | class `PostgresCollection` implements VectorStore (native ANN) | core Contracts\VectorStore, PostgresStore, MetadataFilter |
| 10 | `PostgresVecIndex.php` | class `PostgresVecIndex` — HNSW/IVFFlat индексы | PostgresStore |

**Всего: 10 файлов**

---

### Пакет `ai` (11 файлов)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `AI.php` | final class `AI` — фасад | AIFactory, BackendRegistry, TaskRouter |
| 2 | `AIFactory.php` | class `AIFactory` — фабрика | Все бэкенды, токенизатор, embedding, pipeline, model-hub, vector |
| 3 | `BackendRegistry.php` | class `BackendRegistry` — реестр бэкендов | core Contracts\Backend, core Enums\BackendType |
| 4 | `TaskRouter.php` | class `TaskRouter` — роутинг задач | BackendRegistry |
| 5 | `StreamResponse.php` | class `StreamResponse` — PSR-7 streaming response (заглушка Фаза 1 → доработка Фаза 4) | (PSR-7 интерфейс) |
| 6 | `SharedMemoryManager.php` | class `SharedMemoryManager` — System V shared memory (Фаза 4) | ext-shmop |
| 7 | `ModelPool.php` | class `ModelPool` — пул предзагруженных моделей (Фаза 4) | core Contracts\Model |
| 8 | `AsyncInference.php` | class `AsyncInference` — Fibers-based async (Фаза 4) | (нет) |
| 9 | `NativeBinaryManager.php` | class `NativeBinaryManager` — автоскачивание бинарников (Фаза 4) | core PlatformDetector |
| 10 | `Metrics.php` | class `Metrics` — Prometheus/StatsD метрики (Фаза 4) | (нет) |
| 11 | `Profiler.php` | class `Profiler` — профилирование/бенчмарки (Фаза 4) | (нет) |

**Всего: 11 файлов**

---

### Пакет `dataframe` (отложен) (6 файлов)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `DataFrame.php` | class `DataFrame` implements Contracts\DataFrame | core Contracts\DataFrame |
| 2 | `Column.php` | class `Column` — типизированная колонка | (нет) |
| 3 | `IO/CsvReader.php` | class `CsvReader` | (нет) |
| 4 | `IO/CsvWriter.php` | class `CsvWriter` | (нет) |
| 5 | `IO/JsonReader.php` | class `JsonReader` | (нет) |
| 6 | `IO/ParquetReader.php` | class `ParquetReader` | (нет) |

**Всего: 6 файлов**

---

### Пакет `laravel` (2 файла)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `AIServiceProvider.php` | class `AIServiceProvider` extends ServiceProvider | ai\AI, illuminate/support |
| 2 | `Facades/AI.php` | class `AI` extends Facade | ai\AI, illuminate/support |

**Всего: 2 файла**

---

### Пакет `symfony` (3 файла)

| # | Путь | Содержит | Зависит от |
|---|---|---|---|
| 1 | `AIBundle.php` | class `AIBundle` extends Bundle | ai\AI, symfony/http-kernel |
| 2 | `DependencyInjection/Configuration.php` | class `Configuration` | symfony/config |
| 3 | `DependencyInjection/FerryAIExtension.php` | class `FerryAIExtension` | symfony/dependency-injection |

**Всего: 3 файла**

---

## СВОДНАЯ СТАТИСТИКА

| Пакет | Файлов | Статус | Composer-имя |
|---|---|---|---|
| `core` | 38 | **Фаза 1** | `ferry-ai/inference-core` |
| `tensor` | 3 | **Фаза 1** | `ferry-ai/inference-tensor` |
| `onnx-backend` | 12 | **Фаза 1** (+2 провайдера Фаза 4) | `ferry-ai/inference-onnx-backend` |
| `ai` | 11 | **Фаза 1** | `ferry-ai/inference-ai` |
| `llama-backend` | 16 | **Фаза 2** | `ferry-ai/inference-llama-backend` |
| `tokenizer` | 5 | **Фаза 2** | `ferry-ai/inference-tokenizer` |
| `embedding` | 7 | **Фаза 3** | `ferry-ai/inference-embedding` |
| `vector` | 7 | **Фаза 3** | `ferry-ai/inference-vector` |
| `model-hub` | 13 | **Фаза 3** | `ferry-ai/inference-model-hub` |
| `pipeline` | 10 | **Фаза 3** | `ferry-ai/inference-pipeline` |
| `cpu-backend` | 4 | **Фаза 3** | `ferry-ai/inference-cpu-backend` |
| `dataframe` | 6 | **Фаза 4** | `ferry-ai/inference-dataframe` |
| `laravel` | 2 | **Фаза 4** | `ferry-ai/inference-laravel` |
| `symfony` | 3 | **Фаза 4** | `ferry-ai/inference-symfony` |
| **ИТОГО** | **137** | | `ferry-ai/php-inference` (root) |

> **Примечание о фазах отдельных файлов.** Столбец «Статус» указывает основную фазу пакета.
> Часть файлов внутри пакетов ранних фаз реализуется в **Фазе 4** (production-доработки):
> - `core`: `PlatformDetector.php`, `Logger.php`, `RetryHandler.php`
> - `onnx-backend`: `Provider/OpenVinoProvider.php` (Intel), `Provider/RocmProvider.php` (AMD) — Фаза 4. Провайдеры DirectML/OpenVINO/ROCm **планируются**: `phpmlkit/onnxruntime` их не отдаёт (поддержаны CPU/CUDA/CoreML/TensorRT), потребуют собственного FFI.
> - `ai`: `SharedMemoryManager.php`, `ModelPool.php`, `AsyncInference.php`, `NativeBinaryManager.php`, `Metrics.php`, `Profiler.php` (`StreamResponse.php` создаётся заглушкой в Фазе 1 и дорабатывается в Фазе 4)
> - `model-hub`: `StreamLoader.php`
>
> **Порядок неизменен: сперва ядро платформы (фазы 1–3).** Пакеты `dataframe`, `laravel`, `symfony`
> создаются **последними** (Фаза 4) и только после того, как ядро готово и стабильно.

---

## ПОРЯДОК СОЗДАНИЯ ФАЙЛОВ (Фаза 1 MVP)

Топологический порядок: файл создаётся только когда все его зависимости уже существуют.

```
 1. core/src/Enums/Device.php                              (0 зависимостей)
 2. core/src/Enums/DType.php                               (0 зависимостей)
 3. core/src/Enums/BackendType.php                         (0 зависимостей)
 4. core/src/Enums/TokenizerType.php                       (0 зависимостей)
 5. core/src/Enums/GraphOptimizationLevel.php              (0 зависимостей)
 6. core/src/Enums/DistanceMetric.php                      (0 зависимостей)
 7. core/src/Enums/IndexType.php                           (0 зависимостей)
 8. core/src/Enums/QuantizationType.php                    (0 зависимостей)
 9. core/src/ValueObjects/Shape.php                        (0 зависимостей)
10. core/src/ValueObjects/ModelMetadata.php                (0 зависимостей)
11. core/src/ValueObjects/ChatMessage.php                  (0 зависимостей)
12. core/src/ValueObjects/SamplingParams.php               (0 зависимостей)
13. core/src/ValueObjects/GenerationResult.php             (0 зависимостей)
14. core/src/ValueObjects/EmbeddingResult.php              (0 зависимостей)
15. core/src/ValueObjects/ClassificationResult.php         (0 зависимостей)
16. core/src/Exception/FerryAIException.php                  (0 зависимостей)
17. core/src/Exception/BackendNotAvailableException.php    (16)
18. core/src/Exception/ModelNotFoundException.php          (16)
19. core/src/Exception/ModelLoadException.php              (16)
20. core/src/Exception/InferenceException.php              (16)
21. core/src/Exception/ShapeMismatchException.php          (16, 9)
22. core/src/Exception/DeviceNotAvailableException.php     (16, 1)
23. core/src/Exception/TokenizerException.php              (16)
24. core/src/Exception/ConfigurationException.php          (16)
25. core/src/AIConfig.php                                  (1, 3)
26. core/src/Contracts/Stage.php                           (0 зависимостей)
27. core/src/Contracts/Backend.php                         (1, 11)
28. core/src/Contracts/Model.php                           (1, 11)
29. core/src/Contracts/Tensor.php                          (1, 2, 9)
30. core/src/Contracts/Tokenizer.php                       (4)
31. core/src/Contracts/Embedder.php                        (0 зависимостей)
32. core/src/Contracts/VectorStore.php                     (0 зависимостей)
33. core/src/Contracts/Pipeline.php                        (26)
34. core/src/Contracts/ModelHub.php                        (11)
35. core/src/Contracts/DataFrame.php                       (29)
36. onnx-backend/src/Provider/ExecutionProvider.php        (1)
37. onnx-backend/src/Provider/CpuProvider.php              (36)
38. onnx-backend/src/Provider/CudaProvider.php             (36)
39. onnx-backend/src/Provider/TensorRtProvider.php         (36)
40. onnx-backend/src/Provider/CoreMlProvider.php           (36)
41. onnx-backend/src/Provider/DirectMlProvider.php         (36)
42. onnx-backend/src/OnnxRuntimeFactory.php                (37)
43. onnx-backend/src/OnnxTensor.php                        (29)
44. onnx-backend/src/OnnxModel.php                         (28, 43)
45. onnx-backend/src/OnnxBackend.php                       (27, 44)
46. tensor/src/ArrayTensor.php                             (29)
47. tensor/src/BackedTensor.php                            (29)
48. tensor/src/TensorFactory.php                           (29, 46, 47)
49. ai/src/BackendRegistry.php                             (27, 3)
50. ai/src/TaskRouter.php                                  (49)
51. ai/src/AIFactory.php                                   (все бэкенды фазы 1)
52. ai/src/AI.php                                          (50, 51)
```

---

> **Документ является неотъемлемой частью технического задания. Каждый файл должен быть создан строго в указанном месте с указанным содержимым. Отклонения от файлового дерева требуют пересмотра спецификации.**
