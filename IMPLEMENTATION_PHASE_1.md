# FerryAI — План реализации: Фаза 1 (MVP)

> Версия: 1.0  
> Цель: работающий ONNX-инференс с удобным API  
> Длительность: 2–3 месяца  
> Пакеты: core, tensor, onnx-backend, ai  
> Файлов: 53  
> Коммит-стратегия: 1 коммит = 1 файл. Коммит-сообщение: `feat(package): add ClassName`

---

## 0. ПРЕДВАРИТЕЛЬНЫЕ УСЛОВИЯ

Перед началом Фазы 1 должны быть выполнены:

- [ ] Инициализировано монорепо `php-inference/` с корневым `composer.json`
- [ ] Создана директория `packages/`
- [ ] Установлен PHP 8.5+
- [ ] Включено расширение `ffi` (`ffi.enable=true` в php.ini)
- [ ] Включены расширения: `json`, `hash`, `fileinfo`
- [ ] Настроен phpunit для корневого проекта
- [ ] Прочитаны: `TECHNICAL_SPECIFICATION.md`, `INTERFACE_CONTRACTS.md`, `FILE_TREE.md`

---

## ШАГ 1: core/src/Enums/Device.php

**Назначение:** Перечисление устройств вычислений.

**Зависимости:** нет.

**Детали реализации:**
- string-backed enum с кейсами: `CPU = 'cpu'`, `CUDA = 'cuda'`, `ROCM = 'rocm'`, `METAL = 'metal'`, `VULKAN = 'vulkan'`, `DIRECTML = 'directml'`, `OPENVINO = 'openvino'`, `OPENCL = 'opencl'`, `AUTO = 'auto'`
- Статический метод `resolve(Device $preferred, array $available): Device`:
  - Если preferred != AUTO и preferred есть в available → вернуть preferred
  - Если preferred == AUTO: выбрать из available с максимальным `priority()` (CUDA > ROCM > METAL > VULKAN > DIRECTML > OPENVINO > OPENCL > CPU)
  - Если ни одно не доступно → выбросить DeviceNotAvailableException (AUTO)
- Метод `priority(): int` — CUDA=90, ROCM=80, METAL=70, VULKAN=60, DIRECTML=50, OPENVINO=40, OPENCL=30, CPU=10, AUTO=0

**Критерий приёмки:**
- Все 9 кейсов определены
- `Device::CPU->value === 'cpu'`
- `Device::tryFrom('cuda') === Device::CUDA`
- `Device::resolve(Device::AUTO, [Device::CPU]) === Device::CPU`
- `Device::resolve(Device::AUTO, [Device::CUDA, Device::CPU]) === Device::CUDA`
- `Device::CUDA->priority() > Device::CPU->priority()`

---

## ШАГ 2: core/src/Enums/DType.php

**Назначение:** Перечисление типов данных тензора.

**Зависимости:** нет.

**Детали реализации:**
- string-backed enum: `Float32 = 'float32'`, `Float16 = 'float16'`, `Int32 = 'int32'`, `Int64 = 'int64'`, `String = 'string'`
- Метод `sizeInBytes(): int` — 4 для Float32, 2 для Float16, 4 для Int32, 8 для Int64, 0 для String

**Критерий приёмки:**
- Все 5 кейсов определены
- `DType::Float32->sizeInBytes() === 4`
- `DType::String->sizeInBytes() === 0`

---

## ШАГ 3: core/src/Enums/BackendType.php

**Назначение:** Перечисление типов бэкендов.

**Зависимости:** нет.

**Детали реализации:**
- string-backed enum: `Onnx = 'onnx'`, `Llama = 'llama'`, `CpuNative = 'cpu_native'`

**Критерий приёмки:**
- Все 3 кейса определены
- `BackendType::Onnx->value === 'onnx'`

---

## ШАГ 4: core/src/Enums/TokenizerType.php

**Назначение:** Перечисление типов токенизаторов.

**Зависимости:** нет.

**Детали реализации:**
- string-backed enum: `BPE = 'bpe'`, `WordPiece = 'wordpiece'`, `SentencePiece = 'sentencepiece'`, `Unigram = 'unigram'`

**Критерий приёмки:**
- Все 4 кейса определены

---

## ШАГ 5: core/src/Enums/GraphOptimizationLevel.php

**Назначение:** Уровни оптимизации графа ONNX Runtime.

**Зависимости:** нет.

**Детали реализации:**
- string-backed enum: `DISABLE_ALL = 'disable_all'`, `BASIC = 'basic'`, `EXTENDED = 'extended'`, `ALL = 'all'`

**Критерий приёмки:**
- Все 4 кейса определены

---

## ШАГ 6: core/src/Enums/DistanceMetric.php

**Назначение:** Метрики сходства для векторного хранилища.

**Зависимости:** нет.

**Детали реализации:**
- string-backed enum: `COSINE = 'cosine'`, `EUCLIDEAN = 'euclidean'`, `DOT = 'dot'`

**Критерий приёмки:**
- Все 3 кейса определены

---

## ШАГ 7: core/src/Enums/IndexType.php

**Назначение:** Типы индексов для векторного хранилища.

**Зависимости:** нет.

**Детали реализации:**
- string-backed enum: `HNSW = 'hnsw'`, `IVF = 'ivf'`, `FLAT = 'flat'`

**Критерий приёмки:**
- Все 3 кейса определены

---

## ШАГ 8: core/src/Enums/QuantizationType.php

**Назначение:** Типы квантования векторов.

**Зависимости:** нет.

**Детали реализации:**
- string-backed enum: `FLOAT32 = 'float32'`, `FLOAT16 = 'float16'`, `INT8 = 'int8'`, `BINARY = 'binary'`

**Критерий приёмки:**
- Все 4 кейса определены

---

## ШАГ 9: core/src/ValueObjects/Shape.php

**Назначение:** Неизменяемый value object для формы тензора.

**Зависимости:** нет.

**Детали реализации:**
- `readonly class Shape implements \JsonSerializable`
- Конструктор: `public function __construct(public array $dimensions)`
- Валидация в конструкторе: все элементы — целые числа ≥ -1. Если есть < -1 (кроме -1) → `\InvalidArgumentException`
- `rank(): int` — count($dimensions)
- `size(): int` — array_product($dimensions). Если есть -1 → вернуть -1
- `dimension(int $axis): int` — $dimensions[$axis]. Если ось не существует → `\OutOfBoundsException`
- `isStatic(): bool` — нет ни одной -1
- `toArray(): array` — вернуть $dimensions
- `compatibleWith(Shape $other): bool` — broadcasting rules: идти справа налево, размерности должны совпадать или одна из них = 1 или -1
- `fromString(string $shape): self` — парсинг "1,3,224,224" → new self([1,3,224,224])
- `jsonSerialize(): array` — вернуть $dimensions
- `__toString(): string` — "1,3,224,224"

**Критерий приёмки:**
- `new Shape([1, 3, 224, 224])->rank() === 4`
- `new Shape([1, 3, -1])->isStatic() === false`
- `new Shape([2, 3])->size() === 6`
- `new Shape([1, -1])->size() === -1`
- `new Shape([-5])` → InvalidArgumentException
- `Shape::fromString('1,3,224,224')` эквивалентен `new Shape([1,3,224,224])`

---

## ШАГ 10: core/src/ValueObjects/ModelMetadata.php

**Назначение:** Метаданные загруженной модели.

**Зависимости:** нет.

**Детали реализации:**
- `readonly class ModelMetadata implements \JsonSerializable`
- Поля конструктора (все readonly, promoted):
  - `string $name` — имя модели
  - `string $version` — версия
  - `string $author` — автор
  - `string $license` — лицензия (MIT, Apache-2.0 и т.д.)
  - `array $tags` — теги (массив строк)
  - `int $sizeBytes` — размер в байтах
  - `?string $architecture = null` — архитектура
  - `?string $description = null` — описание
  - `?string $homepage = null` — ссылка
- `fromJson(string $json): self` — десериализация из JSON
- `toJson(): string` — сериализация в JSON (через json_encode с JSON_PRETTY_PRINT)
- `jsonSerialize(): array` — для JsonSerializable

**Критерий приёмки:**
- Все поля доступны только для чтения
- `fromJson(toJson())` возвращает идентичный объект

---

## ШАГ 11: core/src/ValueObjects/ChatMessage.php

**Назначение:** Сообщение в формате ChatML.

**Зависимости:** нет.

**Детали реализации:**
- `readonly class ChatMessage implements \JsonSerializable`
- Поля конструктора:
  - `string $role` — system | user | assistant | tool
  - `string|array $content` — текст или массив content-parts
  - `?string $name = null` — имя участника
  - `?string $toolCallId = null` — для role=tool
  - `?array $toolCalls = null` — для role=assistant
- Валидация: role должен быть одним из допустимых (system, user, assistant, tool) → иначе `\InvalidArgumentException`
- Статические фабрики:
  - `system(string $content): self` — создаёт сообщение с role=system
  - `user(string $content): self` — role=user
  - `assistant(string $content): self` — role=assistant
- `fromArray(array $data): self` — из ассоциативного массива (OpenAI-совместимый)
- `toArray(): array` — в ассоциативный массив (null-поля не включаются)
- `jsonSerialize(): array` — делегирует toArray()

**Критерий приёмки:**
- `ChatMessage::user('Hello')->role === 'user'`
- `ChatMessage::system('You are helpful')->role === 'system'`
- `new ChatMessage('invalid', 'x')` → InvalidArgumentException
- `fromArray(['role' => 'user', 'content' => 'Hi'])` → эквивалентно `user('Hi')`
- `toArray()` не содержит ключей со значением null

---

## ШАГ 12: core/src/ValueObjects/SamplingParams.php

**Назначение:** Параметры сэмплирования для LLM.

**Зависимости:** нет.

**Детали реализации:**
- `readonly class SamplingParams`
- Поля (все с значениями по умолчанию):
  - `float $temperature = 0.7`
  - `float $topP = 1.0`
  - `int $topK = 40`
  - `float $repetitionPenalty = 1.0`
  - `float $frequencyPenalty = 0.0`
  - `float $presencePenalty = 0.0`
  - `int $maxTokens = 2048`
  - `?array $stop = null` — массив стоп-строк
  - `?int $seed = null` — seed для воспроизводимости
- Валидация:
  - temperature: 0.0..2.0
  - topP: 0.0..1.0
  - topK: ≥ 1
  - repetitionPenalty: ≥ 0.0
  - maxTokens: ≥ 1

**Критерий приёмки:**
- Все поля имеют значения по умолчанию
- `new SamplingParams(temperature: 3.0)` → InvalidArgumentException
- Все поля readonly

---

## ШАГ 13: core/src/ValueObjects/GenerationResult.php

**Назначение:** Результат генерации LLM.

**Зависимости:** нет.

**Детали реализации:**
- `readonly class GenerationResult`
- Поля:
  - `string $text` — сгенерированный текст
  - `int $tokensGenerated` — число сгенерированных токенов
  - `int $tokensPrompt` — число токенов в промпте
  - `int $tokensTotal` — общее число токенов
  - `float $durationMs` — длительность в миллисекундах
  - `?array $logprobs = null` — лог-вероятности токенов

**Критерий приёмки:**
- Все поля readonly
- Объект создаётся без ошибок

---

## ШАГ 14: core/src/ValueObjects/EmbeddingResult.php

**Назначение:** Результат эмбеддинга.

**Зависимости:** нет.

**Детали реализации:**
- `readonly class EmbeddingResult`
- Поля:
  - `array $vector` — массив float
  - `int $dimension` — размерность (равна count($vector))
  - `string $modelName` — имя модели
- Валидация: dimension === count($vector). Если нет → InvalidArgumentException.

**Критерий приёмки:**
- `new EmbeddingResult([1.0, 2.0, 3.0], 3, 'test')` — OK
- `new EmbeddingResult([1.0, 2.0], 3, 'test')` — InvalidArgumentException

---

## ШАГ 15: core/src/ValueObjects/ClassificationResult.php

**Назначение:** Результат классификации.

**Зависимости:** нет.

**Детали реализации:**
- `readonly class ClassificationResult`
- Поля:
  - `string $label` — предсказанная метка
  - `float $confidence` — уверенность (0.0..1.0)
  - `array $allScores = []` — [label => score]
- Валидация: confidence в диапазоне [0.0, 1.0]

**Критерий приёмки:**
- Объект создаётся
- confidence 1.5 → InvalidArgumentException

---

## ШАГ 16: core/src/Exception/FerryAIException.php

**Назначение:** Базовое исключение всей платформы.

**Зависимости:** нет.

**Детали реализации:**
- `class FerryAIException extends \RuntimeException`
- Конструктор принимает: `string $message`, `int $code = 0`, `?\Throwable $previous = null`
- Метод `errorCode(): string` — возвращает строковый код вида `FERRY_AI_` + код класса-наследника. Для базового: `FERRY_AI_ERROR`

**Критерий приёмки:**
- `$e = new FerryAIException('test'); $e->errorCode() === 'FERRY_AI_ERROR'`
- `$e instanceof \RuntimeException`

---

## ШАГ 17: core/src/Exception/BackendNotAvailableException.php

**Назначение:** Бэкенд не может быть загружен.

**Зависимости:** Шаг 16 (FerryAIException).

**Детали реализации:**
- `class BackendNotAvailableException extends FerryAIException`
- Конструктор: `__construct(string $backendType, ?string $reason = null)`
  - Формирует сообщение: "Backend '$backendType' is not available. $reason"
- `backendType(): string` — возвращает тип бэкенда
- `reason(): ?string` — возвращает причину
- `errorCode(): string` — `FERRY_AI_BACKEND_NOT_AVAILABLE`

**Критерий приёмки:**
- Исключение выбрасывается и содержит корректный backendType

---

## ШАГ 18: core/src/Exception/ModelNotFoundException.php

**Назначение:** Модель не найдена.

**Зависимости:** Шаг 16 (FerryAIException).

**Детали реализации:**
- `class ModelNotFoundException extends FerryAIException`
- Конструктор: `__construct(string $source)` — сообщение: "Model not found: $source"
- `source(): string`
- `errorCode(): string` — `FERRY_AI_MODEL_NOT_FOUND`

---

## ШАГ 19: core/src/Exception/ModelLoadException.php

**Назначение:** Ошибка загрузки модели.

**Зависимости:** Шаг 16 (FerryAIException).

**Детали реализации:**
- `class ModelLoadException extends FerryAIException`
- Конструктор: `__construct(string $path, string $reason)` — "Failed to load model '$path': $reason"
- `path(): string`, `reason(): string`
- `errorCode(): string` — `FERRY_AI_MODEL_LOAD`

---

## ШАГ 20: core/src/Exception/InferenceException.php

**Назначение:** Ошибка во время инференса.

**Зависимости:** Шаг 16 (FerryAIException).

**Детали реализации:**
- `class InferenceException extends FerryAIException`
- Конструктор: `__construct(string $message)` — сообщение передаётся как есть
- `errorCode(): string` — `FERRY_AI_INFERENCE`

---

## ШАГ 21: core/src/Exception/ShapeMismatchException.php

**Назначение:** Несоответствие формы тензора.

**Зависимости:** Шаг 16 (FerryAIException), Шаг 9 (Shape).

**Детали реализации:**
- `class ShapeMismatchException extends FerryAIException`
- Конструктор: `__construct(Shape $expected, Shape $actual)` — "Shape mismatch: expected $expected, got $actual"
- `expected(): Shape`, `actual(): Shape`
- `errorCode(): string` — `FERRY_AI_SHAPE_MISMATCH`

**Критерий приёмки:**
- Исключение содержит оба Shape объекта

---

## ШАГ 22: core/src/Exception/DeviceNotAvailableException.php

**Назначение:** Устройство недоступно.

**Зависимости:** Шаг 16 (FerryAIException), Шаг 1 (Device).

**Детали реализации:**
- `class DeviceNotAvailableException extends FerryAIException`
- Конструктор: `__construct(Device $requested)` — "Device '$requested->value' is not available"
- `requestedDevice(): Device`
- `errorCode(): string` — `FERRY_AI_DEVICE_NOT_AVAILABLE`

---

## ШАГ 23: core/src/Exception/TokenizerException.php

**Назначение:** Ошибка токенизации.

**Зависимости:** Шаг 16 (FerryAIException).

**Детали реализации:**
- `class TokenizerException extends FerryAIException`
- Конструктор: `__construct(string $reason)`
- `errorCode(): string` — `FERRY_AI_TOKENIZER`

---

## ШАГ 24: core/src/Exception/ConfigurationException.php

**Назначение:** Неверная конфигурация.

**Зависимости:** Шаг 16 (FerryAIException).

**Детали реализации:**
- `class ConfigurationException extends FerryAIException`
- Конструктор: `__construct(string $key, string $reason)` — "Configuration error for '$key': $reason"
- `configKey(): string`
- `errorCode(): string` — `FERRY_AI_CONFIGURATION`

---

## ШАГ 25: core/src/AIConfig.php

**Назначение:** Управление глобальной конфигурацией.

**Зависимости:** Шаг 1 (Device), Шаг 3 (BackendType).

**Детали реализации:**
- `final class AIConfig implements \ArrayAccess`
- Хранит внутренний массив `$config` со значениями по умолчанию:
  - `backend` => `'auto'`
  - `device` => `'auto'`
  - `model_cache` => `sys_get_temp_dir() . '/ferry-ai-models'`
  - `max_tokens` => `2048`
  - `temperature` => `0.7`
  - `top_p` => `1.0`
  - `stream_timeout` => `30`
  - `verify_signatures` => `true`
  - `log_level` => `'warning'`
  - `backends` => `[]`
- `fromArray(array $config): self` — мёржит переданный массив с дефолтами
- `toArray(): array`
- `get(string $key, $default = null): mixed` — поддерживает dot-нотацию: `get('backends.onnx.providers')`
- `set(string $key, mixed $value): self` — immutable: возвращает новый экземпляр
- `has(string $key): bool` — поддерживает dot-нотацию
- Типизированные геттеры:
  - `backend(): BackendType` — парсит строку в BackendType: `onnx`→Onnx, `llama`→Llama, `cpu`→CpuNative (значение enum `cpu_native`), `auto`→автоопределение доступных
  - `device(): Device` — парсит строку в Device
  - `modelCache(): string`
  - `maxTokens(): int`
  - `temperature(): float`
  - `topP(): float`
  - `streamTimeout(): int`
  - `verifySignatures(): bool`
  - `logLevel(): string`
  - `backendsConfig(): array` — возвращает `backends` ключ
- ArrayAccess делегирует к get/set/has/unset на внутреннем массиве

**Критерий приёмки:**
- `AIConfig::fromArray([])->backend()` — работает без ошибок (auto → CPU)
- Dot-нотация: `get('backends.onnx.providers')` работает
- `set('temperature', 0.9)->temperature() === 0.9`
- Иммутабельность: `set()` возвращает новый объект, не меняя исходный

---

## ШАГ 26: core/src/Contracts/Stage.php

**Назначение:** Интерфейс одной стадии пайплайна.

**Зависимости:** нет.

**Детали реализации:**
- `interface Stage`
- `process(mixed $input): mixed` — обработать один элемент. Может выбросить `\RuntimeException`
- `name(): string` — имя стадии для логирования

**Критерий приёмки:**
- Интерфейс содержит ровно 2 метода

---

## ШАГ 27: core/src/Contracts/Backend.php

**Назначение:** Контракт бэкенд-драйвера.

**Зависимости:** Шаг 1 (Device), Шаг 10 (ModelMetadata). Также зависит от Contracts\Model (циклическая ссылка через load(), это нормально для PHP).

**Детали реализации:**
- `interface Backend`
- Методы (ровно как в INTERFACE_CONTRACTS.md, раздел 1.1):
  - `availableDevices(): array` — возвращает `Device[]`
  - `load(string $source, ?Device $device = null): Model`
    - Если $device === null → использовать автоопределение
    - Возможные $source: путь к файлу, URL (https://), HF-идентификатор (hf://)
  - `version(): string` — версия нативного движка
  - `isAvailable(): bool` — проверка доступности (наличие shared library)

**Критерий приёмки:**
- Интерфейс содержит ровно 4 метода
- Сигнатуры точно совпадают с INTERFACE_CONTRACTS.md

---

## ШАГ 28: core/src/Contracts/Model.php

**Назначение:** Контракт загруженной модели.

**Зависимости:** Шаг 1 (Device), Шаг 10 (ModelMetadata).

**Детали реализации:**
- `interface Model`
- Методы (ровно как в INTERFACE_CONTRACTS.md, раздел 1.2):
  - `run(array $inputs): array` — инференс
  - `inputs(): array` — метаданные входов: `[name => ['name' => ..., 'shape' => [...], 'dtype' => '...']]`
  - `outputs(): array` — метаданные выходов (такой же формат)
  - `metadata(): ModelMetadata`
  - `device(): Device`
  - `unload(): void` — освобождение нативных ресурсов

**Критерий приёмки:**
- Интерфейс содержит ровно 6 методов

---

## ШАГ 29: core/src/Contracts/Tensor.php

**Назначение:** Контракт многомерного тензора.

**Зависимости:** Шаг 1 (Device), Шаг 2 (DType), Шаг 9 (Shape).

**Детали реализации:**
- `interface Tensor extends \ArrayAccess, \Countable, \JsonSerializable`
- Методы (ровно как в INTERFACE_CONTRACTS.md, раздел 1.3):
  - `shape(): Shape`
  - `dtype(): DType`
  - `to(Device $device): self` — перенос на устройство (новый тензор)
  - `device(): Device`
  - `toArray(): array` — экспорт в PHP-массив
  - `data(): mixed` — сырой FFI-буфер
  - `add(self $other): self` — поэлементное сложение
  - `sub(self $other): self` — вычитание
  - `mul(self $other): self` — умножение
  - `matmul(self $other): self` — матричное умножение
  - `transpose(?array $axes = null): self` — транспонирование
  - `reshape(Shape $newShape): self` — изменение формы
  - `slice(array $slices): self` — срез
  - `__clone(): void` — для PHP 8.5 clone with
  - ArrayAccess: `offsetExists`, `offsetGet`, `offsetSet`, `offsetUnset`
  - Countable: `count()`
  - JsonSerializable: `jsonSerialize()`
  - `__serialize(): array`
  - `__unserialize(array $data): void`

**Критерий приёмки:**
- Интерфейс содержит все перечисленные методы
- extends ArrayAccess, Countable, JsonSerializable

---

## ШАГ 30: core/src/Contracts/Tokenizer.php

**Назначение:** Контракт токенизатора.

**Зависимости:** Шаг 4 (TokenizerType).

**Детали реализации:**
- `interface Tokenizer`
- Методы (ровно как в INTERFACE_CONTRACTS.md, раздел 1.4):
  - `encode(string $text, bool $addSpecialTokens = true): array`
  - `decode(array $ids): string`
  - `encodeBatch(array $texts, bool $padToMaxLength = true): array`
  - `vocabSize(): int`
  - `type(): TokenizerType`
  - `specialTokenId(string $tokenName): ?int`
  - `specialTokens(): array`
  - `countTokens(string $text): int`
  - `chunk(string $text, int $maxTokens = 512, int $overlap = 64): array`

**Критерий приёмки:**
- Интерфейс содержит ровно 9 методов

---

## ШАГ 31: core/src/Contracts/Embedder.php

**Назначение:** Контракт эмбеддера.

**Зависимости:** нет.

**Детали реализации:**
- `interface Embedder`
- Методы (ровно как в INTERFACE_CONTRACTS.md, раздел 1.5):
  - `embed(string $text): array`
  - `embedBatch(array $texts): array`
  - `dimension(): int`
  - `normalize(array $vector): array`
  - `cosineSimilarity(array $a, array $b): float`
  - `modelName(): string`

**Критерий приёмки:**
- Интерфейс содержит ровно 6 методов

---

## ШАГ 32: core/src/Contracts/VectorStore.php

**Назначение:** Контракт векторного хранилища.

**Зависимости:** нет.

**Детали реализации:**
- `interface VectorStore`
- Методы (ровно как в INTERFACE_CONTRACTS.md, раздел 1.6):
  - `add(string $id, array $vector, ?array $metadata = null): void`
  - `addBatch(array $items): void`
  - `search(array $queryVector, int $k = 10, ?array $filter = null): array`
  - `delete(string $id): void`
  - `deleteByFilter(array $filter): int`
  - `update(string $id, ?array $vector = null, ?array $metadata = null): void`
  - `count(): int`
  - `dimension(): int`
  - `collectionName(): string`
  - `iterator(): \Iterator`
  - `export(): array`
  - `clear(): void`

**Критерий приёмки:**
- Интерфейс содержит ровно 12 методов

---

## ШАГ 33: core/src/Contracts/Pipeline.php

**Назначение:** Контракт конвейера обработки.

**Зависимости:** Шаг 26 (Stage).

**Детали реализации:**
- `interface Pipeline`
- Методы (ровно как в INTERFACE_CONTRACTS.md, раздел 1.7):
  - `pipe(Stage $stage): self`
  - `run(mixed $input): \Generator`
  - `stages(): array` — возвращает Stage[]
  - `__invoke(mixed $input): \Generator` — поддержка Pipe Operator

**Критерий приёмки:**
- Интерфейс содержит ровно 4 метода

---

## ШАГ 34: core/src/Contracts/ModelHub.php

**Назначение:** Контракт хаба моделей.

**Зависимости:** Шаг 10 (ModelMetadata).

**Детали реализации:**
- `interface ModelHub`
- Методы (ровно как в INTERFACE_CONTRACTS.md, раздел 1.9):
  - `download(string $modelId, ?string $version = null): string`
  - `cached(string $modelId, ?string $version = null): ?string`
  - `verify(string $path, ?string $sha256 = null, ?string $signature = null): bool`
  - `introspect(string $path): ModelMetadata`
  - `downloadWithProgress(string $modelId, ?string $version = null): \Generator`
  - `remove(string $modelId, ?string $version = null): void`
  - `prune(?int $maxSizeBytes = null): int`
  - `cacheSize(): int`
  - `warmup(array $modelIds): void`

**Критерий приёмки:**
- Интерфейс содержит ровно 9 методов

---

## ШАГ 35: core/src/Contracts/DataFrame.php

**Назначение:** Контракт табличных данных (отложен).

**Зависимости:** Шаг 29 (Tensor).

**Детали реализации:**
- `interface DataFrame extends \Iterator, \Countable`
- Методы (ровно как в INTERFACE_CONTRACTS.md, раздел 1.10)

**Критерий приёмки:**
- Интерфейс компилируется без ошибок

---

## ШАГ 36–41: onnx-backend Provider-ы

### ШАГ 36: onnx-backend/src/Provider/ExecutionProvider.php

**Назначение:** Интерфейс провайдера исполнения ONNX Runtime.

**Зависимости:** core/src/Enums/Device.php.

**Детали реализации:**
- `interface ExecutionProvider`
- `name(): string` — имя провайдера (CPUExecutionProvider, CUDAExecutionProvider и т.д.)
- `device(): Device` — соответствующее устройство
- `isAvailable(): bool` — проверка доступности
- `configure(): array` — возвращает массив настроек для OrtSessionOptions

---

### ШАГ 37: onnx-backend/src/Provider/CpuProvider.php

**Зависимости:** Шаг 36.

**Детали реализации:**
- `class CpuProvider implements ExecutionProvider`
- `name(): string` — `'CPUExecutionProvider'`
- `device(): Device` — `Device::CPU`
- `isAvailable(): bool` — всегда true
- `configure(): array` — возвращает пустой массив (CPU провайдер не требует настроек)

---

### ШАГ 38: onnx-backend/src/Provider/CudaProvider.php

**Зависимости:** Шаг 36.

**Детали реализации:**
- `class CudaProvider implements ExecutionProvider`
- `name(): string` — `'CUDAExecutionProvider'`
- `device(): Device` — `Device::CUDA`
- `isAvailable(): bool` — проверяет наличие CUDA через FFI (cudaGetDeviceCount > 0)
- Конструктор: `__construct(int $deviceId = 0, ?int $memoryLimit = null)`
- `configure(): array` — массив с device_id и (опционально) memory_limit

---

### ШАГ 39–41: TensorRtProvider, CoreMlProvider, DirectMlProvider

Аналогичны CudaProvider. Каждый:
- Имплементирует ExecutionProvider
- Возвращает соответствующее имя провайдера
- `isAvailable()` проверяет наличие через FFI
- `configure()` возвращает настройки
- `device(): Device` — маппинг провайдера на устройство: TensorRtProvider → `Device::CUDA`, CoreMlProvider → `Device::METAL`, DirectMlProvider → `Device::DIRECTML`
- CoreMlProvider доступен только на macOS
- DirectMlProvider доступен только на Windows

> Провайдеры `OpenVinoProvider` (Intel → `Device::OPENVINO`) и `RocmProvider` (AMD → `Device::ROCM`) — расширение аппаратной поддержки, создаются в Фазе 4 (см. IMPLEMENTATION_PHASE_4). В Фазе 1 достаточно 5 провайдеров.
>
> **Важно:** `phpmlkit/onnxruntime` отдаёт только CPU/CUDA/CoreML/TensorRT. `DirectMlProvider` (как и OpenVINO/ROCm) — задел на будущее: реальная поддержка DirectML **планируется** и потребует собственного FFI поверх ONNX Runtime; до тех пор `isAvailable()` возвращает false.

---

## ШАГ 42: onnx-backend/src/OnnxRuntimeFactory.php

**Назначение:** Фабрика для создания OrtEnvironment и OrtSession.

**Зависимости:** Шаг 36 (ExecutionProvider), phpmlkit/onnxruntime.

**Детали реализации:**
- `class OnnxRuntimeFactory`
- `createEnvironment(): OrtEnvironment` — создаёт окружение ONNX Runtime
- `createSession(string $modelPath, ExecutionProvider ...$providers): OrtSession`
  - Загружает модель
  - Настраивает провайдеры
  - Устанавливает уровень оптимизации графа
- `availableProviders(): array` — список доступных провайдеров в системе
- `defaultProvider(): ExecutionProvider` — авто-выбор лучшего: CUDA > CPU

---

## ШАГ 43: onnx-backend/src/OnnxTensor.php

**Назначение:** Реализация интерфейса Tensor над OrtValue.

**Зависимости:** core Contracts\Tensor, phpmlkit/onnxruntime.

**Детали реализации:**
- `class OnnxTensor implements Tensor`
- Хранит OrtValue (из phpmlkit/onnxruntime)
- Хранит Shape и DType
- `shape(): Shape` — возвращает Shape
- `dtype(): DType` — маппит ONNX-тип на DType enum
- `to(Device $device): self` — если уже на устройстве → $this; иначе → DeviceNotAvailableException (ONNX тензор не может мигрировать между устройствами после создания; перенос делается на уровне бэкенда)
- `device(): Device` — возвращает устройство тензора
- `toArray(): array` — конвертирует OrtValue в PHP-массив (через phpmlkit)
- `data(): mixed` — возвращает сырой OrtValue
- `add/sub/mul/matmul` — НЕ реализованы в OnnxTensor (тензорные операции — ответственность tensor-пакета, который делегирует бэкенду)
  - Эти методы выбрасывают `\BadMethodCallException` с сообщением "Use backend for tensor operations"
- `transpose/reshape/slice` — аналогично не реализованы
- ArrayAccess делегирует к индексации через OrtValue
- `count()` — общее число элементов (shape->size())
- `jsonSerialize()` — экспорт в toArray()

**ВАЖНО:** OnnxTensor — это «голый» тензор, обёртка над OrtValue. Он не реализует арифметику. Арифметика делается через TensorFactory и BackedTensor из пакета tensor.

---

## ШАГ 44: onnx-backend/src/OnnxModel.php

**Назначение:** Реализация интерфейса Model над OrtSession.

**Зависимости:** core Contracts\Model, Шаг 43 (OnnxTensor), phpmlkit/onnxruntime.

**Детали реализации:**
- `class OnnxModel implements Model`
- Хранит OrtSession и ModelMetadata
- Конструктор: `__construct(OrtSession $session, ModelMetadata $metadata, Device $device)`
- `run(array $inputs): array`
  - Для каждого входа:
    - Если значение — массив PHP → создать OrtValue через phpmlkit
    - Если значение — OnnxTensor → взять OrtValue напрямую (zero-copy)
  - Вызвать `$session->run($inputOrtValues)`
  - Результат — массив OrtValue → обернуть в OnnxTensor
  - Вернуть ассоциативный массив output_name => OnnxTensor
- `inputs(): array` — читает метаданные из сессии
- `outputs(): array` — аналогично
- `metadata(): ModelMetadata`
- `device(): Device`
- `unload(): void` — вызывает session->release() и освобождает ресурсы

**Критерий приёмки:**
- `run()` принимает PHP-массивы и OnnxTensor'ы
- Возвращает ассоциативный массив OnnxTensor'ов
- После `unload()` повторный `run()` → исключение

---

## ШАГ 45: onnx-backend/src/OnnxBackend.php

**Назначение:** Реализация интерфейса Backend через ONNX Runtime.

**Зависимости:** core Contracts\Backend, Шаг 42 (OnnxRuntimeFactory), Шаг 44 (OnnxModel).

**Детали реализации:**
- `class OnnxBackend implements Backend`
- Хранит OnnxRuntimeFactory
- `availableDevices(): array`
  - Проверяет доступные провайдеры
  - Возвращает соответствующие Device: [CUDA, CPU] или [CPU]
- `load(string $source, ?Device $device = null): Model`
  - Определяет тип источника (file, URL, HF)
  - Если URL или HF — скачивает через model-hub (если доступен) или выбрасывает исключение с просьбой скачать вручную
  - Создаёт OrtSession через OnnxRuntimeFactory
  - Читает метаданные (имя, версию — из имени файла)
  - Возвращает OnnxModel
- `version(): string` — получает версию ONNX Runtime через OrtGetApiBase()
- `isAvailable(): bool` — проверяет, загружена ли shared library ONNX Runtime

**Критерий приёмки:**
- `isAvailable()` возвращает true при наличии библиотеки
- `load('/path/to/model.onnx')` возвращает OnnxModel
- `availableDevices()` возвращает непустой массив

---

## ШАГ 46: tensor/src/ArrayTensor.php

**Назначение:** Pure-PHP реализация Tensor (для CPU-fallback).

**Зависимости:** core Contracts\Tensor, Шаг 9 (Shape), Шаг 1 (Device), Шаг 2 (DType).

**Детали реализации:**
- `class ArrayTensor implements Tensor`
- Хранит многомерный PHP-массив
- Полная реализация ВСЕХ методов интерфейса Tensor:
  - Арифметика реализована на PHP (поэлементно)
  - matmul — через вложенные циклы (только для маленьких тензоров)
  - transpose — перестановка элементов
  - reshape — проверка совместимости, перепаковка
  - slice — array_slice по осям
  - ArrayAccess, Countable, JsonSerializable — полная реализация
  - `to(Device $device)` — если device == CPU → $this; иначе → исключение (ArrayTensor не поддерживает GPU)
  - `data()` — возвращает внутренний массив
- `device()` всегда возвращает Device::CPU

**Критерий приёмки:**
- Все методы интерфейса реализованы
- Арифметические операции возвращают новый ArrayTensor
- matmul для [2,3] × [3,2] даёт [2,2]

---

## ШАГ 47: tensor/src/BackedTensor.php

**Назначение:** Тензор, делегирующий операции бэкенду.

**Зависимости:** core Contracts\Tensor, Шаг 9 (Shape), Шаг 1 (Device), Шаг 2 (DType), core Contracts\Backend.

**Детали реализации:**
- `class BackedTensor implements Tensor`
- Хранит внутренний Tensor (OnnxTensor, ArrayTensor или любой другой)
- Хранит ссылку на Backend (для арифметических операций)
- Все методы делегируют внутреннему тензору
- Арифметические методы (`add`, `sub`, `mul`, `matmul`, `transpose`, `reshape`, `slice`) делегируют бэкенду:
  - `$this->backend->runOperation('add', $this, $other)`
  - Но на фазе 1 бэкенд не обязан реализовывать runOperation — можно выбрасывать исключение

**Критерий приёмки:**
- Проксирует все вызовы внутреннему тензору
- Конструктор принимает Tensor и Backend

---

## ШАГ 48: tensor/src/TensorFactory.php

**Назначение:** Фабрика для создания тензоров.

**Зависимости:** core Contracts\Tensor, Шаг 46 (ArrayTensor), Шаг 47 (BackedTensor), Шаг 9 (Shape), Шаг 1 (Device), Шаг 2 (DType).

**Детали реализации:**
- `final class TensorFactory`
- `fromArray(array $data, ?DType $dtype = null, ?Device $device = null): Tensor`
  - Автоопределение формы (рекурсивный обход массива)
  - Создаёт ArrayTensor
- `zeros(Shape $shape, DType $dtype = DType::Float32, ?Device $device = null): Tensor`
  - Создаёт многомерный массив нулей → ArrayTensor
- `ones(Shape $shape, DType $dtype = DType::Float32, ?Device $device = null): Tensor`
  - Аналогично с единицами
- `random(Shape $shape, DType $dtype = DType::Float32, ?Device $device = null): Tensor`
  - Использует `\Random\Randomizer` из PHP 8.2
  - Заполняет случайными числами [0, 1)

**Критерий приёмки:**
- `fromArray([[1,2],[3,4]])->shape()` → Shape([2,2])
- `zeros(Shape([3,3]))->toArray()` — массив 3×3 нулей

---

## ШАГ 49: ai/src/BackendRegistry.php

**Назначение:** Центральный реестр бэкендов.

**Зависимости:** core Contracts\Backend, core Enums\BackendType.

**Детали реализации:**
- `class BackendRegistry`
- Хранит массив [BackendType => Backend]
- `register(BackendType $type, Backend $backend): void` — регистрация бэкенда
- `get(BackendType $type): Backend` — получение зарегистрированного бэкенда. Если не зарегистрирован → BackendNotAvailableException
- `has(BackendType $type): bool` — проверка регистрации
- `all(): array` — все зарегистрированные бэкенды
- `autoDetect(): BackendType` — определяет лучший доступный бэкенд:
  1. Llama (если зарегистрирован и доступен)
  2. Onnx (если зарегистрирован и доступен)
  3. CpuNative (если зарегистрирован и доступен)

**Критерий приёмки:**
- После register → has() возвращает true
- get() для незарегистрированного → исключение
- autoDetect() возвращает первый доступный

---

## ШАГ 50: ai/src/TaskRouter.php

**Назначение:** Роутинг задач по бэкендам.

**Зависимости:** Шаг 49 (BackendRegistry), core Enums\BackendType.

**Детали реализации:**
- `class TaskRouter`
- Хранит BackendRegistry
- `routeForChat(): BackendType` — Llama если доступен, иначе Onnx
- `routeForEmbedding(): BackendType` — Onnx
- `routeForClassification(): BackendType` — Onnx если доступен, иначе CpuNative
- `routeForPrediction(): BackendType` — CpuNative
- `routeFor(string $task): BackendType`:
  - chat → routeForChat()
  - stream → routeForChat()
  - embed → routeForEmbedding()
  - classify → routeForClassification()
  - moderate → routeForClassification()
  - predict → routeForPrediction()
  - similarity → routeForEmbedding()
  - default → autoDetect()

**Критерий приёмки:**
- Роутинг возвращает корректный BackendType
- Неизвестная задача → autoDetect()

---

## ШАГ 51: ai/src/AIFactory.php

**Назначение:** Фабрика для создания всех компонентов.

**Зависимости:** Все бэкенды, токенизатор, embedding, pipeline, model-hub, vector (все пакеты Фазы 1).

**Детали реализации:**
- `class AIFactory`
- Хранит AIConfig и BackendRegistry
- Конструктор принимает AIConfig (опционально)
- `createBackend(BackendType $type): Backend`
  - Onnx → new OnnxBackend()
  - Llama → BackendNotAvailableException (в фазе 1)
  - CpuNative → BackendNotAvailableException (в фазе 1)
- Заглушки для методов, которые появятся в следующих фазах:
  - `createTokenizer(...)` — выбрасывает исключение с сообщением "Not implemented in Phase 1"
  - `createVectorStore(...)` — аналогично
  - `createModelHub(...)` — аналогично
  - `createPipeline(...)` — аналогично
  - `createEmbedder(...)` — аналогично

**Критерий приёмки:**
- `createBackend(BackendType::Onnx)` возвращает OnnxBackend
- `createBackend(BackendType::Llama)` → исключение

---

## ШАГ 52: ai/src/AI.php (Фасад) + ai/src/StreamResponse.php (заглушка)

**Назначение:** Главная точка входа для пользователя.

**Зависимости:** Шаг 50 (TaskRouter), Шаг 51 (AIFactory), Шаг 25 (AIConfig), все Value Objects, все Contracts.

**Детали реализации:**
- `final class AI` — все методы статические
- Хранит статическое состояние:
  - `?AIConfig $config = null`
  - `?BackendRegistry $registry = null`
  - `?Backend $activeBackend = null`
  - `?BackendType $activeBackendType = null`
  - `?Device $activeDevice = null`
- `config(array $config): void` — создаёт AIConfig, инициализирует BackendRegistry, регистрирует OnnxBackend
- `warmup(array $modelIds): void` — заглушка в фазе 1
- `reset(): void` — сбрасывает всё состояние
- `resetBackend(string $name): void` — сбрасывает конкретный бэкенд
- `backend(string $name): void` — переключает активный бэкенд
- `device(string $device): void` — переключает активное устройство
- `chat(array $messages, ?array $options = null): GenerationResult`
  - Определяет бэкенд через TaskRouter
  - Если LlamaBackend → исключение с сообщением "Chat requires llama-backend. Install Phase 2."
  - Если OnnxBackend → загружает модель, токенизирует, выполняет инференс
  - На фазе 1: исключение "Chat not available. Use ONNX for embedding/classification."
- `embed(string|array $input): EmbeddingResult|array`
  - Определяет модель для эмбеддингов (из конфигурации или встроенную)
  - Загружает модель через OnnxBackend
  - Токенизирует (заглушка в фазе 1 — простая разбивка по пробелам или требование tokenizer)
  - Выполняет инференс
  - Извлекает эмбеддинг (mean pooling по выходу)
  - Возвращает EmbeddingResult (или массив для батча)
- `similarity(string $a, string $b): float`
  - Вызывает embed для каждого → cosineSimilarity
- `classify(mixed $input): ClassificationResult`
  - Загружает модель классификации
  - Выполняет инференс
  - Находит argmax по выходу
  - Возвращает ClassificationResult
- Заглушки для фаз 2+:
  - `stream()` → исключение
  - `moderate()` → исключение
  - `predict()` → исключение
  - `pipeline()` → исключение
  - `vector()` → исключение
  - `hub()` → исключение
  - `tokenizer()` → исключение

**Сопутствующий файл — `ai/src/StreamResponse.php` (заглушка):**
- Класс создаётся вместе с AI.php
- Методы `create()`, `toSse()`, `toNdjson()` — заглушки: выбрасывают исключение "StreamResponse requires Phase 2 (llama-backend)."
- Зависимость: PSR-7 (psr/http-message, опционально)
- В Фазе 4 шаг 125 дорабатывается до реальной SSE/NDJSON-реализации

**Критерий приёмки:**
- `AI::config([...])` не выбрасывает исключений
- `AI::embed('hello world')` возвращает EmbeddingResult (при наличии ONNX-модели)
- `AI::classify($text)` возвращает ClassificationResult
- `AI::stream(...)` → исключение с понятным сообщением

---

## ИНТЕГРАЦИОННЫЙ ТЕСТ ФАЗЫ 1

После создания всех 53 файлов выполнить:

```bash
# 1. Автозагрузка
composer dump-autoload

# 2. Тест core
php vendor/bin/phpunit packages/core --no-configuration --bootstrap vendor/autoload.php

# 3. Тест: создание Shape
php -r "
require 'vendor/autoload.php';
use FerryAI\Core\ValueObjects\Shape;
\$s = new Shape([1, 3, 224, 224]);
assert(\$s->rank() === 4);
assert(\$s->size() === 150528);
echo 'Shape OK\n';
"

# 4. Тест: AIConfig
php -r "
require 'vendor/autoload.php';
use FerryAI\Core\AIConfig;
use FerryAI\Core\Enums\Device;
\$c = AIConfig::fromArray(['device' => 'cuda']);
assert(\$c->device() === Device::CUDA);
echo 'AIConfig OK\n';
"

# 5. Тест: полный цикл ONNX
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['backend' => 'onnx', 'device' => 'cpu']);
\$result = AI::embed('Hello world');
assert(is_array(\$result->vector));
assert(count(\$result->vector) > 0);
echo 'Embedding OK, dimension: ' . \$result->dimension . PHP_EOL;
"

# 6. Тест: классификация
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['backend' => 'onnx']);
\$result = AI::classify('This is a test text');
assert(strlen(\$result->label) > 0);
echo 'Classification OK, label: ' . \$result->label . PHP_EOL;
"
```

**Все 6 тестов должны пройти без ошибок.**

---

## КРИТЕРИИ ГОТОВНОСТИ ФАЗЫ 1

- [ ] Все 53 файла созданы в правильных директориях
- [ ] Composer autoload работает (PSR-4)
- [ ] `ferry-ai/inference-core` компилируется без ошибок
- [ ] `ferry-ai/inference-tensor` компилируется без ошибок
- [ ] `ferry-ai/inference-onnx-backend` компилируется без ошибок
- [ ] `ferry-ai/inference-ai` компилируется без ошибок
- [ ] `AI::config()` инициализирует платформу
- [ ] `AI::embed()` возвращает вектор (при наличии ONNX-модели)
- [ ] `AI::classify()` возвращает метку
- [ ] Исключения выбрасываются с понятными сообщениями
- [ ] Документация в README.md описывает установку и первый запуск
- [ ] CI проходит (если настроен)

---

> **План реализации Фазы 1 завершён. После успешного прохождения всех шагов и интеграционного теста можно переходить к Фазе 2.**
