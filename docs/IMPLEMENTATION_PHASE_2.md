# FerryAI — Phase 2 Build Record (LLM)

> **STATUS: COMPLETED.** All 23 steps implemented. 291 tests. Preserved as build record.

---

> Версия: 1.0  
> Цель: запуск LLM через llama.cpp из PHP  
> Длительность: 2–3 месяца  
> Пакеты: llama-backend, tokenizer, обновление ai  
> Новых файлов: 21 (+ 2 обновления существующих)  
> Коммит-стратегия: 1 коммит = 1 логически завершённый файл

---

## 0. ПРЕДВАРИТЕЛЬНЫЕ УСЛОВИЯ

- [ ] Фаза 1 завершена и проходит интеграционный тест
- [ ] Установлена shared library llama.cpp (скомпилирована с поддержкой нужного GPU-бэкенда или CPU-only)
- [ ] В php.ini: `ffi.enable=true`
- [ ] Прочитаны: `FILE_TREE.md` (пакеты llama-backend и tokenizer), `INTERFACE_CONTRACTS.md` (раздел 8 и 9)

---

## ПАКЕТ `llama-backend` (16 файлов)

---

## ШАГ 53: llama-backend/src/LlamaContextParams.php

**Назначение:** Value object для параметров llama_context.

**Зависимости:** нет.

**Детали реализации:**
- `readonly class LlamaContextParams`
- Поля конструктора (все readonly):
  - `int $nCtx = 2048` — размер контекста (макс. длина последовательности)
  - `int $nBatch = 512` — размер батча для обработки промпта
  - `int $nGpuLayers = 0` — сколько слоёв оффлоадить на GPU (0 = все на CPU)
  - `int $nThreads = 0` — количество CPU-потоков (0 = авто)
  - `bool $flashAttn = false` — использовать Flash Attention
  - `bool $useMmap = true` — использовать memory-mapped I/O
  - `bool $useMlock = false` — залочить модель в RAM
- `toArray(): array` — возвращает ассоциативный массив для передачи в FFI

**Критерий приёмки:**
- Все поля имеют значения по умолчанию
- `toArray()` возвращает все ключи

---

## ШАГ 54: llama-backend/src/LlamaModelParams.php

**Назначение:** Value object для параметров загрузки модели.

**Зависимости:** нет.

**Детали реализации:**
- `readonly class LlamaModelParams`
- Поля конструктора:
  - `int $nGpuLayers = 0` — число слоёв на GPU
  - `bool $useMmap = true`
  - `bool $useMlock = false`
  - `bool $vocabOnly = false` — загрузить только словарь
- `toArray(): array`

**Критерий приёмки:**
- Все поля readonly с дефолтами

---

## ШАГ 55: llama-backend/src/FFI/LlamaCpp.php

**Назначение:** FFI-определения C API llama.cpp.

**Зависимости:** PHP FFI extension.

**Детали реализации:**
- `class LlamaCpp`
- Использует `\FFI::cdef()` с C-декларациями функций llama.cpp C API:
  ```
  llama_model_params llama_model_default_params(void);
  llama_context_params llama_context_default_params(void);
  llama_model* llama_model_load_from_file(const char *path, llama_model_params params);
  void llama_model_free(llama_model *model);
  llama_context* llama_init_from_model(llama_model *model, llama_context_params params);
  void llama_free(llama_context *ctx);
  const llama_vocab* llama_model_get_vocab(const llama_model *model);
  int32_t llama_vocab_n_tokens(const llama_vocab *vocab);
  uint32_t llama_n_ctx(const llama_context *ctx);
  int32_t llama_model_n_embd(const llama_model *model);
  // ... и ещё ~20 функций
  ```
  > **Актуальность C API (сверено по `include/llama.h`, master):** используем новые имена —
  > `llama_model_load_from_file`, `llama_init_from_model`, `llama_model_free`, `llama_free`,
  > `llama_vocab_n_tokens`, `llama_model_n_embd`. Устаревшие алиасы (`llama_load_model_from_file`,
  > `llama_new_context_with_model`, `llama_free_model`, `llama_n_vocab`, `llama_n_embd`) ещё
  > присутствуют, но помечены DEPRECATED — не использовать.
- `loadLibrary(string $libPath): void` — загружает shared library через `\FFI::cdef()`
- `isAvailable(): bool` — проверяет, что библиотека загружена
- Статические методы-обёртки для каждой C-функции:
  - `modelDefaultParams(): object`
  - `contextDefaultParams(): object`
  - `modelLoadFromFile(string $path, object $params): object`
  - `modelFree(object $model): void`
  - `contextInitFromModel(object $model, object $params): object`
  - `contextFree(object $ctx): void`
  - `nVocab(object $model): int`
  - `nCtx(object $ctx): int`
  - `nEmbd(object $model): int`
  - `tokenize(object $model, string $text, bool $addBos, bool $special): array`
  - `tokenToPiece(object $model, int $tokenId): string`
  - `decode(object $ctx, object $batch): int`
  - `sampleToken(object $ctx, ...): int`
  - И т.д. — все функции, нужные для инференса

**Критерий приёмки:**
- `LlamaCpp::isAvailable()` определяется наличием .so/.dylib/.dll
- Методы не падают при вызове

---

## ШАГ 56: llama-backend/src/FFI/LlamaContext.php

**Назначение:** Высокоуровневая обёртка над llama_context.

**Зависимости:** Шаг 55 (LlamaCpp).

**Детали реализации:**
- `class LlamaContext`
- Хранит FFI-указатели: `$model`, `$context`
- Конструктор: `__construct(string $modelPath, LlamaModelParams $modelParams, LlamaContextParams $contextParams)`
  - Загружает модель через `llama_model_load_from_file`
  - Создаёт контекст через `llama_init_from_model`
- `nVocab(): int` — размер словаря
- `nCtx(): int` — размер контекста
- `nEmbd(): int` — размерность эмбеддингов
- `tokenize(string $text, bool $addBos = true, bool $special = true): array` — токенизация
- `tokenToPiece(int $tokenId): string` — токен → строка
- `decode(array $tokens, int $nPast = 0): int` — инференс одного шага
- `sampleToken(array $logits, SamplingParams $params): int` — сэмплирование
- `kvCacheClear(): void` — очистка KV-кэша
- `kvCacheSeqRm(int $seqId, int $p0, int $p1): void` — удаление части KV-кэша
- `__destruct(): void` — освобождает context и model

**Критерий приёмки:**
- Конструктор загружает GGUF-файл
- `tokenize('Hello')` возвращает массив int
- `tokenToPiece(token_id)` возвращает строку

---

## ШАГ 57: llama-backend/src/FFI/LlamaBatch.php

**Назначение:** Обёртка над llama_batch для пакетной обработки.

**Зависимости:** Шаг 55 (LlamaCpp).

**Детали реализации:**
- `class LlamaBatch`
- Хранит массив токенов и их позиций
- `add(int $token, int $pos, array $seqIds, bool $logits): void` — добавить токен в батч
- `addSequence(array $tokens, int $startPos, array $seqIds): void` — добавить последовательность
- `clear(): void` — очистить батч
- `size(): int` — количество токенов в батче
- `toNative(): object` — конвертировать в llama_batch для FFI

**Критерий приёмки:**
- Можно добавить токены и конвертировать в нативный батч

---

## ШАГ 58: llama-backend/src/Sampling/Sampler.php

**Назначение:** Интерфейс сэмплера токенов.

**Зависимости:** core ValueObjects\SamplingParams.

**Детали реализации:**
- `interface Sampler`
- `sample(array $logits, SamplingParams $params): int`

---

## ШАГ 59: llama-backend/src/Sampling/GreedySampler.php

**Назначение:** Жадное сэмплирование — всегда выбирает токен с максимальной вероятностью.

**Зависимости:** Шаг 58 (Sampler).

**Детали реализации:**
- `class GreedySampler implements Sampler`
- `sample(array $logits, SamplingParams $params): int` — возвращает argmax($logits)

---

## ШАГ 60: llama-backend/src/Sampling/TopPSampler.php

**Назначение:** Nucleus sampling (top-p).

**Зависимости:** Шаг 58 (Sampler).

**Детали реализации:**
- `class TopPSampler implements Sampler`
- `sample(array $logits, SamplingParams $params): int`
  - Сортирует логиты по убыванию
  - Вычисляет softmax → вероятности
  - Вычисляет кумулятивную сумму
  - Обрезает по порогу topP
  - Сэмплирует из оставшихся токенов пропорционально их вероятностям

---

## ШАГ 61: llama-backend/src/Sampling/TopKSampler.php

**Назначение:** Top-K sampling.

**Зависимости:** Шаг 58 (Sampler).

**Детали реализации:**
- `class TopKSampler implements Sampler`
- `sample(array $logits, SamplingParams $params): int`
  - Сортирует логиты
  - Оставляет только topK токенов
  - Softmax → сэмплирование

---

## ШАГ 62: llama-backend/src/Sampling/GrammarSampler.php

**Назначение:** Constrained generation через GBNF-грамматики.

**Зависимости:** Шаг 58 (Sampler), Шаг 64 (GbnfGrammar).

**Детали реализации:**
- `class GrammarSampler implements Sampler`
- Конструктор: `__construct(GbnfGrammar $grammar)`
- `sample(array $logits, SamplingParams $params): int`
  - Фильтрует логиты: только токены, допустимые грамматикой
  - Среди допустимых применяет top-p или greedy
  - Обновляет состояние грамматики

---

## ШАГ 63: llama-backend/src/Sampling/SamplerFactory.php

**Назначение:** Фабрика сэмплеров.

**Зависимости:** Шаг 58 (Sampler), Шаги 59-62.

**Детали реализации:**
- `class SamplerFactory`
- `create(string $type, ?GbnfGrammar $grammar = null): Sampler`
  - `'greedy'` → GreedySampler
  - `'top_p'` → TopPSampler
  - `'top_k'` → TopKSampler
  - `'grammar'` → GrammarSampler (требует $grammar)
  - default → TopPSampler

---

## ШАГ 64: llama-backend/src/Grammar/GbnfGrammar.php

**Назначение:** Представление GBNF-грамматики.

**Зависимости:** нет.

**Детали реализации:**
- `final readonly class GbnfGrammar`
- Хранит строку с GBNF-правилами
- `fromString(string $gbnf): self` — создать из GBNF-строки
- `fromJsonSchema(array $schema): self` — делегирует JsonSchemaConverter
- `toString(): string` — вернуть GBNF-строку
- `__toString(): string` — алиас toString()

---

## ШАГ 65: llama-backend/src/Grammar/JsonSchemaConverter.php

**Назначение:** Конвертация JSON Schema → GBNF.

**Зависимости:** Шаг 64 (GbnfGrammar).

**Детали реализации:**
- `class JsonSchemaConverter`
- `convert(array $jsonSchema): GbnfGrammar`
  - Поддерживает типы: string, number, integer, boolean, array, object
  - Поддерживает enum, const, properties, required, items
  - Генерирует GBNF-правила для каждого поля
  - Корневое правило: `root ::= object`
  - Рекурсивный обход схемы

**Критерий приёмки:**
- `{"type":"object","properties":{"name":{"type":"string"}},"required":["name"]}` → валидная GBNF-грамматика

---

## ШАГ 66: llama-backend/src/ChatFormatter.php

**Назначение:** Конвертация ChatML-сообщений в формат, понятный llama.cpp.

**Зависимости:** core ValueObjects\ChatMessage.

**Детали реализации:**
- `class ChatFormatter`
- Хранит шаблон форматирования (определяется типом модели)
- Поддерживаемые форматы:
  - `llama3` — `<|begin_of_text|><|start_header_id|>system<|end_header_id|>...`
  - `chatml` — `<|im_start|>system\n...<|im_end|>`
  - `mistral` — `[INST] ... [/INST]`
  - `gemma` — `<start_of_turn>user\n...<end_of_turn>`
  - `phi` — `<|system|>\n...<|end|>\n<|user|>\n...<|end|>\n<|assistant|>`
- `format(array $messages): string` — собирает полный промпт
- `detectFormat(string $modelName): string` — автоопределение формата по имени модели
- `applyTemplate(string $template, array $messages): string` — применяет пользовательский шаблон (Jinja2-подобный)
- Особые случаи:
  - System message может быть не во всех форматах
  - Tool calls преобразуются в специальные токены

**Критерий приёмки:**
- `format([user('Hello')])` возвращает строку с тегами формата
- Для llama3: содержит `<|begin_of_text|>`

---

## ШАГ 67: llama-backend/src/LlamaModel.php

**Назначение:** Реализация интерфейса Model для llama.cpp.

**Зависимости:** core Contracts\Model, Шаг 56 (LlamaContext), Шаг 63 (SamplerFactory), Шаг 66 (ChatFormatter).

**Детали реализации:**
- `class LlamaModel implements Model`
- Хранит: LlamaContext, ChatFormatter, Sampler, ModelMetadata, Device
- Конструктор создаёт контекст, читает метаданные из GGUF (имя, архитектура)
- `run(array $inputs): array`
  - Принимает ChatMessage[] (в виде массива массивов) → форматирует промпт
  - Токенизирует промпт
  - Выполняет префиксное заполнение (батчами по n_batch)
  - Генерирует один токен
  - Декодирует токен → строка
  - Возвращает `['text' => '...', 'token' => <id>]`
- `runComplete(array $inputs): GenerationResult`
  - Полный цикл: промпт → генерация до EOS или max_tokens
  - Возвращает GenerationResult
- `runStream(array $inputs): \Generator`
  - То же, но через yield для каждого токена
  - Использует Fiber внутри
- `inputs(): array` — `[['name' => 'messages', 'shape' => [-1], 'dtype' => 'string']]`
- `outputs(): array` — `[['name' => 'text', 'shape' => [-1], 'dtype' => 'string']]`
- `metadata(): ModelMetadata`
- `device(): Device` — CPU или CUDA (в зависимости от nGpuLayers)
- `unload(): void` — освобождает контекст

**Критерий приёмки:**
- Загружает GGUF-файл
- `run()` генерирует текст
- `runStream()` выдаёт токены через Generator

**ПРИМЕЧАНИЕ:** LlamaModel НЕ использует единый `run()` для всего. Он предоставляет три метода: `run()` (один токен), `runComplete()` (весь ответ), `runStream()` (Generator). Это архитектурное решение: единый `run()` нереалистичен для LLM, поэтому Model-контракт дополняется специфическими методами.

---

## ШАГ 68: llama-backend/src/LlamaBackend.php

**Назначение:** Реализация интерфейса Backend для llama.cpp.

**Зависимости:** core Contracts\Backend, Шаг 67 (LlamaModel).

**Детали реализации:**
- `class LlamaBackend implements Backend`
- `availableDevices(): array`
  - Проверяет, скомпилирован ли llama.cpp с CUDA → [CUDA, CPU]
  - Проверяет Metal → [METAL, CPU]
  - Иначе → [CPU]
- `load(string $source, ?Device $device = null): Model`
  - $source — путь к .gguf файлу
  - Определяет количество GPU-слоёв в зависимости от device
  - Создаёт LlamaModel
- `version(): string` — возвращает версию llama.cpp (константа из хедера)
- `isAvailable(): bool` — проверяет наличие shared library

**Критерий приёмки:**
- `load('model.gguf')` возвращает LlamaModel
- `availableDevices()` возвращает корректный список

---

## ПАКЕТ `tokenizer` (5 файлов)

---

## ШАГ 69: tokenizer/src/TokenizerLoader.php

**Назначение:** Загрузка tokenizer.json и определение типа.

**Зависимости:** core Enums\TokenizerType.

**Детали реализации:**
- `class TokenizerLoader`
- `loadFromFile(string $path): array` — читает tokenizer.json, возвращает декодированный массив
- `loadFromModel(string $modelId): array` — скачивает через Model Hub (если доступен) или ищет локально
- `detectType(array $config): TokenizerType`
  - Анализирует структуру tokenizer.json
  - Если есть `model.type == 'bpe'` → BPE
  - Если есть `model.type == 'wordpiece'` → WordPiece
  - Если есть `model.type == 'unigram'` → Unigram
  - Если есть `SentencePiece*` поля → SentencePiece

**Критерий приёмки:**
- `detectType()` определяет тип корректно

---

## ШАГ 70: tokenizer/src/HuggingFaceTokenizer.php

**Назначение:** Приоритетная реализация — биндинг к tokenizers-cpp через FFI.

**Зависимости:** core Contracts\Tokenizer, Шаг 69 (TokenizerLoader).

**Детали реализации:**
- `class HuggingFaceTokenizer implements Tokenizer`
- Загружает Rust-библиотеку tokenizers-cpp через FFI
- `encode(string $text, bool $addSpecialTokens = true): array` — через FFI-вызов tokenizers_encode
- `decode(array $ids): string` — через FFI-вызов tokenizers_decode
- `encodeBatch(array $texts, bool $padToMaxLength = true): array` — пакетное кодирование
- `vocabSize(): int`
- `type(): TokenizerType`
- `specialTokenId(string $tokenName): ?int`
- `specialTokens(): array`
- `countTokens(string $text): int` — быстрый подсчёт
- `chunk(string $text, int $maxTokens = 512, int $overlap = 64): array`
  - Кодирует текст → токены
  - Разбивает на чанки с перекрытием
  - Декодирует каждый чанк обратно в текст
- `isAvailable(): bool` — проверка наличия tokenizers-cpp

**Критерий приёмки:**
- Если библиотека доступна — все методы работают
- Если нет — `isAvailable()` возвращает false

---

## ШАГ 71: tokenizer/src/PureBpeTokenizer.php

**Назначение:** Pure PHP реализация BPE (fallback).

**Зависимости:** core Contracts\Tokenizer, Шаг 69 (TokenizerLoader).

**Детали реализации:**
- `class PureBpeTokenizer implements Tokenizer`
- Загружает словарь из tokenizer.json: `{token_string: token_id}`
- Загружает merge-правила: `[(token_a, token_b, merged_token)]`
- `encode(string $text, bool $addSpecialTokens = true): array`
  - Разбивает текст на байты
  - Итеративно применяет merge-правила (жадно)
  - Конвертирует в token IDs
- `decode(array $ids): string`
  - Конвертирует IDs → строки токенов
  - Склеивает, обрабатывает спецсимволы (## для WordPiece, Ġ для GPT)
- `encodeBatch(...)` — цикл по текстам + padding
- Остальные методы — аналогично HuggingFaceTokenizer
- **Ограничение:** PureBpeTokenizer медленнее нативного. Для production рекомендуется HuggingFaceTokenizer.

**Критерий приёмки:**
- `encode('Hello world')` возвращает массив int
- `decode(encode('Hello world'))` === 'Hello world' (или близко к нему)

---

## ШАГ 72: tokenizer/src/PureWordPieceTokenizer.php

**Назначение:** Pure PHP реализация WordPiece.

**Зависимости:** core Contracts\Tokenizer, Шаг 69 (TokenizerLoader).

**Детали реализации:**
- `class PureWordPieceTokenizer implements Tokenizer`
- Аналогичен PureBpeTokenizer, но алгоритм WordPiece:
  - Начинает с целого слова
  - Если слова нет в словаре → разбивает на подстроки
  - Префикс `##` для продолжения слова
- Все те же методы интерфейса Tokenizer

---

## ШАГ 73: tokenizer/src/TokenizerFactory.php

**Назначение:** Фабрика токенизаторов.

**Зависимости:** core Contracts\Tokenizer, Шаги 70-72.

**Детали реализации:**
- `class TokenizerFactory`
- `create(string $modelName): Tokenizer`
  - Ищет tokenizer.json для модели (локально или в Model Hub)
  - Если доступен tokenizers-cpp → HuggingFaceTokenizer
  - Если тип BPE → PureBpeTokenizer
  - Если тип WordPiece → PureWordPieceTokenizer
  - Если другой тип → исключение
- `createFromFile(string $tokenizerJsonPath): Tokenizer`
  - То же, но из конкретного файла

---

## ОБНОВЛЕНИЕ ПАКЕТА `ai`

---

## ШАГ 74: ai/src/AIFactory.php (обновление)

**Зависимости:** llama-backend LlamaBackend, tokenizer TokenizerFactory.

**Изменения:**
- `createBackend(BackendType $type): Backend`
  - Llama → `new LlamaBackend()`
- `createTokenizer(string $modelName): Tokenizer`
  - Было: исключение
  - Стало: `TokenizerFactory::create($modelName)`

---

## ШАГ 75: ai/src/AI.php (обновление)

**Зависимости:** Шаг 74.

**Изменения:**
- `config()` — регистрирует LlamaBackend в BackendRegistry
- `chat(array $messages, ?array $options = null): GenerationResult`
  - Было: исключение
  - Стало: загружает LlamaBackend, форматирует сообщения через ChatFormatter, выполняет runComplete()
  - Если backend == onnx и модель поддерживает text generation — пробует через ONNX
  - Иначе: LlamaBackend
- `stream(array $messages, ?array $options = null): Generator`
  - Было: исключение
  - Стало: делегирует LlamaModel::runStream()
  - Возвращает Generator<string> — каждый yield это строка токена
- `tokenizer(string $modelName): Tokenizer`
  - Было: исключение
  - Стало: `AIFactory::createTokenizer($modelName)`

**Критерий приёмки:**
- `AI::chat([user('Hello')])` возвращает GenerationResult с текстом
- `foreach(AI::stream([user('Hello')]) as $token)` выводит токены по одному

---

## ИНТЕГРАЦИОННЫЙ ТЕСТ ФАЗЫ 2

```bash
# 1. Автозагрузка
composer dump-autoload

# 2. Проверка llama-backend
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['backends' => ['llama' => ['model_path' => 'model.gguf']]]);
\$result = AI::chat([['role' => 'user', 'content' => 'What is PHP?']]);
assert(strlen(\$result->text) > 0);
assert(\$result->tokensGenerated > 0);
echo 'Chat OK, tokens: ' . \$result->tokensGenerated . PHP_EOL;
"

# 3. Проверка стриминга
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['backend' => 'llama']);
foreach (AI::stream([['role' => 'user', 'content' => 'Hello']]) as \$token) {
    echo \$token;
}
echo PHP_EOL;
echo 'Stream OK' . PHP_EOL;
"

# 4. Проверка токенизатора
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
\$t = AI::tokenizer('gpt2');
\$ids = \$t->encode('Hello world');
assert(count(\$ids) > 0);
assert(\$t->decode(\$ids) === 'Hello world');
echo 'Tokenizer OK, vocab size: ' . \$t->vocabSize() . PHP_EOL;
"

# 5. Чанкинг
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
\$t = AI::tokenizer('gpt2');
\$chunks = \$t->chunk('This is a long text ' . str_repeat('word ', 500), 100, 20);
assert(count(\$chunks) > 1);
echo 'Chunking OK, chunks: ' . count(\$chunks) . PHP_EOL;
"
```

**Все 4 теста должны пройти без ошибок.**

---

## КРИТЕРИИ ГОТОВНОСТИ ФАЗЫ 2

- [ ] Все 23 файла созданы
- [ ] `ferry-ai/inference-llama-backend` компилируется
- [ ] `ferry-ai/inference-tokenizer` компилируется
- [ ] LlamaCpp FFI биндинг загружает shared library
- [ ] LlamaModel загружает GGUF-файл и выполняет инференс
- [ ] `AI::chat()` возвращает осмысленный ответ (при наличии модели)
- [ ] `AI::stream()` генерирует токены через Generator
- [ ] Токенизатор кодирует/декодирует корректно
- [ ] Чанкинг работает с перекрытием
- [ ] ChatFormatter поддерживает минимум 3 формата (llama3, chatml, mistral)
- [ ] GrammarSampler ограничивает выдачу грамматикой
- [ ] JsonSchemaConverter генерирует валидный GBNF
- [ ] Исключения при отсутствии библиотеки — понятные
- [ ] Стриминг не блокирует PHP-процесс

---

> **План реализации Фазы 2 завершён. После успешного прохождения всех шагов и интеграционного теста можно переходить к Фазе 3.**
