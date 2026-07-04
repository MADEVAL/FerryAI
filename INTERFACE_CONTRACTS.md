# PHP AI Platform — Контракты интерфейсов

> Версия: 1.0  
> Назначение: исчерпывающие сигнатуры всех интерфейсов, enum'ов, value objects, исключений  
> Основание: TECHNICAL_SPECIFICATION.md, разделы 10–22  
> Правило: сигнатуры здесь — истина. Реализация не может отклоняться.

---

## ПАКЕТ `core` (нулевые зависимости)

### Пространство имён: `FerryAI\Core`

---

## 1. Контракты (Contracts)

### 1.1. `Contracts\Backend`

```php
namespace FerryAI\Core\Contracts;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\ValueObjects\ModelMetadata;

interface Backend
{
    /**
     * Возвращает список устройств, доступных данному бэкенду.
     * Порядок: от наиболее производительного к наименее.
     *
     * @return Device[]
     */
    public function availableDevices(): array;

    /**
     * Загружает модель из указанного источника.
     *
     * @param string $source  Путь к файлу (.onnx, .gguf, .rbm),
     *                        URL (https://...),
     *                        HuggingFace-идентификатор (hf://org/model),
     *                        resource (поток)
     * @param Device|null $device  Целевое устройство.
     *                             null = автоопределение (AUTO).
     *
     * @return Model
     *
     * @throws \FerryAI\Core\Exception\ModelNotFoundException  Источник недоступен
     * @throws \FerryAI\Core\Exception\ModelLoadException      Ошибка загрузки (формат, совместимость)
     */
    public function load(string $source, ?Device $device = null): Model;

    /**
     * Возвращает строковый идентификатор версии нативного движка.
     * Пример: "1.18.0" для ONNX Runtime, "b4000" для llama.cpp.
     */
    public function version(): string;

    /**
     * Проверяет, доступен ли данный бэкенд в текущем окружении
     * (наличие shared library, совместимость ОС/архитектуры).
     */
    public function isAvailable(): bool;
}
```

---

### 1.2. `Contracts\Model`

```php
namespace FerryAI\Core\Contracts;

use FerryAI\Core\ValueObjects\ModelMetadata;

interface Model
{
    /**
     * Выполняет инференс (прямой проход) модели.
     *
     * @param array<string, mixed> $inputs
     *   Ассоциативный массив: имя_входа => данные.
     *   Данные: Tensor | массив PHP | строка.
     *
     * @return array<string, mixed>
     *   Ассоциативный массив: имя_выхода => данные (Tensor).
     *
     * @throws \FerryAI\Core\Exception\InferenceException  Ошибка выполнения
     * @throws \FerryAI\Core\Exception\ShapeMismatchException  Несоответствие формы входа
     */
    public function run(array $inputs): array;

    /**
     * Возвращает метаданные входов модели.
     *
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     *   Ключ — имя входа.
     *   Значение: name, shape (с -1 для динамических осей), dtype (строка).
     */
    public function inputs(): array;

    /**
     * Возвращает метаданные выходов модели.
     *
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public function outputs(): array;

    /**
     * Возвращает метаданные модели: имя, автор, версия, лицензия, размер, теги.
     */
    public function metadata(): ModelMetadata;

    /**
     * Возвращает устройство, на котором загружена модель.
     */
    public function device(): \FerryAI\Core\Enums\Device;

    /**
     * Освобождает нативные ресурсы модели.
     * После вызова модель непригодна для использования.
     */
    public function unload(): void;
}
```

---

### 1.3. `Contracts\Tensor`

```php
namespace FerryAI\Core\Contracts;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\ValueObjects\Shape;
use ArrayAccess;
use Countable;
use JsonSerializable;

interface Tensor extends ArrayAccess, Countable, JsonSerializable
{
    /**
     * Возвращает форму тензора.
     */
    public function shape(): Shape;

    /**
     * Возвращает тип данных элементов тензора.
     */
    public function dtype(): DType;

    /**
     * Переносит тензор на целевое устройство.
     * Возвращает НОВЫЙ тензор. Исходный не мутируется.
     *
     * @throws \FerryAI\Core\Exception\DeviceNotAvailableException
     */
    public function to(Device $device): self;

    /**
     * Возвращает устройство, на котором находится тензор.
     */
    public function device(): Device;

    /**
     * Экспортирует данные тензора в PHP-массив.
     * Для больших тензоров — дорогостоящая операция.
     *
     * @return array  Многомерный массив соответствующей формы
     */
    public function toArray(): array;

    /**
     * Возвращает сырой FFI-буфер (указатель на нативную память).
     * Для zero-copy передачи между бэкендами.
     *
     * @return mixed  FFI\CData (указатель)
     */
    public function data(): mixed;

    /**
     * Поэлементное сложение двух тензоров.
     * Возвращает новый тензор.
     *
     * @throws \FerryAI\Core\Exception\ShapeMismatchException
     */
    public function add(self $other): self;

    /**
     * Поэлементное вычитание.
     */
    public function sub(self $other): self;

    /**
     * Поэлементное умножение.
     */
    public function mul(self $other): self;

    /**
     * Матричное умножение (matmul).
     *
     * @throws \FerryAI\Core\Exception\ShapeMismatchException
     */
    public function matmul(self $other): self;

    /**
     * Транспонирование тензора.
     * Для 2D — стандартное. Для >2D — перестановка осей.
     *
     * @param int[]|null $axes  Порядок осей. null = обратный порядок.
     */
    public function transpose(?array $axes = null): self;

    /**
     * Изменение формы тензора (reshape).
     * Общее число элементов должно сохраняться.
     *
     * @throws \FerryAI\Core\Exception\ShapeMismatchException
     */
    public function reshape(Shape $newShape): self;

    /**
     * Срез тензора по осям.
     *
     * @param array<int, int|array{int, int}> $slices
     *   Ключ — номер оси. Значение — индекс (int) или диапазон [start, length].
     */
    public function slice(array $slices): self;

    /**
     * Создаёт клон тензора с изменённым устройством (PHP 8.5 clone with).
     * Без мутации исходного.
     */
    public function __clone(): void;

    // ArrayAccess
    public function offsetExists(mixed $offset): bool;
    public function offsetGet(mixed $offset): mixed;
    public function offsetSet(mixed $offset, mixed $value): void;
    public function offsetUnset(mixed $offset): void;

    // Countable
    public function count(): int;

    // JsonSerializable
    public function jsonSerialize(): array;

    // __serialize / __unserialize для кэширования
    public function __serialize(): array;
    public function __unserialize(array $data): void;
}
```

---

### 1.4. `Contracts\Tokenizer`

```php
namespace FerryAI\Core\Contracts;

use FerryAI\Core\Enums\TokenizerType;

interface Tokenizer
{
    /**
     * Кодирует текст в массив ID токенов.
     *
     * @param string $text          Входной текст
     * @param bool   $addSpecialTokens  Добавлять BOS/EOS
     *
     * @return int[]  Массив token IDs
     *
     * @throws \FerryAI\Core\Exception\TokenizerException
     */
    public function encode(string $text, bool $addSpecialTokens = true): array;

    /**
     * Декодирует массив ID токенов обратно в текст.
     *
     * @param int[] $ids
     *
     * @throws \FerryAI\Core\Exception\TokenizerException
     */
    public function decode(array $ids): string;

    /**
     * Пакетное кодирование нескольких текстов.
     *
     * @param string[] $texts
     * @param bool     $padToMaxLength  Добивать до максимальной длины в батче
     *
     * @return array{
     *     input_ids: int[][],
     *     attention_mask: int[][]
     * }
     */
    public function encodeBatch(array $texts, bool $padToMaxLength = true): array;

    /**
     * Возвращает размер словаря (общее количество токенов).
     */
    public function vocabSize(): int;

    /**
     * Возвращает тип токенизатора.
     */
    public function type(): TokenizerType;

    /**
     * Возвращает ID специального токена.
     *
     * @param string $tokenName  Одно из: bos, eos, pad, unk, sep, cls, mask
     */
    public function specialTokenId(string $tokenName): ?int;

    /**
     * Возвращает все специальные токены и их ID.
     *
     * @return array<string, int>
     */
    public function specialTokens(): array;

    /**
     * Подсчитывает количество токенов в тексте БЕЗ полного кодирования.
     * Быстрая оценка.
     */
    public function countTokens(string $text): int;

    /**
     * Разбивает длинный текст на чанки фиксированной длины с перекрытием.
     *
     * @param string $text
     * @param int    $maxTokens   Максимальное число токенов в чанке
     * @param int    $overlap     Перекрытие между чанками в токенах
     *
     * @return string[]
     */
    public function chunk(string $text, int $maxTokens = 512, int $overlap = 64): array;
}
```

---

### 1.5. `Contracts\Embedder`

```php
namespace FerryAI\Core\Contracts;

interface Embedder
{
    /**
     * Вычисляет эмбеддинг для одного текста.
     *
     * @param string $text
     *
     * @return float[]  Вектор эмбеддинга (float32)
     */
    public function embed(string $text): array;

    /**
     * Вычисляет эмбеддинги для батча текстов.
     *
     * @param string[] $texts
     *
     * @return float[][]  Массив векторов
     */
    public function embedBatch(array $texts): array;

    /**
     * Возвращает размерность векторов эмбеддинга.
     */
    public function dimension(): int;

    /**
     * Нормализует вектор (L2-норма).
     *
     * @param float[] $vector
     * @return float[]
     */
    public function normalize(array $vector): array;

    /**
     * Вычисляет косинусное сходство между двумя векторами.
     */
    public function cosineSimilarity(array $a, array $b): float;

    /**
     * Возвращает имя используемой модели эмбеддингов.
     */
    public function modelName(): string;
}
```

---

### 1.6. `Contracts\VectorStore`

```php
namespace FerryAI\Core\Contracts;

use Iterator;

interface VectorStore
{
    /**
     * Добавляет один вектор в хранилище.
     *
     * @param string     $id        Уникальный идентификатор
     * @param float[]    $vector    Вектор (float32)
     * @param array|null $metadata  Произвольные JSON-метаданные
     *
     * @throws \FerryAI\Core\Exception\ShapeMismatchException  Размерность не совпадает с коллекцией
     */
    public function add(string $id, array $vector, ?array $metadata = null): void;

    /**
     * Добавляет батч векторов.
     *
     * @param array<int, array{id: string, vector: float[], metadata?: array}> $items
     */
    public function addBatch(array $items): void;

    /**
     * Поиск k ближайших соседей.
     *
     * @param float[]     $queryVector  Вектор запроса
     * @param int         $k            Количество результатов
     * @param array|null  $filter       Фильтр по метаданным (WHERE-синтаксис)
     *
     * @return array<int, array{id: string, distance: float, metadata: array}>
     */
    public function search(array $queryVector, int $k = 10, ?array $filter = null): array;

    /**
     * Удаляет вектор по ID.
     */
    public function delete(string $id): void;

    /**
     * Удаляет векторы по фильтру метаданных.
     *
     * @return int  Количество удалённых
     */
    public function deleteByFilter(array $filter): int;

    /**
     * Обновляет вектор и/или метаданные по ID.
     */
    public function update(string $id, ?array $vector = null, ?array $metadata = null): void;

    /**
     * Возвращает количество векторов в коллекции.
     */
    public function count(): int;

    /**
     * Возвращает размерность векторов коллекции.
     */
    public function dimension(): int;

    /**
     * Возвращает имя коллекции.
     */
    public function collectionName(): string;

    /**
     * Возвращает итератор по всем векторам коллекции.
     *
     * @return Iterator<array{id: string, vector: float[], metadata: array}>
     */
    public function iterator(): Iterator;

    /**
     * Экспорт всей коллекции в массив.
     *
     * @return array<int, array{id: string, vector: float[], metadata: array}>
     */
    public function export(): array;

    /**
     * Очищает коллекцию полностью.
     */
    public function clear(): void;
}
```

---

### 1.7. `Contracts\Pipeline`

```php
namespace FerryAI\Core\Contracts;

use Generator;

interface Pipeline
{
    /**
     * Добавляет стадию в конвейер.
     *
     * @param Stage $stage
     * @return $this
     */
    public function pipe(Stage $stage): self;

    /**
     * Запускает конвейер на входных данных.
     *
     * @param mixed $input  Начальные данные (строка, массив, Generator)
     *
     * @return Generator  Ленивая обработка. Каждый элемент — результат прохода через все стадии.
     */
    public function run(mixed $input): Generator;

    /**
     * Возвращает массив добавленных стадий.
     *
     * @return Stage[]
     */
    public function stages(): array;

    /**
     * Поддержка Pipe Operator PHP 8.5:
     * $pipeline |> $pipeline->pipe(new TokenizeStage(...))
     *               |> $pipeline->pipe(new EmbedStage(...))
     */
    public function __invoke(mixed $input): Generator;
}
```

---

### 1.8. `Contracts\Stage`

```php
namespace FerryAI\Core\Contracts;

interface Stage
{
    /**
     * Обрабатывает один элемент данных.
     *
     * @param mixed $input  Данные от предыдущей стадии (или начальные)
     *
     * @return mixed  Данные для следующей стадии
     *
     * @throws \RuntimeException  При ошибке обработки. Пайплайн останавливается.
     */
    public function process(mixed $input): mixed;

    /**
     * Возвращает имя стадии (для логирования и профилирования).
     */
    public function name(): string;
}
```

---

### 1.9. `Contracts\ModelHub`

```php
namespace FerryAI\Core\Contracts;

use FerryAI\Core\ValueObjects\ModelMetadata;
use Generator;

interface ModelHub
{
    /**
     * Загружает модель из HuggingFace Hub.
     *
     * @param string $modelId  Например: "sentence-transformers/all-MiniLM-L6-v2"
     * @param string|null $version  Версия/тег. null = последняя.
     *
     * @return string  Локальный путь к скачанному файлу модели
     *
     * @throws \FerryAI\Core\Exception\ModelNotFoundException
     */
    public function download(string $modelId, ?string $version = null): string;

    /**
     * Проверяет, есть ли модель в локальном кэше.
     *
     * @param string $modelId
     * @param string|null $version
     *
     * @return string|null  Путь к модели или null
     */
    public function cached(string $modelId, ?string $version = null): ?string;

    /**
     * Верифицирует файл модели (SHA-256 + Ed25519 подпись).
     *
     * @param string      $path       Путь к файлу
     * @param string|null $sha256     Ожидаемый SHA-256. null = проверить по .sha256 файлу.
     * @param string|null $signature  Ed25519 подпись. null = проверить по .ed25519 файлу.
     *
     * @return bool  true если верификация пройдена
     *
     * @throws \FerryAI\Core\Exception\ModelLoadException  Верификация не пройдена
     */
    public function verify(string $path, ?string $sha256 = null, ?string $signature = null): bool;

    /**
     * Читает метаданные модели БЕЗ полной загрузки.
     *
     * @param string $path  Путь к файлу модели
     */
    public function introspect(string $path): ModelMetadata;

    /**
     * Загружает модель с прогресс-баром.
     *
     * @param string $modelId
     * @param string|null $version
     *
     * @return Generator  yield ['progress' => 0..100, 'downloaded' => int, 'total' => int]
     */
    public function downloadWithProgress(string $modelId, ?string $version = null): Generator;

    /**
     * Удаляет модель из кэша.
     *
     * @param string      $modelId
     * @param string|null $version  null = удалить все версии
     */
    public function remove(string $modelId, ?string $version = null): void;

    /**
     * Очищает кэш по LRU-политике.
     *
     * @param int|null $maxSizeBytes  Максимальный размер кэша. null = из конфигурации.
     *
     * @return int  Количество удалённых моделей
     */
    public function prune(?int $maxSizeBytes = null): int;

    /**
     * Возвращает суммарный размер кэша в байтах.
     */
    public function cacheSize(): int;

    /**
     * Предзагружает список моделей (прогрев кэша).
     *
     * @param string[] $modelIds
     */
    public function warmup(array $modelIds): void;
}
```

---

### 1.10. `Contracts\DataFrame` (отложен, Фаза 4)

```php
namespace FerryAI\Core\Contracts;

use Iterator;
use Countable;

interface DataFrame extends Iterator, Countable
{
    /**
     * @return string[]  Имена колонок
     */
    public function columns(): array;

    /**
     * @return string[]  Типы колонок (float, int, string, categorical)
     */
    public function dtypes(): array;

    /**
     * Возвращает количество строк.
     */
    public function numRows(): int;

    /**
     * Возвращает количество колонок.
     */
    public function numCols(): int;

    /**
     * Фильтрация строк по условию.
     *
     * @param callable $predicate  function(array $row): bool
     */
    public function filter(callable $predicate): self;

    /**
     * Сортировка по колонке.
     *
     * @param string $column
     * @param bool   $ascending
     */
    public function sort(string $column, bool $ascending = true): self;

    /**
     * Группировка по колонке.
     *
     * @param string $column
     * @return array<string, static>
     */
    public function groupBy(string $column): array;

    /**
     * Агрегация по колонке.
     *
     * @param string $column
     * @param string $function  sum, mean, min, max, count
     */
    public function aggregate(string $column, string $function): float|int;

    /**
     * Выборка подмножества колонок.
     *
     * @param string[] $columns
     */
    public function select(array $columns): self;

    /**
     * Получение колонки как массива.
     *
     * @return mixed[]
     */
    public function column(string $name): array;

    /**
     * Получение строки как ассоциативного массива.
     */
    public function row(int $index): array;

    /**
     * Конвертация колонки в тензор.
     */
    public function toTensor(string $column): \FerryAI\Core\Contracts\Tensor;

    /**
     * Импорт из CSV.
     */
    public static function fromCsv(string $path, bool $hasHeader = true): self;

    /**
     * Импорт из массива.
     */
    public static function fromArray(array $data, ?array $columns = null): self;

    /**
     * Экспорт в массив.
     */
    public function toArray(): array;

    /**
     * Экспорт в CSV.
     */
    public function toCsv(string $path, bool $includeHeader = true): void;
}
```

---

### 1.11. `Contracts\ExecutionProvider` (onnx-backend)

```php
namespace FerryAI\OnnxBackend\Provider;

use FerryAI\Core\Enums\Device;

interface ExecutionProvider
{
    /** Имя провайдера: "CPUExecutionProvider", "CUDAExecutionProvider", etc. */
    public function name(): string;

    /** Устройство, соответствующее провайдеру. */
    public function device(): Device;

    /** Проверка доступности в текущем окружении. */
    public function isAvailable(): bool;

    /** Возвращает массив настроек для OrtSessionOptions. */
    public function configure(): array;
}
```

---

### 1.12. `Contracts\PoolingStrategy` (embedding)

```php
namespace FerryAI\Embedding\Pooling;

interface PoolingStrategy
{
    /**
     * Извлекает эмбеддинг из скрытых состояний модели.
     *
     * @param array      $hiddenStates   [seq_len, hidden_dim] или [batch, seq_len, hidden_dim]
     * @param array|null $attentionMask  Маска внимания (1 = реальный токен, 0 = padding)
     *
     * @return float[]  Вектор [hidden_dim] или [batch, hidden_dim]
     */
    public function pool(array $hiddenStates, ?array $attentionMask = null): array;

    /** Имя стратегии: cls, mean, eos, max. */
    public function name(): string;
}
```

---

## 2. Перечисления (Enums)

### 2.1. `Enums\Device`

```php
namespace FerryAI\Core\Enums;

enum Device: string
{
    case CPU   = 'cpu';
    case CUDA  = 'cuda';
    case METAL = 'metal';
    case AUTO  = 'auto';

    /**
     * Определяет лучшее доступное устройство из переданного списка.
     *
     * @param Device[] $available
     */
    public static function resolve(Device $preferred, array $available): self;

    /**
     * Возвращает приоритет устройства (чем больше, тем лучше).
     */
    public function priority(): int;
}
```

---

### 2.2. `Enums\DType`

```php
namespace FerryAI\Core\Enums;

enum DType: string
{
    case Float32 = 'float32';
    case Float16 = 'float16';
    case Int32   = 'int32';
    case Int64   = 'int64';
    case String  = 'string';

    /**
     * Размер одного элемента в байтах.
     * Float32 = 4, Float16 = 2, Int32 = 4, Int64 = 8, String = 0 (variable).
     */
    public function sizeInBytes(): int;
}
```

---

### 2.3. `Enums\BackendType`

```php
namespace FerryAI\Core\Enums;

enum BackendType: string
{
    case Onnx      = 'onnx';
    case Llama     = 'llama';
    case CpuNative = 'cpu_native';
}
```

---

### 2.4. `Enums\TokenizerType`

```php
namespace FerryAI\Core\Enums;

enum TokenizerType: string
{
    case BPE           = 'bpe';
    case WordPiece     = 'wordpiece';
    case SentencePiece = 'sentencepiece';
    case Unigram       = 'unigram';
}
```

---

### 2.5. `Enums\GraphOptimizationLevel` (для ONNX)

```php
namespace FerryAI\Core\Enums;

enum GraphOptimizationLevel: string
{
    case DISABLE_ALL = 'disable_all';
    case BASIC       = 'basic';
    case EXTENDED    = 'extended';
    case ALL         = 'all';
}
```

---

### 2.6. `Enums\DistanceMetric` (для Vector Store)

```php
namespace FerryAI\Core\Enums;

enum DistanceMetric: string
{
    case COSINE    = 'cosine';
    case EUCLIDEAN = 'euclidean';
    case DOT       = 'dot';
}
```

---

### 2.7. `Enums\IndexType` (для Vector Store)

```php
namespace FerryAI\Core\Enums;

enum IndexType: string
{
    case HNSW = 'hnsw';
    case IVF  = 'ivf';
    case FLAT = 'flat';
}
```

---

### 2.8. `Enums\QuantizationType` (для Vector Store)

```php
namespace FerryAI\Core\Enums;

enum QuantizationType: string
{
    case FLOAT32 = 'float32';
    case FLOAT16 = 'float16';
    case INT8    = 'int8';
    case BINARY  = 'binary';
}
```

---

## 3. Value Objects

### 3.1. `ValueObjects\Shape`

```php
namespace FerryAI\Core\ValueObjects;

readonly class Shape implements \JsonSerializable
{
    /**
     * @param int[] $dimensions  Размерности. Все >= 0. -1 допустимо для динамических осей.
     *
     * @throws \InvalidArgumentException  Если размерность отрицательна (кроме -1)
     */
    public function __construct(public array $dimensions) {}

    /** Количество осей (ранг). */
    public function rank(): int;

    /** Общее количество элементов (произведение размерностей). -1 если есть динамическая ось. */
    public function size(): int;

    /** Размер по указанной оси. */
    public function dimension(int $axis): int;

    /** Является ли форма статической (все оси известны). */
    public function isStatic(): bool;

    /** Возвращает размерности в виде массива. */
    public function toArray(): array;

    /** Проверяет совместимость формы с другой (broadcasting rules). */
    public function compatibleWith(self $other): bool;

    /** Создаёт Shape из строки вида "1,3,224,224". */
    public static function fromString(string $shape): self;
}
```

---

### 3.2. `ValueObjects\ModelMetadata`

```php
namespace FerryAI\Core\ValueObjects;

readonly class ModelMetadata implements \JsonSerializable
{
    public function __construct(
        public string $name,
        public string $version,
        public string $author,
        public string $license,
        public array  $tags,
        public int    $sizeBytes,
        public ?string $architecture = null,
        public ?string $description  = null,
        public ?string $homepage     = null,
    ) {}

    /** Создаёт из JSON-строки. */
    public static function fromJson(string $json): self;

    /** Экспорт в JSON. */
    public function toJson(): string;
}
```

---

### 3.3. `ValueObjects\ChatMessage`

```php
namespace FerryAI\Core\ValueObjects;

readonly class ChatMessage implements \JsonSerializable
{
    /**
     * @param string       $role     system | user | assistant | tool
     * @param string|array $content  Текст или массив content-parts (мультимодальные)
     * @param string|null  $name     Имя участника (опционально)
     * @param string|null  $toolCallId  ID вызова инструмента (для role=tool)
     * @param array|null   $toolCalls   Вызовы инструментов (для role=assistant)
     */
    public function __construct(
        public string       $role,
        public string|array $content,
        public ?string      $name       = null,
        public ?string      $toolCallId = null,
        public ?array       $toolCalls  = null,
    ) {}

    /** Создаёт системное сообщение. */
    public static function system(string $content): self;

    /** Создаёт пользовательское сообщение. */
    public static function user(string $content): self;

    /** Создаёт сообщение ассистента. */
    public static function assistant(string $content): self;

    /** Создаёт из ассоциативного массива (OpenAI-совместимого). */
    public static function fromArray(array $data): self;

    /** Экспорт в массив для OpenAI-совместимого API. */
    public function toArray(): array;
}
```

---

### 3.4. `ValueObjects\SamplingParams`

```php
namespace FerryAI\Core\ValueObjects;

readonly class SamplingParams
{
    public function __construct(
        public float $temperature       = 0.7,
        public float $topP              = 1.0,
        public int   $topK              = 40,
        public float $repetitionPenalty = 1.0,
        public float $frequencyPenalty  = 0.0,
        public float $presencePenalty   = 0.0,
        public int   $maxTokens         = 2048,
        /** @var string[]|null */
        public ?array $stop             = null,
        public ?int  $seed              = null,
    ) {}
}
```

---

### 3.5. `ValueObjects\GenerationResult`

```php
namespace FerryAI\Core\ValueObjects;

readonly class GenerationResult
{
    public function __construct(
        public string $text,
        public int    $tokensGenerated,
        public int    $tokensPrompt,
        public int    $tokensTotal,
        public float  $durationMs,
        /** @var float[]|null */
        public ?array $logprobs = null,
    ) {}
}
```

---

### 3.6. `ValueObjects\EmbeddingResult`

```php
namespace FerryAI\Core\ValueObjects;

readonly class EmbeddingResult
{
    /**
     * @param float[] $vector   Вектор эмбеддинга
     * @param int     $dimension  Размерность
     * @param string  $modelName  Имя модели
     */
    public function __construct(
        public array  $vector,
        public int    $dimension,
        public string $modelName,
    ) {}
}
```

---

### 3.7. `ValueObjects\ClassificationResult`

```php
namespace FerryAI\Core\ValueObjects;

readonly class ClassificationResult
{
    /**
     * @param string $label       Предсказанная метка
     * @param float  $confidence  Уверенность (0..1)
     * @param array  $allScores   Все метки с вероятностями [label => score]
     */
    public function __construct(
        public string $label,
        public float  $confidence,
        public array  $allScores = [],
    ) {}
}
```

---

## 4. Исключения (Exceptions)

Все исключения — в `FerryAI\Core\Exception`.

### 4.1. Базовое исключение

```php
namespace FerryAI\Core\Exception;

class FerryAIException extends \RuntimeException
{
    /** Код ошибки для машинной обработки. */
    public function errorCode(): string;
}
```

---

### 4.2. `BackendNotAvailableException`

```php
namespace FerryAI\Core\Exception;

class BackendNotAvailableException extends FerryAIException
{
    /** @param string $backendType  Тип бэкенда (onnx, llama, cpu_native) */
    public function __construct(string $backendType, ?string $reason = null);

    /** Возвращает тип бэкенда, который не удалось загрузить. */
    public function backendType(): string;

    /** Возвращает причину (отсутствует shared library, несовместима ОС и т.д.). */
    public function reason(): ?string;
}
```

---

### 4.3. `ModelNotFoundException`

```php
namespace FerryAI\Core\Exception;

class ModelNotFoundException extends FerryAIException
{
    /** @param string $source  Что пытались загрузить (путь, URL, HF id) */
    public function __construct(string $source);

    public function source(): string;
}
```

---

### 4.4. `ModelLoadException`

```php
namespace FerryAI\Core\Exception;

class ModelLoadException extends FerryAIException
{
    /** @param string $path     Путь к файлу
     *  @param string $reason   Причина (битый файл, несовместимый формат и т.д.) */
    public function __construct(string $path, string $reason);

    public function path(): string;
    public function reason(): string;
}
```

---

### 4.5. `InferenceException`

```php
namespace FerryAI\Core\Exception;

class InferenceException extends FerryAIException
{
    /** @param string $message  Описание ошибки (нехватка памяти, внутренняя ошибка движка) */
    public function __construct(string $message);
}
```

---

### 4.6. `ShapeMismatchException`

```php
namespace FerryAI\Core\Exception;

use FerryAI\Core\ValueObjects\Shape;

class ShapeMismatchException extends FerryAIException
{
    /** @param Shape  $expected  Ожидаемая форма
     *  @param Shape  $actual    Фактическая форма */
    public function __construct(Shape $expected, Shape $actual);

    public function expected(): Shape;
    public function actual(): Shape;
}
```

---

### 4.7. `DeviceNotAvailableException`

```php
namespace FerryAI\Core\Exception;

use FerryAI\Core\Enums\Device;

class DeviceNotAvailableException extends FerryAIException
{
    /** @param Device $requested  Запрошенное устройство */
    public function __construct(Device $requested);

    public function requestedDevice(): Device;
}
```

---

### 4.8. `TokenizerException`

```php
namespace FerryAI\Core\Exception;

class TokenizerException extends FerryAIException
{
    /** @param string $reason  Причина ошибки (неизвестный тип, битый tokenizer.json) */
    public function __construct(string $reason);
}
```

---

### 4.9. `ConfigurationException`

```php
namespace FerryAI\Core\Exception;

class ConfigurationException extends FerryAIException
{
    /** @param string $key    Ключ конфигурации с ошибкой
     *  @param string $reason  Описание проблемы */
    public function __construct(string $key, string $reason);

    public function configKey(): string;
}
```

---

## 5. Класс `AIConfig`

```php
namespace FerryAI\Core;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\BackendType;
use ArrayAccess;

final class AIConfig implements ArrayAccess
{
    /** Создаёт конфигурацию из массива. */
    public static function fromArray(array $config): self;

    /** Возвращает все настройки как массив. */
    public function toArray(): array;

    /** Возвращает значение по ключу (с точкой для вложенных). */
    public function get(string $key, mixed $default = null): mixed;

    /** Устанавливает значение. */
    public function set(string $key, mixed $value): self;

    /** Проверяет наличие ключа. */
    public function has(string $key): bool;

    // Типизированные геттеры
    public function backend(): BackendType;
    public function device(): Device;
    public function modelCache(): string;
    public function maxTokens(): int;
    public function temperature(): float;
    public function topP(): float;
    public function streamTimeout(): int;
    public function verifySignatures(): bool;
    public function logLevel(): string;
    public function backendsConfig(): array;

    // ArrayAccess
    public function offsetExists(mixed $offset): bool;
    public function offsetGet(mixed $offset): mixed;
    public function offsetSet(mixed $offset, mixed $value): void;
    public function offsetUnset(mixed $offset): void;
}
```

---

## ПАКЕТ `ai` — Фасад

### Пространство имён: `FerryAI`

---

### 6. Класс `AI` (фасад)

```php
namespace FerryAI;

use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Core\Contracts\ModelHub;
use FerryAI\Core\Contracts\Pipeline;
use FerryAI\Core\ValueObjects\ChatMessage;
use FerryAI\Core\ValueObjects\GenerationResult;
use FerryAI\Core\ValueObjects\EmbeddingResult;
use FerryAI\Core\ValueObjects\ClassificationResult;
use Generator;

final class AI
{
    // === Конфигурация ===

    /**
     * Устанавливает глобальную конфигурацию.
     * Вызывается один раз при старте приложения.
     *
     * @param array $config  @see AIConfig для списка ключей
     */
    public static function config(array $config): void;

    /**
     * Предзагружает модели при старте (прогрев).
     *
     * @param string[] $modelIds
     */
    public static function warmup(array $modelIds): void;

    /**
     * Сбрасывает всю конфигурацию и выгружает все модели.
     */
    public static function reset(): void;

    /**
     * Сбрасывает конкретный бэкенд.
     */
    public static function resetBackend(string $name): void;

    // === Выбор бэкенда / устройства ===

    /**
     * Устанавливает активный бэкенд.
     * Все последующие вызовы пойдут через этот бэкенд.
     *
     * @param string $name  onnx | llama | cpu | auto
     */
    public static function backend(string $name): void;

    /**
     * Устанавливает активное устройство.
     *
     * @param string $device  cpu | cuda | metal | auto
     */
    public static function device(string $device): void;

    // === Чат (LLM) ===

    /**
     * Чат с LLM. Возвращает полный ответ.
     *
     * @param ChatMessage[]|array[] $messages  Сообщения в формате ChatML
     * @param array|null            $options   Переопределение параметров (temperature, maxTokens и т.д.)
     *
     * @return GenerationResult
     */
    public static function chat(array $messages, ?array $options = null): GenerationResult;

    /**
     * Стриминг токенов чата. Возвращает Generator.
     *
     * @param ChatMessage[]|array[] $messages
     * @param array|null            $options
     *
     * @return Generator<string>  Каждый yield — строка (один токен)
     */
    public static function stream(array $messages, ?array $options = null): Generator;

    // === Эмбеддинги ===

    /**
     * Текстовый эмбеддинг.
     *
     * @param string|string[] $input  Текст или массив текстов
     *
     * @return EmbeddingResult|EmbeddingResult[]
     */
    public static function embed(string|array $input): EmbeddingResult|array;

    /**
     * Косинусное сходство между двумя текстами.
     */
    public static function similarity(string $a, string $b): float;

    // === Классификация ===

    /**
     * Классификация текста или изображения.
     *
     * @param string|\resource $input  Текст, путь к файлу, resource
     *
     * @return ClassificationResult
     */
    public static function classify(mixed $input): ClassificationResult;

    /**
     * Модерация контента.
     *
     * @return array{categories: array<string, float>, flagged: bool}
     */
    public static function moderate(string $text): array;

    // === Предиктивная модель (табличные данные) ===

    /**
     * Предсказание на основе табличных признаков.
     *
     * @param array<string, float|int|string> $features
     *
     * @return mixed  Результат зависит от модели
     */
    public static function predict(array $features): mixed;

    // === Доступ к подсистемам ===

    /** Доступ к Pipeline. */
    public static function pipeline(): Pipeline;

    /** Доступ к Vector Store (коллекция по имени). */
    public static function vector(string $collection): VectorStore;

    /** Доступ к Model Hub. */
    public static function hub(): ModelHub;

    /** Загрузка токенизатора по имени модели. */
    public static function tokenizer(string $modelName): Tokenizer;

    // === HTTP-ответы (для контроллеров) ===

    /**
     * Возвращает готовый HTTP-ответ для стриминга (PSR-7).
     * Альтернатива foreach(stream()) для простых случаев.
     */
    public static function streamResponse(array $messages, ?array $options = null): \Psr\Http\Message\ResponseInterface;
}
```

---

### 7. Класс `AIFactory`

```php
namespace FerryAI;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Core\Contracts\ModelHub;
use FerryAI\Core\Contracts\Pipeline;
use FerryAI\Core\Contracts\Embedder;
use FerryAI\Core\Enums\BackendType;

final class AIFactory
{
    /** Создаёт экземпляр бэкенда по типу. */
    public function createBackend(BackendType $type): Backend;

    /** Создаёт токенизатор. */
    public function createTokenizer(string $modelName): Tokenizer;

    /** Создаёт Vector Store. */
    public function createVectorStore(string $collection, int $dimension): VectorStore;

    /** Создаёт Model Hub. */
    public function createModelHub(): ModelHub;

    /** Создаёт Pipeline. */
    public function createPipeline(): Pipeline;

    /** Создаёт Embedder. */
    public function createEmbedder(string $modelName): Embedder;
}
```

---

## ПАКЕТ `llama-backend` — специфические интерфейсы

### Пространство имён: `FerryAI\LlamaBackend`

---

### 8. `Sampling\Sampler`

```php
namespace FerryAI\LlamaBackend\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;

interface Sampler
{
    /**
     * Выбирает следующий токен из логитов.
     *
     * @param float[] $logits  Выход модели (вероятности для каждого токена словаря)
     * @param SamplingParams $params
     *
     * @return int  ID выбранного токена
     */
    public function sample(array $logits, SamplingParams $params): int;
}
```

---

### 9. `Grammar\GbnfGrammar`

```php
namespace FerryAI\LlamaBackend\Grammar;

final readonly class GbnfGrammar
{
    /** Создаёт из GBNF-строки. */
    public static function fromString(string $gbnf): self;

    /** Создаёт из JSON Schema. */
    public static function fromJsonSchema(array $schema): self;

    /** Возвращает GBNF-строку. */
    public function toString(): string;
}
```

---

### 10. `LlamaContextParams` (value object)

```php
namespace FerryAI\LlamaBackend;

readonly class LlamaContextParams
{
    public function __construct(
        public int $nCtx = 2048,
        public int $nBatch = 512,
        public int $nGpuLayers = 0,
        public int $nThreads = 0,
        public bool $flashAttn = false,
        public bool $useMmap = true,
        public bool $useMlock = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array;
}
```

---

### 11. `LlamaModelParams` (value object)

```php
namespace FerryAI\LlamaBackend;

readonly class LlamaModelParams
{
    public function __construct(
        public int $nGpuLayers = 0,
        public bool $useMmap = true,
        public bool $useMlock = false,
        public bool $vocabOnly = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array;
}
```

---

## ПАКЕТ `pipeline` — встроенные стадии

### Пространство имён: `FerryAI\Pipeline`

---

### 12. Стадии

```php
namespace FerryAI\Pipeline;

use FerryAI\Core\Contracts\Stage;

// Токенизация текста
final class TokenizeStage implements Stage
{
    public function __construct(
        private \FerryAI\Core\Contracts\Tokenizer $tokenizer,
        private bool $addSpecialTokens = true,
    ) {}
    public function process(mixed $input): mixed;
    public function name(): string;
}

// Получение эмбеддингов
final class EmbedStage implements Stage
{
    public function __construct(
        private \FerryAI\Core\Contracts\Embedder $embedder,
    ) {}
    public function process(mixed $input): mixed;
    public function name(): string;
}

// L2-нормализация
final class NormalizeStage implements Stage
{
    public function process(mixed $input): mixed;
    public function name(): string;
}

// Сохранение в Vector Store
final class StoreStage implements Stage
{
    public function __construct(
        private \FerryAI\Core\Contracts\VectorStore $store,
    ) {}
    public function process(mixed $input): mixed;
    public function name(): string;
}

// Классификация
final class ClassifyStage implements Stage
{
    public function __construct(
        private \FerryAI\Core\Contracts\Backend $backend,
        private string $modelPath,
    ) {}
    public function process(mixed $input): mixed;
    public function name(): string;
}

// Фильтрация
final class FilterStage implements Stage
{
    public function __construct(
        private \Closure $predicate,
    ) {}
    public function process(mixed $input): mixed;
    public function name(): string;
}

// Пользовательская трансформация
final class TransformStage implements Stage
{
    public function __construct(
        private \Closure $transform,
        private string $stageName = 'transform',
    ) {}
    public function process(mixed $input): mixed;
    public function name(): string;
}

// Разбиение на чанки
final class ChunkStage implements Stage
{
    public function __construct(
        private \FerryAI\Core\Contracts\Tokenizer $tokenizer,
        private int $maxTokens = 512,
        private int $overlap = 64,
    ) {}
    public function process(mixed $input): mixed;
    public function name(): string;
}
```

---

## ПАКЕТ `vector` — специфические классы

### Пространство имён: `FerryAI\Vector`

---

### 13. `Collection`

```php
namespace FerryAI\Vector;

use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Core\Enums\DistanceMetric;
use FerryAI\Core\Enums\IndexType;
use FerryAI\Core\Enums\QuantizationType;

final class Collection implements VectorStore
{
    /**
     * @param string $name              Имя коллекции
     * @param int    $dimension         Размерность векторов
     * @param string $dbPath            Путь к SQLite-файлу
     * @param DistanceMetric $metric    Метрика сходства
     * @param IndexType $indexType      Тип индекса
     * @param QuantizationType $quant   Тип квантования
     * @param array|null $metadataSchema  JSON Schema для метаданных
     */
    public function __construct(
        string $name,
        int $dimension,
        string $dbPath,
        DistanceMetric $metric = DistanceMetric::COSINE,
        IndexType $indexType = IndexType::HNSW,
        QuantizationType $quant = QuantizationType::FLOAT32,
        ?array $metadataSchema = null,
    ) {}

    // Реализация VectorStore (см. выше) ...
}
```

---

## ПАКЕТ `embedding` — специфические классы

### Пространство имён: `FerryAI\Embedding`

---

### 14. `Embedder`

```php
namespace FerryAI\Embedding;

use FerryAI\Core\Contracts\Embedder as EmbedderContract;

final class Embedder implements EmbedderContract
{
    /**
     * @param string $modelName  Имя модели (встроенная или путь к .onnx)
     * @param string $pooling    Стратегия пулинга: cls, mean, eos, max
     * @param bool   $normalize  Применять L2-нормализацию
     */
    public function __construct(
        string $modelName,
        string $pooling = 'mean',
        bool $normalize = true,
    ) {}

    // Реализация Embedder (см. выше) ...
}
```

---

## ПАКЕТ `tensor` — фабрика

### Пространство имён: `FerryAI\Tensor`

---

### 15. `TensorFactory`

```php
namespace FerryAI\Tensor;

use FerryAI\Core\Contracts\Tensor;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\ValueObjects\Shape;

final class TensorFactory
{
    /** Создаёт тензор из PHP-массива. */
    public function fromArray(array $data, ?DType $dtype = null, ?Device $device = null): Tensor;

    /** Создаёт тензор заданной формы, заполненный нулями. */
    public function zeros(Shape $shape, DType $dtype = DType::Float32, ?Device $device = null): Tensor;

    /** Создаёт тензор заданной формы, заполненный единицами. */
    public function ones(Shape $shape, DType $dtype = DType::Float32, ?Device $device = null): Tensor;

    /** Создаёт тензор заданной формы со случайными значениями [0, 1). */
    public function random(Shape $shape, DType $dtype = DType::Float32, ?Device $device = null): Tensor;
}
```

---

## ПАКЕТ `model-hub` — специфические классы

### Пространство имён: `FerryAI\ModelHub`

---

### 16. `Hub`

```php
namespace FerryAI\ModelHub;

use FerryAI\Core\Contracts\ModelHub as ModelHubContract;

final class Hub implements ModelHubContract
{
    /**
     * @param string      $cacheDir  Директория кэша
     * @param string|null $hfToken   HuggingFace API token (для приватных моделей)
     */
    public function __construct(
        string $cacheDir,
        ?string $hfToken = null,
    ) {}

    /** Регистрирует пользовательскую модель (из локального файла). */
    public function register(string $name, string $path, ?string $sha256 = null): void;

    /** Возвращает список закэшированных моделей. */
    public function list(): array;

    /** Проверяет обновления моделей в HuggingFace Hub. */
    public function checkUpdates(): array;

    // Реализация ModelHub (см. выше) ...
}
```

---

## ПАКЕТ `platform` — мета-пакет

### Пространство имён: `FerryAI\Platform`

---

## Интеграции с фреймворками

### 17. Laravel

```php
namespace FerryAI\Laravel;

use Illuminate\Support\ServiceProvider;

final class AIServiceProvider extends ServiceProvider
{
    public function register(): void;
    public function boot(): void;
}

// Laravel Facade (если нужен)
// namespace FerryAI\Laravel\Facades;
// class AI extends \Illuminate\Support\Facades\Facade { ... }
```

---

### 18. Symfony

```php
namespace FerryAI\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AIBundle extends Bundle
{
    public function boot(): void;
}
```

---

> **Документ является неотъемлемой частью технического задания. Любая реализация обязана строго соответствовать сигнатурам, описанным здесь. Изменение сигнатур требует пересмотра спецификации.**
