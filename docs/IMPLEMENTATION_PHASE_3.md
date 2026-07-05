# FerryAI — Phase 3 Build Record (Ecosystem)

> **STATUS: COMPLETED.** All 42 steps implemented. 469 tests. Preserved as build record.

---

> Версия: 1.0  
> Цель: полная экосистема для AI-разработки на PHP  
> Длительность: 3–4 месяца  
> Пакеты: embedding, vector, model-hub, pipeline, cpu-backend  
> Новых файлов: 40 (+ 2 обновления существующих)  
> Коммит-стратегия: 1 коммит = 1 файл

---

## 0. ПРЕДВАРИТЕЛЬНЫЕ УСЛОВИЯ

- [ ] Фаза 2 завершена и проходит интеграционный тест
- [ ] Все пакеты Фаз 1 и 2 установлены и работают
- [ ] Прочитаны: `FILE_TREE.md` (пакеты embedding, vector, model-hub, pipeline, cpu-backend), `INTERFACE_CONTRACTS.md`

---

## ПАКЕТ `embedding` (7 файлов)

---

## ШАГ 76: embedding/src/Pooling/PoolingStrategy.php

**Назначение:** Интерфейс стратегии пулинга.

**Зависимости:** нет.

**Детали реализации:**
- `interface PoolingStrategy`
- `pool(array $hiddenStates, array $attentionMask = null): array`
  - `$hiddenStates` — [seq_len, hidden_dim] или [batch, seq_len, hidden_dim]
  - Возвращает вектор [hidden_dim] или [batch, hidden_dim]

---

## ШАГ 77–80: Реализации Pooling

### ШАГ 77: embedding/src/Pooling/ClsPooling.php

**Зависимости:** Шаг 76.

**Детали реализации:**
- `class ClsPooling implements PoolingStrategy`
- `pool(array $hiddenStates, ...)` — возвращает первый токен (CLS)

### ШАГ 78: embedding/src/Pooling/MeanPooling.php

**Зависимости:** Шаг 76.

**Детали реализации:**
- `class MeanPooling implements PoolingStrategy`
- `pool(array $hiddenStates, ?array $attentionMask = null)`
  - Усредняет все токены
  - Если передан attention_mask — учитывает его (игнорирует padding)

### ШАГ 79: embedding/src/Pooling/EosPooling.php

**Зависимости:** Шаг 76.

**Детали реализации:**
- `class EosPooling implements PoolingStrategy`
- `pool(array $hiddenStates, ...)` — возвращает последний токен (EOS)

### ШАГ 80: embedding/src/Pooling/MaxPooling.php

**Зависимости:** Шаг 76.

**Детали реализации:**
- `class MaxPooling implements PoolingStrategy`
- `pool(array $hiddenStates, ...)` — max pooling по оси seq_len

---

## ШАГ 81: embedding/src/EmbeddedModels.php

**Назначение:** Реестр встроенных моделей для эмбеддингов.

**Зависимости:** нет.

**Детали реализации:**
- `class EmbeddedModels`
- Статический массив моделей:
  - `all-MiniLM-L6-v2` → HF ID: `sentence-transformers/all-MiniLM-L6-v2`, dim=384, pooling=mean
  - `all-mpnet-base-v2` → HF ID: `sentence-transformers/all-mpnet-base-v2`, dim=768, pooling=mean
  - `multilingual-e5-small` → HF ID: `intfloat/multilingual-e5-small`, dim=384, pooling=mean
  - `bge-small-en` → HF ID: `BAAI/bge-small-en-v1.5`, dim=384, pooling=cls
- `list(): array` — возвращает все модели
- `get(string $name): ?array` — информация о конкретной модели
- `isEmbedded(string $name): bool`

---

## ШАГ 82: embedding/src/Embedder.php

**Назначение:** Основная реализация эмбеддера.

**Зависимости:** core Contracts\Embedder, onnx-backend OnnxBackend, tokenizer Tokenizer, Шаг 76-81.

**Детали реализации:**
- `class Embedder implements EmbedderContract`
- Конструктор:
  - `string $modelName` — имя модели (встроенная или путь к .onnx)
  - `string $pooling = 'mean'` — стратегия пулинга
  - `bool $normalize = true` — L2-нормализация
- При создании:
  - Если встроенная модель → скачать через Model Hub (или использовать локальный кэш)
  - Загрузить ONNX-модель
  - Загрузить токенизатор
- `embed(string $text): array`
  - Токенизирует текст
  - Создаёт входные тензоры (input_ids, attention_mask)
  - Выполняет инференс через OnnxBackend
  - Применяет pooling (извлекает эмбеддинг из скрытых состояний)
  - Нормализует (если включено)
  - Возвращает float[]
- `embedBatch(array $texts): array`
  - Пакетная токенизация
  - Один вызов модели для всего батча
  - Pooling + нормализация для каждого
- `dimension(): int` — размерность эмбеддинга
- `normalize(array $vector): array` — L2 норма: деление на sqrt(sum(v^2))
- `cosineSimilarity(array $a, array $b): float` — dot product (если нормализованы) или dot(a,b)/(|a|*|b|)
- `modelName(): string`

**Критерий приёмки:**
- `embed('hello')` возвращает массив float длиной 384 (для MiniLM)
- `embedBatch(['a', 'b'])` возвращает 2 вектора
- `cosineSimilarity(embed('hi'), embed('hello'))` > 0.5
- Нормализованный вектор имеет длину 1.0

---

## ПАКЕТ `vector` (7 файлов)

---

## ШАГ 83: vector/src/SQLiteStore.php

**Назначение:** PDO-обёртка над SQLite для хранения векторов.

**Зависимости:** ext-pdo_sqlite или ext-sqlite3.

**Детали реализации:**
- `class SQLiteStore`
- Конструктор: `__construct(string $dbPath)` — создаёт/открывает SQLite-файл
- При инициализации создаёт таблицы:
  - `collections` (name, dimension, metric, index_type, created_at)
  - `vectors_{collection}` (id TEXT PRIMARY KEY, vector BLOB, metadata TEXT, created_at)
- `createCollection(string $name, int $dimension, ...): void`
- `collectionExists(string $name): bool`
- `insertVector(string $collection, string $id, string $vectorBlob, ?string $metadata): void`
- `getVector(string $collection, string $id): array`
- `deleteVector(string $collection, string $id): void`
- `countVectors(string $collection): int`
- `iterateVectors(string $collection): \Generator` — потоковое чтение
- `clearCollection(string $collection): void`
- `rawQuery(string $sql, array $params): array`

**Критерий приёмки:**
- База создаётся
- CRUD работает
- Блоб хранится корректно

---

## ШАГ 84: vector/src/SqliteVecExtension.php

**Назначение:** FFI-биндинг к sqlite-vec extension для ANN-поиска.

**Зависимости:** PHP FFI, Шаг 83 (SQLiteStore).

**Детали реализации:**
- `class SqliteVecExtension`
- `isAvailable(): bool` — проверяет наличие sqlite-vec
- `load(SQLiteStore $store): void` — загружает extension в SQLite-соединение
- `createIndex(string $collection, string $indexType = 'hnsw'): void` — создаёт ANN-индекс
- `search(string $collection, string $vectorBlob, int $k): array` — ANN-поиск
- **Если sqlite-vec недоступен:** все методы возвращают false/пустой массив. Поиск делегируется BruteForceIndex.

---

## ШАГ 85: vector/src/BruteForceIndex.php

**Назначение:** Fallback brute force поиск на PHP.

**Зависимости:** Шаг 83 (SQLiteStore).

**Детали реализации:**
- `class BruteForceIndex`
- `search(array $queryVector, array $vectors, int $k, string $metric = 'cosine'): array`
  - Перебирает все векторы
  - Вычисляет расстояние (cosine / euclidean / dot)
  - Heap для top-k
  - Возвращает массив [['id' => ..., 'distance' => ...], ...]
- Оптимизация: кэширование нормализованных векторов для cosine

**Критерий приёмки:**
- Для 10 тыс. векторов поиск работает за < 100ms

---

## ШАГ 86: vector/src/MetadataFilter.php

**Назначение:** WHERE-подобный парсер для фильтрации по JSON-метаданным.

**Зависимости:** нет.

**Детали реализации:**
- `class MetadataFilter`
- `matches(array $metadata, array $filter): bool`
- Поддерживаемые операторы:
  - `eq` — равенство
  - `neq` — не равно
  - `gt`, `gte`, `lt`, `lte` — сравнения
  - `in`, `nin` — вхождение в массив
  - `contains` — подстрока
  - `exists` — наличие ключа
  - `and`, `or`, `not` — логические
- `toSql(array $filter): array` — конвертация в SQL-условия для PDO
- `toPhp(array $filter): \Closure` — конвертация в PHP-предикат

**Критерий приёмки:**
- `matches(['price' => 100], ['price' => ['lt' => 200]])` → true
- Вложенные AND/OR работают

---

## ШАГ 87: vector/src/Collection.php

**Назначение:** Реализация интерфейса VectorStore.

**Зависимости:** core Contracts\VectorStore, Шаги 83-86.

**Детали реализации:**
- `class Collection implements VectorStore`
- Конструктор соответствует сигнатуре из INTERFACE_CONTRACTS.md, раздел 13
- `add(string $id, array $vector, ?array $metadata = null): void`
  - Валидирует размерность
  - Квантует вектор (если нужно)
  - Сериализует в BLOB (pack float32)
  - Сохраняет через SQLiteStore
- `addBatch(array $items): void` — в транзакции
- `search(array $queryVector, int $k = 10, ?array $filter = null): array`
  - Квантует query-вектор
  - Если sqlite-vec доступен → ANN-поиск
  - Если нет → BruteForceIndex
  - Применяет фильтр к результатам
  - Возвращает id, distance, metadata
- `delete`, `deleteByFilter`, `update` — CRUD
- `count(), dimension(), collectionName()` — геттеры
- `iterator(): \Iterator` — потоковое чтение через Generator
- `export(): array` — все векторы как массив
- `clear(): void` — очистка коллекции

**Критерий приёмки:**
- Полный цикл: add → search → delete
- Поиск возвращает векторы, отсортированные по расстоянию
- Фильтр по метаданным работает

---

## ШАГ 88: vector/src/CollectionManager.php

**Назначение:** Управление коллекциями.

**Зависимости:** Шаг 87 (Collection), Шаг 83 (SQLiteStore).

**Детали реализации:**
- `class CollectionManager`
- `create(string $name, int $dimension, array $options = []): Collection`
- `open(string $name): Collection` — открыть существующую
- `delete(string $name): void` — удалить коллекцию
- `list(): array` — список коллекций
- `exists(string $name): bool`

---

## ШАГ 89: vector/src/ExportImport.php

**Назначение:** Экспорт/Импорт коллекций.

**Зависимости:** Шаг 87 (Collection).

**Детали реализации:**
- `class ExportImport`
- `toJson(Collection $collection, string $path): void`
  - Экспорт в JSON lines (по одному вектору на строку)
- `fromJson(string $path, string $collectionName, int $dimension): Collection`
- `toNumpy(Collection $collection, string $path): void` — экспорт в .npy (опционально)
- `toCsv(Collection $collection, string $path): void`

---

## ПАКЕТ `model-hub` (12 файлов)

> В Фазе 3 создаются 12 из 13 файлов пакета. 13-й файл — `StreamLoader.php` (потоковая/mmap загрузка >18GB моделей) — реализуется в Фазе 4 (шаг 121) как production-доработка.

---

## ШАГ 90: model-hub/src/Format/FormatDetector.php

**Назначение:** Определение формата модели по magic bytes.

**Зависимости:** нет.

**Детали реализации:**
- `class FormatDetector`
- `detect(string $path): string` — возвращает: 'onnx', 'gguf', 'safetensors', 'ai', 'rbm' или 'unknown'
- Magic bytes:
  - .onnx → `\x08\x08\x12\x08` (Protobuf header)
  - .gguf → `GGUF` (первые 4 байта)
  - .safetensors → первые 8 байт — длина заголовка (uint64 little-endian)
  - .ai → `PK\x03\x04` (ZIP)
  - .rbm → специфичный для RubixML

---

## ШАГ 91: model-hub/src/Format/AiArchive.php

**Назначение:** Чтение/запись .ai архивов.

**Зависимости:** ext-zip.

**Детали реализации:**
- `class AiArchive`
- `create(string $outputPath, array $files): void`
  - $files = ['model.onnx' => '/path/to/model', 'tokenizer.json' => '/path/...', 'config.json' => '/path/...']
  - Создаёт ZIP-архив
- `extract(string $archivePath, string $outputDir): array`
  - Извлекает все файлы
  - Возвращает ассоциативный массив: тип → путь
- `list(string $archivePath): array` — список файлов в архиве
- `validate(string $archivePath): bool` — проверяет наличие model и config

---

## ШАГ 92: model-hub/src/Format/OnnxInspector.php

**Назначение:** Интроспекция .onnx файла без полной загрузки.

**Зависимости:** нет.

**Детали реализации:**
- `class OnnxInspector`
- `inspect(string $path): ModelMetadata`
  - Парсит ONNX-модель (Protobuf)
  - Читает: имя модели (из graph.name), версию (из model_version), inputs/outputs
  - Считает количество параметров (опционально)
- `inputs(string $path): array`
- `outputs(string $path): array`
- **Фолбэк:** если парсинг Protobuf слишком сложен → возвращает базовые метаданные из имени файла

---

## ШАГ 93: model-hub/src/Format/GgufInspector.php

**Назначение:** Интроспекция .gguf файла.

**Зависимости:** нет.

**Детали реализации:**
- `class GgufInspector`
- `inspect(string $path): ModelMetadata`
  - Читает GGUF-заголовок (первые байты GGUF + metadata_kv pairs)
  - Извлекает: general.name, general.architecture, general.quantization_version, общее количество токенов/параметров
- `metadata(string $path): array` — все ключи-значения из GGUF
- `sizeBytes(string $path): int` — размер файла

---

## ШАГ 94: model-hub/src/Signature/Sha256Verifier.php

**Назначение:** SHA-256 верификация файла.

**Зависимости:** ext-hash.

**Детали реализации:**
- `class Sha256Verifier`
- `compute(string $path): string` — SHA-256 файла
- `verify(string $path, string $expectedHash): bool` — сравнение
- `verifyFile(string $path, string $sha256Path): bool` — сравнение с .sha256 файлом

---

## ШАГ 95: model-hub/src/Signature/SignatureVerifier.php

**Назначение:** Ed25519 верификация подписи.

**Зависимости:** ext-sodium.

**Детали реализации:**
- `class SignatureVerifier`
- `verify(string $dataPath, string $signaturePath, string $publicKeyPath): bool`
  - Читает файл данных
  - Читает подпись (64 байта Ed25519)
  - Читает публичный ключ (32 байта)
  - Проверяет через `sodium_crypto_sign_verify_detached()`
- `sign(string $dataPath, string $privateKeyPath): string` — создание подписи (для авторов моделей)

---

## ШАГ 96: model-hub/src/ModelVerifier.php

**Назначение:** Комплексная верификация модели.

**Зависимости:** Шаг 90, 94, 95.

**Детали реализации:**
- `class ModelVerifier`
- `verify(string $path, ?string $sha256 = null, ?string $signature = null, ?string $publicKey = null): bool`
  - Проверяет SHA-256 (если задан)
  - Проверяет Ed25519 подпись (если задана)
  - Проверяет magic bytes → формат должен быть известен
- `quickVerify(string $path): bool` — только magic bytes

---

## ШАГ 97: model-hub/src/ModelIntrospector.php

**Назначение:** Интроспекция модели без загрузки.

**Зависимости:** Шаг 90, 92, 93.

**Детали реализации:**
- `class ModelIntrospector`
- `introspect(string $path): ModelMetadata`
  - Определяет формат
  - Делегирует OnnxInspector или GgufInspector
  - Для неизвестных форматов: basic info (имя файла, размер)

---

## ШАГ 98: model-hub/src/HuggingFaceClient.php

**Назначение:** HTTP-клиент для HuggingFace Hub API.

**Зависимости:** codewithkyrian/huggingface-php.

**Детали реализации:**
- `class HuggingFaceClient`
- `listFiles(string $modelId): array` — список файлов модели
- `downloadFile(string $modelId, string $filename, string $destination): void`
- `getModelInfo(string $modelId): array` — метаданные модели (теги, лицензия, размер)
- `searchModels(string $query, array $filters = []): array`
- Использует базовый пакет `codewithkyrian/huggingface-php` для HTTP
- Добавляет: ретраи (3 попытки с экспоненциальной задержкой), таймаут, поддержку HF_TOKEN

---

## ШАГ 99: model-hub/src/Downloader.php

**Назначение:** Загрузка с прогрессом, ретраями, resume.

**Зависимости:** Шаг 98 (HuggingFaceClient).

**Детали реализации:**
- `class Downloader`
- `download(string $url, string $destination, ?callable $onProgress = null): void`
  - Поддержка Range-запросов для resume
  - Прогресс-коллбэк: function($downloadedBytes, $totalBytes)
- `downloadWithProgress(string $modelId, string $filename, string $destination): \Generator`
  - Generator: yield ['progress' => 0..100, 'downloaded' => int, 'total' => int, 'speed' => bytes_per_sec]
- `cancel(): void` — отмена текущей загрузки

---

## ШАГ 100: model-hub/src/CacheManager.php

**Назначение:** LRU-кэш для моделей.

**Зависимости:** нет.

**Детали реализации:**
- `class CacheManager`
- Конструктор: `__construct(string $cacheDir, ?int $maxSizeBytes = null)`
- `put(string $key, string $path): void` — добавить в кэш
- `get(string $key): ?string` — получить путь (обновляет access time)
- `has(string $key): bool`
- `remove(string $key): void`
- `prune(): int` — LRU-вытеснение до уровня maxSizeBytes. Возвращает количество удалённых.
- `cacheSize(): int` — текущий размер
- `list(): array` — [key => ['path' => ..., 'size' => ..., 'last_access' => ...]]
- `clear(): void` — полная очистка
- Использует SQLite для хранения метаданных кэша (ключ, путь, размер, last_access)

---

## ШАГ 101: model-hub/src/Hub.php

**Назначение:** Реализация интерфейса ModelHub.

**Зависимости:** core Contracts\ModelHub, Шаги 96-100.

**Детали реализации:**
- `class Hub implements ModelHubContract`
- Конструктор: `__construct(string $cacheDir, ?string $hfToken = null)`
- Все 9 методов интерфейса:
  - `download($modelId, $version)` — через HuggingFaceClient → CacheManager
  - `cached($modelId, $version)` — через CacheManager
  - `verify($path, $sha256, $signature)` — через ModelVerifier
  - `introspect($path)` — через ModelIntrospector
  - `downloadWithProgress($modelId, $version)` — через Downloader
  - `remove($modelId, $version)` — через CacheManager
  - `prune($maxSizeBytes)` — через CacheManager
  - `cacheSize()` — через CacheManager
  - `warmup($modelIds)` — предзагрузка всех моделей
- Дополнительно:
  - `register(string $name, string $path, ?string $sha256 = null): void` — зарегистрировать локальную модель
  - `list(): array` — список закэшированных моделей
  - `checkUpdates(): array` — модели с обновлениями на HF

**Критерий приёмки:**
- `download('sentence-transformers/all-MiniLM-L6-v2')` скачивает модель
- Повторный вызов возвращает кэшированный путь
- `verify()` проверяет целостность
- `prune()` удаляет старые модели

---

## ПАКЕТ `pipeline` (10 файлов)

---

## ШАГ 102–109: Стадии пайплайна

Все стадии реализуют `core Contracts\Stage`. Каждая стадия в отдельном файле в `pipeline/src/Stages/`.

### ШАГ 102: ChunkStage
- Разбивает текст на чанки через Tokenizer
- Возвращает массив чанков (Generator при run())

### ШАГ 103: TokenizeStage
- Токенизирует текст через Tokenizer
- Возвращает массив token IDs + attention_mask

### ШАГ 104: EmbedStage
- Эмбеддинг через Embedder
- Возвращает вектор

### ШАГ 105: NormalizeStage
- L2-нормализация вектора
- Принимает и возвращает float[]

### ШАГ 106: StoreStage
- Сохраняет вектор в VectorStore
- Принимает: ['id' => ..., 'vector' => ..., 'metadata' => ...]
- Возвращает ID

### ШАГ 107: ClassifyStage
- Классификация через Backend
- Принимает текст, возвращает ClassificationResult

### ШАГ 108: FilterStage
- Фильтрация через callable
- Если предикат возвращает false → элемент пропускается

### ШАГ 109: TransformStage
- Пользовательская трансформация через callable
- Принимает и возвращает mixed

---

## ШАГ 110: pipeline/src/Pipeline.php

**Назначение:** Базовая реализация Pipeline.

**Зависимости:** core Contracts\Pipeline, core Contracts\Stage.

**Детали реализации:**
- `class Pipeline implements Contracts\Pipeline`
- `pipe(Stage $stage): self` — добавляет стадию (fluent interface)
- `run(mixed $input): \Generator`
  - Если $input — массив → обрабатывает каждый элемент
  - Если $input — Generator → лениво потребляет
  - Для каждого элемента: пропускает через все стадии последовательно
  - Если стадия выбрасывает исключение — оно прокидывается наверх, пайплайн останавливается
  - Логирует каждую стадию (время выполнения, вход/выход — опционально)
- `stages(): array` — массив Stage[]
- `__invoke(mixed $input): \Generator` — поддержка Pipe Operator

---

## ШАГ 111: pipeline/src/FiberPipeline.php

**Назначение:** Fibers-based реализация для неблокирующего выполнения.

**Зависимости:** Шаг 110 (Pipeline).

**Детали реализации:**
- `class FiberPipeline extends Pipeline`
- `run(mixed $input): \Generator`
  - Запускает каждую стадию в отдельном Fiber
  - Между элементами: Fiber::suspend()
  - Можно вызывать `$pipeline->tick()` для одного шага
  - Поддержка таймаута: если стадия выполняется дольше N секунд → исключение
- `runAsync(mixed $input): \Fiber` — возвращает Fiber для ручного управления

---

## ПАКЕТ `cpu-backend` (4 файла)

---

## ШАГ 112: cpu-backend/src/RubixMLAdapter.php

**Назначение:** Адаптер к API RubixML.

**Зависимости:** RubixML\ML.

**Детали реализации:**
- `class RubixMLAdapter`
- `loadModel(string $path): \Rubix\ML\Estimator` — загружает .rbm модель
- `predict(\Rubix\ML\Estimator $model, array $samples): array` — предсказание
- `proba(\Rubix\ML\Estimator $model, array $samples): array` — вероятности

---

## ШАГ 113: cpu-backend/src/CpuNativeTensor.php

**Назначение:** Реализация Tensor через RubixML/Tensor.

**Зависимости:** core Contracts\Tensor, RubixML\Tensor.

**Детали реализации:**
- `class CpuNativeTensor implements Tensor`
- Обёртка над `\Tensor\Matrix` и `\Tensor\Vector` из RubixML/Tensor
- Все методы интерфейса Tensor
- `device()` всегда возвращает Device::CPU
- `to(Device $device)` — если CPU → $this, иначе → исключение

---

## ШАГ 114: cpu-backend/src/CpuNativeModel.php

**Назначение:** Реализация Model через RubixML.

**Зависимости:** core Contracts\Model, Шаг 112 (RubixMLAdapter).

**Детали реализации:**
- `class CpuNativeModel implements Model`
- `run(array $inputs): array` — делегирует predict/proba
- `inputs()` / `outputs()` — базовые метаданные
- `metadata()` — читает из файла модели
- `device()` — Device::CPU
- `unload()` — освобождает память

---

## ШАГ 115: cpu-backend/src/CpuNativeBackend.php

**Назначение:** Реализация Backend через RubixML.

**Зависимости:** core Contracts\Backend, Шаг 114 (CpuNativeModel).

**Детали реализации:**
- `class CpuNativeBackend implements Backend`
- `availableDevices(): array` — всегда [Device::CPU]
- `load(string $source, ?Device $device = null): Model` — загружает .rbm
- `version(): string` — версия RubixML/ML
- `isAvailable(): bool` — проверяет наличие RubixML/ML и RubixML/Tensor

---

## ОБНОВЛЕНИЕ ПАКЕТА `ai`

---

## ШАГ 116: ai/src/AIFactory.php (обновление)

**Изменения:**
- `createBackend(BackendType $type)` — добавляет CpuNative
- `createEmbedder(string $modelName)` — было исключение, стало `new Embedder(...)`
- `createVectorStore(string $collection, int $dimension)` — через CollectionManager
- `createModelHub()` — через Hub
- `createPipeline()` — через FiberPipeline

---

## ШАГ 117: ai/src/AI.php (обновление)

**Изменения:**
- `config()` — регистрирует CpuNativeBackend
- `similarity(string $a, string $b): float` — теперь использует Embedder напрямую
- `pipeline(): Pipeline` — было исключение, стало `AIFactory::createPipeline()`
- `vector(string $collection): VectorStore` — было исключение, стало `AIFactory::createVectorStore(...)`
- `hub(): ModelHub` — было исключение, стало `AIFactory::createModelHub()`
- `predict(array $features): mixed` — было исключение, стало через CpuNativeBackend
- `moderate(string $text): array` — было исключение, теперь реально вызывает ONNX-модель модерации

---

## ИНТЕГРАЦИОННЫЙ ТЕСТ ФАЗЫ 3

```bash
# 1. Эмбеддинги
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['backend' => 'onnx']);
\$r = AI::embed('Hello world');
assert(count(\$r->vector) > 0);
assert(\$r->dimension > 0);
echo 'Embed OK, dim: ' . \$r->dimension . PHP_EOL;
"

# 2. Косинусное сходство
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['backend' => 'onnx']);
\$sim = AI::similarity('Hello world', 'Hi there');
assert(\$sim > 0.0);
assert(\$sim <= 1.0);
echo 'Similarity: ' . \$sim . PHP_EOL;
"

# 3. Vector Store
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['backend' => 'onnx']);
\$store = AI::vector('test');
\$store->add('1', [0.1, 0.2, 0.3], ['label' => 'A']);
\$store->add('2', [0.4, 0.5, 0.6], ['label' => 'B']);
\$results = \$store->search([0.1, 0.2, 0.3], 1);
assert(count(\$results) === 1);
assert(\$results[0]['id'] === '1');
\$store->clear();
echo 'Vector Store OK' . PHP_EOL;
"

# 4. Pipeline
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['backend' => 'onnx']);
\$pipeline = AI::pipeline()
    ->pipe(new \FerryAI\Pipeline\TransformStage(function(\$x) { return strtoupper(\$x); }))
    ->pipe(new \FerryAI\Pipeline\TransformStage(function(\$x) { return \$x . '!'; }));
foreach (\$pipeline->run(['hello', 'world']) as \$result) {
    echo \$result . PHP_EOL;
}
echo 'Pipeline OK' . PHP_EOL;
"

# 5. Model Hub
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['model_cache' => sys_get_temp_dir() . '/ferry-ai-models']);
\$hub = AI::hub();
\$path = \$hub->download('sentence-transformers/all-MiniLM-L6-v2');
assert(file_exists(\$path));
echo 'Model Hub OK, path: ' . \$path . PHP_EOL;
"

# 6. Модерация
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['backend' => 'onnx']);
\$result = AI::moderate('This is a normal text');
assert(isset(\$result['flagged']));
echo 'Moderation OK, flagged: ' . (\$result['flagged'] ? 'yes' : 'no') . PHP_EOL;
"
```

**Все 6 тестов должны пройти без ошибок.**

---

## КРИТЕРИИ ГОТОВНОСТИ ФАЗЫ 3

- [ ] Все 42 файла созданы
- [ ] `ferry-ai/inference-embedding` работает: embed, embedBatch, normalize, cosineSimilarity
- [ ] `ferry-ai/inference-vector` работает: CRUD, search с фильтрацией, fallback brute force
- [ ] `ferry-ai/inference-model-hub` работает: download, cache, verify, prune
- [ ] `ferry-ai/inference-pipeline` работает: стадии, Generator, ошибки
- [ ] `ferry-ai/inference-cpu-backend` работает как fallback
- [ ] `AI::similarity()` вычисляет косинусное сходство
- [ ] `AI::vector('name')` открывает Vector Store
- [ ] `AI::hub()` управляет моделями
- [ ] `AI::pipeline()` создаёт конвейер
- [ ] `AI::moderate()` проверяет контент
- [ ] `AI::predict()` работает через cpu-backend
- [ ] LRU-кэш очищает старые модели
- [ ] Верификация моделей (SHA-256) работает
- [ ] Экспорт/импорт Vector Store работает

---

> **План реализации Фазы 3 завершён. После успешного прохождения всех шагов и интеграционного теста можно переходить к Фазе 4.**
