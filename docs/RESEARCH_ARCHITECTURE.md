# Глубокое исследование: FerryAI — архитектурный анализ

> Дата: 30 июня 2026  
> Источник: анализ исходной переписки + независимый веб-ресёрч  
> Роль: профессиональный архитектор  
> Принцип: не изобретать велосипед, использовать существующее

---

## ОГЛАВЛЕНИЕ

1. [Контекст: о чём переписка](#1-контекст-о-чём-переписка)
2. [Верификация утверждений из переписки](#2-верификация-утверждений-из-переписки)
3. [Существующая PHP AI/ML экосистема (полный срез)](#3-существующая-php-aiml-экосистема-полный-срез)
4. [PHP 8.5: новые возможности для AI-платформы](#4-php-85-новые-возможности-для-ai-платформы)
5. [Архитектурный анализ: что реально, что нет](#5-архитектурный-анализ-что-реально-что-нет)
6. [Стратегия развития: что строить](#6-стратегия-развития-что-строить)
7. [Gap-анализ: чего не хватает прямо сейчас](#7-gap-анализ-чего-не-хватает-прямо-сейчас)
8. [Финальная архитектурная модель](#8-финальная-архитектурная-модель)
9. [Приоритетный roadmap](#9-приоритетный-roadmap)
10. [Выводы](#10-выводы)

---

## 1. Контекст: о чём переписка

Исходная переписка содержит диалог о возможности создания современной AI-платформы для PHP. Ключевые темы обсуждения:

- **FANN** — старая C-библиотека (начало 2000-х), НЕ подходит как основа
- **Мульти-бэкенд архитектура** — единый PHP API поверх разных движков
- **Возможности PHP 8.x** — FFI, Fibers, Attributes, Enum, readonly и др.
- **GPU из PHP** — через FFI к CUDA/cuBLAS/cuDNN/OpenCL/Vulkan
- **Стратегия внедрения** — FFI-прототип → нативное расширение
- **Пакетная архитектура** — 20+ независимых пакетов

Финальный вывод переписки: строить не «TensorFlow для PHP», а унифицированную платформу **FerryAI** с подключаемыми бэкендами.

---

## 2. Верификация утверждений из переписки

### 2.1. FANN

| Утверждение | Вердикт |
|---|---|
| FANN — старая C-библиотека 2000-х | ✅ Правда. Последний релиз FANN 2.2 (2012). PHP extension для FANN (ext/fann) исключён из ядра PHP начиная с PHP 8.0 и перенесён в PECL. |
| Не умеет CNN/RNN/Transformer/LLM | ✅ Правда. Только MLP (многослойный перцептрон). |
| Не поддерживает GPU | ✅ Правда. FANN — CPU-only. |
| Никому не интересен | ✅ Правда. 0 активных проектов на базе FANN за последние 5 лет. |

**Вывод:** раздел про FANN полностью корректен.

### 2.2. PHP FFI vs нативное расширение

| Утверждение | Вердикт |
|---|---|
| FFI отключён на многих хостингах | ✅ Правда. `ffi.enable=preload` требует явной настройки. Shared-хостинги почти всегда `ffi.enable=false`. |
| FFI имеет накладные расходы | ⚠️ Частично. Для единичных вызовов — да, для пакетных вычислений (основной сценарий ML) — незначительны, так как overhead FFI амортизируется на фоне времени вычислений. |
| Вызовы CUDA/cuBLAS возможны через FFI | ✅ Правда. Но нужны прекомпилированные .cu ядра. |
| ONNX Runtime — самый перспективный путь | ✅ Подтверждено. Уже 3+ рабочих реализации. |

**Вывод:** стратегия «FFI прототип → нативное расширение» — корректна и проверена практикой.

### 2.3. PHP-возможности для ML

| Возможность PHP | Применимость | Статус |
|---|---|---|
| FFI | Критична | ✅ Подтверждено — основа всех реальных PHP-бэкендов к ONNX/TF/llama.cpp |
| JIT | Полезна | ✅ PHP 8.0+, улучшен в 8.4 (IR Framework). Ускоряет математику, но не заменяет нативный код |
| Fibers | Полезна | ✅ PHP 8.1+. Идеально для пайплайнов и стриминга токенов |
| Attributes | Полезна | ✅ PHP 8.0+. Уже используется RubixML для метаданных |
| Readonly | Полезна | ✅ PHP 8.1 (свойства), 8.2 (классы). Идеально для Tensor/Shape/Device |
| Enum | Полезна | ✅ PHP 8.1. Device, DType, Backend — идеальный use-case |
| SplFixedArray | Ограничена | ⚠️ Быстрее массивов для float[], но не многомерные. RubixML/Tensor использует C-расширение вместо этого |
| WeakMap | Полезна | ✅ PHP 8.0. Для градиентов и графов вычислений |
| Generator | Критична | ✅ Для датасетов, батчей, стриминга токенов |
| First-class callable | Полезна | ✅ PHP 8.1. Красивые графы вычислений |
| Shared Memory (shmop) | Полезна | ✅ Для inference с разделяемыми весами между процессами |
| ext-parallel | Ограничена | ⚠️ Редко установлен, но мощный для многопоточных вычислений |
| Intersection Types | Полезна | ✅ PHP 8.1. `Tensor&Serializable`, `Tensor&Iterator` — чистые API-контракты без лишних интерфейсов |
| Iterator | Полезна | ✅ `foreach($dataset as $batch)` — естественная эргономика датасетов |
| ArrayAccess | Полезна | ✅ `$tensor[5][10]` — тензор индексируется как обычный массив |
| Countable | Полезна | ✅ `count($tensor)` — интроспекция размеров без отдельных методов |
| JsonSerializable | Полезна | ✅ Для REST API, сериализации моделей, экспорта конфигов ONNX |
| __serialize / __unserialize | Полезна | ✅ Современная альтернатива Serializable. Кэширование препроцессированных тензоров |
| Random extension | Полезна | ✅ PHP 8.2 `Randomizer`. Инициализация весов, dropout, shuffle датасетов в PurePHP-бэкенде |
| Hash extension | Полезна | ✅ Хэши для верификации скачанных моделей, ключи кэша, dedup словарей |
| GMP | Ограничена | ⚠️ Bloom Filter / BitSet для огромных индексов — нишевый сценарий, не для MVP |
| Sodium | Полезна | ✅ Подпись `weights.bin + signature` — безопасная загрузка моделей из недоверенных источников |
| PCNTL | Ограничена | ⚠️ Только Linux. Многопроцессное обучение — выходит за рамки inference-ниши проекта |
| OPcache | Полезна | ✅ Кэш моделей, токенизаторов, словарей в shared memory между запросами |
| Reflection | Полезна | ✅ Автопостроение моделей из `#[Layer]`-атрибутов — автоматическая регистрация слоёв |
| DOM/XML | Ограничена | ⚠️ Парсинг ONNX-конфигов и экспорт — частично перекрывается самим ONNX-бэкендом |
| Streams | Критична | ✅ `fopen()` / `stream_get_contents()` / `mmap()` через FFI. Модели по 18GB не влезают в память целиком — без потокового чтения LLM-бэкенд нереализуем |
| Zip | Полезна | ✅ Распространение моделей как `model.ai` (config.json + weights.bin + tokenizer.json внутри zip) |
| Phar | Ограничена | ⚠️ Идея `model.phar` из переписки — креативно, но непрактично для multi-GB моделей |

### 2.4. GPU через FFI — детальная проверка

| Путь | Реализуемость | Практичность | Примечания |
|---|---|---|---|
| CUDA Runtime (cudaMalloc, cudaMemcpy, cudaLaunchKernel) | ✅ Возможно | ⭐⭐ | Нужны прекомпилированные .cu ядра. Без них — только управление памятью |
| cuBLAS (cublasSgemm и др.) | ✅ Возможно | ⭐⭐⭐⭐⭐ | Отличный путь. Матричные операции — готовые функции C API |
| cuDNN (Convolution, Pooling, Attention) | ✅ Возможно | ⭐⭐⭐⭐⭐ | Готовые примитивы для глубокого обучения |
| OpenCL | ✅ Возможно | ⭐⭐⭐⭐ | Кроссплатформенный (NVIDIA/AMD/Intel). C API идеально ложится на FFI |
| Vulkan Compute | ✅ Возможно | ⭐⭐ | Огромный API. Писать биндинги — отдельный большой проект |
| llama.cpp через FFI | ✅ Возможно | ⭐⭐⭐⭐⭐ | Лучший путь для LLM. GPU внутри llama.cpp, PHP только вызывает llama_decode() |
| ONNX Runtime через FFI | ✅ Возможно | ⭐⭐⭐⭐⭐ | Самый зрелый путь. Поддержка CUDA, TensorRT, CoreML, DirectML, OpenVINO, ROCm |

**Ключевой вывод:** утверждения переписки о GPU через FFI полностью верифицированы. Наиболее практичный путь — **ONNX Runtime + llama.cpp** как основные бэкенды, потому что они уже решают проблему GPU-ускорения внутри себя.

---

## 3. Существующая PHP AI/ML экосистема (полный срез)

### 3.1. Библиотеки машинного обучения (Pure PHP)

#### RubixML/ML — ⭐2200 (флагман)
- **Что:** High-level ML библиотека. 40+ алгоритмов (классификация, регрессия, кластеризация, anomaly detection).
- **Плюсы:** Зрелая, документированная, активная (последний релиз Oct 2025), MIT-лицензия.
- **Минусы:** CPU-only (Pure PHP), нет GPU, нет LLM, нет трансформеров.
- **Зависимость:** RubixML/Tensor (опционально) для ускорения матриц.
- **URL:** https://github.com/RubixML/ML

#### RubixML/Tensor — ⭐279
- **Что:** PHP-расширение (C/Zephir) для матричных/векторных операций.
- **Плюсы:** OpenBLAS + LAPACKE, многопоточность, значительное ускорение (см. benchmarks).
- **Минусы:** Только CPU, сложная компиляция (нужен Fortran, OpenBLAS и т.д.).
- **URL:** https://github.com/RubixML/Tensor

#### php-ai/php-mlx — ⭐98 (заброшен)
- **Что:** «Next generation» php-ml. Последнее обновление: Aug 2020.
- **Статус:** Мёртв. Организация php-ai неактивна.

### 3.2. Биндинги к нативным движкам (FFI-based)

#### dstogov/php-tensorflow — ⭐427
- **Что:** Экспериментальный биндинг к TensorFlow C API через FFI.
- **Автор:** Дмитрий Стогов (один из ключевых разработчиков PHP core, автор PHP FFI).
- **Статус:** Эксперимент, не библиотека. Последнее обновление: Jun 2023.
- **Значимость:** Доказательство концепции — TensorFlow из PHP возможен.
- **URL:** https://github.com/dstogov/php-tensorflow

#### ankane/onnxruntime-php — ⭐149
- **Что:** ONNX Runtime для PHP (через FFI).
- **Плюсы:** Стабильный, хорошо документированный, GPU-поддержка (CUDA, CoreML).
- **Автор:** Andrew Kane (автор множества ML-библиотек для Ruby/Python/PHP).
- **Статус:** Активный. 24 релиза.
- **URL:** https://github.com/ankane/onnxruntime-php

#### phpmlkit/onnxruntime — ⭐8 (новый, апрель 2026)
- **Что:** Переосмысленная версия onnxruntime-php. FFI-first архитектура.
- **Плюсы:** Zero-copy буферы, полная поддержка ONNX-типов (sequences, maps, optionals), NDArray-интероп, нативные бинарники для платформ (cpu, cuda12, cuda13).
- **Минусы:** Молодой, мало звёзд, мало тестирования в production.
- **Статус:** Активно развивается. Последнее обновление: апрель 2026.
- **URL:** https://github.com/phpmlkit/onnxruntime

#### SuperUserNameMan/llama.php — ⭐4 (архивирован)
- **Что:** PHP 8 биндинги к llama.cpp через FFI.
- **Статус:** Заброшен, архив.
- **URL:** https://github.com/SuperUserNameMan/llama.php

#### aschmelyun/php-deepseek — ⭐35
- **Что:** Запуск DeepSeek R1 (1.5B) через ONNX Runtime в PHP.
- **Статус:** Proof-of-concept, 1 коммит.
- **URL:** https://github.com/aschmelyun/php-deepseek

### 3.3. OpenCV для PHP

#### php-opencv/php-opencv — ⭐365 (архивирован)
- **Что:** OpenCV 4.5+ с DNN-модулем для PHP 7/8.
- **Статус:** Заброшен (Dec 2023). Модуль DNN поддерживал Caffe, TensorFlow, ONNX.
- **URL:** https://github.com/php-opencv/php-opencv

### 3.4. Вывод по экосистеме

```
СОСТОЯНИЕ ЭКОСИСТЕМЫ PHP AI/ML (июнь 2026):

ГОТОВО К ИСПОЛЬЗОВАНИЮ:
  - RubixML/ML          → классический ML, production-ready
  - RubixML/Tensor      → матричные вычисления, CPU
  - ankane/onnxruntime-php → ONNX-inference, CPU+GPU

ЭКСПЕРИМЕНТАЛЬНО / ТРЕБУЕТ ДОРАБОТКИ:
  - phpmlkit/onnxruntime → современный ONNX, GPU
  - dstogov/php-tensorflow → TF C API, proof-of-concept

ОТСУТСТВУЕТ:
  - production-ready llama.cpp биндинги
  - единый API над разными бэкендами
  - autograd / обучение из PHP
  - DataFrame API
  - Pipeline API
  - Model Hub / кэширование моделей
  - токенизаторы
  - embedding-движок
  - vector store
```

---

## 4. PHP 8.5: новые возможности для AI-платформы

Релиз PHP 8.5 (ноябрь 2025) добавил возможности, прямо релевантные для AI-платформы:

### 4.1. Pipe Operator `|>`
```
// Было:
$result = json_decode(strtolower(trim($text)));

// Стало:
$result = $text |> trim(...) |> strtolower(...) |> json_decode(...);
```
**Применение в AI:** идеально для пайплайнов обработки:
```php
$embedding = $text
    |> $tokenizer->encode(...)
    |> $model->embed(...)
    |> $normalizer->l2(...);
```

### 4.2. Clone With
```php
$newTensor = clone($tensor, ['device' => Device::CUDA]);
```
**Применение:** перенос тензоров между устройствами без мутаций.

### 4.3. `#[\NoDiscard]`
```php
#[\NoDiscard]
function matmul(Tensor $a, Tensor $b): Tensor { ... }
// Предупреждение, если результат не использован
```
**Применение:** предотвращение silent bugs в вычислительных графах.

### 4.4. Closures в Constant Expressions
```php
#[ModelConfig(
    preprocess: static fn(string $text) => strtolower(trim($text))
)]
class BERT extends Model { ... }
```
**Применение:** декларативная конфигурация моделей через атрибуты.

### 4.5. `#[\Override]` на свойствах + атрибуты на константах (вкл. `#[\Deprecated]`)

### 4.6. Backtraces в Fatal Errors
Упрощает отладку долгих вычислений.

### 4.7. `#[\DelayedTargetValidation]`
Гибкая валидация атрибутов — полезна при кодогенерации моделей.

### 4.8. Asymmetric Visibility для Static Properties
Улучшает инкапсуляцию в синглтонах (Model Registry, Device Manager).

### 4.9. Final Property Promotion
```php
class Model {
    public function __construct(
        public final string $name  // нельзя переопределить в наследниках
    ) {}
}
```

---

## 5. Архитектурный анализ: что реально, что нет

### 5.1. Концепция «FerryAI» — оценка реализуемости

| Компонент | Реализуемость | Обоснование |
|---|---|---|
| **Tensor API** | ✅ Высокая | Можно взять RubixML/Tensor как базу для CPU. Для GPU — обёртка над OrtValue из onnxruntime. |
| **DataFrame API** | ✅ Средняя | Нет аналогов в PHP. Можно сделать с нуля либо биндинг к Polars (Rust) через FFI. |
| **Backend Driver System** | ✅ Высокая | Паттерн проверен (Laravel driver system). ONNX Runtime уже работает. llama.cpp — требуется написать биндинг. |
| **Pipeline API** | ✅ Высокая | Fibers + `|>` operator + generators = идеальный стек для пайплайнов. |
| **Model Hub** | ✅ Средняя | HuggingFace API уже существует для PHP (`codewithkyrian/huggingface-php`). Нужна обвязка. |
| **Autograd** | ❌ Низкая | Чистый PHP — слишком медленно. C-расширение — огромный объём работы. Не приоритетно. |
| **Обучение моделей из PHP** | ❌ Низкая | PHP не конкурент Python в обучении. Ниша — inference, а не training. |
| **Tokenizer** | ✅ Средняя | Можно биндинг к HuggingFace Tokenizers (Rust) через FFI. Либо использовать tokenizer.json из моделей. |
| **Vector Store** | ✅ Средняя | SQLite + расширение для векторного поиска, либо биндинг к FAISS через FFI. |
| **Embedding Engine** | ✅ Высокая | ONNX Runtime уже умеет. Нужен удобный API. |

### 5.2. Ключевое архитектурное решение: бэкенд-стратегия

```
ПРЕДЛАГАЕМАЯ АРХИТЕКТУРА БЭКЕНДОВ:

┌──────────────────────────────────────────────┐
│               FerryAI API                    │
│  (единый интерфейс для пользователя)          │
└──────────────────┬───────────────────────────┘
                   │
    ┌──────────────┼──────────────┬──────────────┐
    │              │              │              │
┌───▼────┐  ┌─────▼────┐  ┌─────▼────┐  ┌─────▼────┐
│ ONNX   │  │ llama.cpp│  │CPU Native│  │ Future   │
│ Runtime│  │ Backend  │  │ Backend  │  │ Backends │
│ (FFI)  │  │ (FFI)    │  │(RubixML) │  │          │
└───┬────┘  └─────┬────┘  └──────────┘  └──────────┘
    │              │
    ├── CPU        ├── CPU
    ├── CUDA       ├── CUDA
    ├── TensorRT   ├── Metal
    ├── CoreML     ├── Vulkan
    ├── DirectML   └── OpenCL
    ├── OpenVINO
    └── ROCm
```

**Почему именно так:**
1. **ONNX Runtime** — универсальный формат моделей (из PyTorch, TF, JAX, sklearn). Максимальный охват.
2. **llama.cpp** — стандарт де-факто для запуска LLM локально. Не нужно повторять.
3. **CPU Native (RubixML)** — fallback, когда нет нативных GPU-библиотек. Для простых моделей через RubixML/Tensor. Важно: несмотря на название «Pure PHP» в переписке, этот бэкенд фактически использует C-расширение RubixML/Tensor (OpenBLAS + LAPACKE) и не является чисто-PHP решением.
4. **НЕ включаем FANN** — слишком старый, не даёт преимуществ перед CPU Native бэкендом.
5. **НЕ делаем свой autograd** — это работа для Python, не для PHP.

### 5.3. Пакетная структура (финальная — канон в `FILE_TREE.md`)

```
php-inference/
├── packages/
│   ├── core/              # Базовые контракты, enum'ы (Device, DType, BackendType…), исключения
│   ├── tensor/            # Tensor API (обёртка над бэкендами)
│   ├── onnx-backend/      # ONNX Runtime backend (использует phpmlkit/onnxruntime)
│   ├── llama-backend/     # llama.cpp backend (новый, через FFI)
│   ├── cpu-backend/       # CPU-native backend (интеграция с RubixML/Tensor C-расширением)
│   ├── tokenizer/         # Токенизация (бэкенд-независимо)
│   ├── embedding/         # Эмбеддинги через модели
│   ├── pipeline/          # Pipeline API (Fibers-based)
│   ├── model-hub/         # Загрузка, кэширование, верификация моделей (в т.ч. HuggingFace Hub)
│   ├── vector/            # Vector store (SQLite + sqlite-vec)
│   ├── dataframe/         # DataFrame API (Фаза 4, отложен)
│   ├── ai/                # Унифицированный фасад (точка входа)
│   ├── laravel/           # Интеграция с Laravel (Фаза 4)
│   └── symfony/           # Интеграция с Symfony (Фаза 4)
```

> Ранняя идея отдельного пакета `huggingface/` реализована внутри `model-hub`. Итог — **14 пакетов**; ядро платформы (фазы 1–3) — 11, ещё 3 (`dataframe`, `laravel`, `symfony`) добавляются последними в Фазе 4.

**Что НЕ нужно делать отдельными пакетами (YAGNI):**
- math/ — достаточно tensor/
- nn/ — обучение не наша ниша
- autograd/ — не наша ниша
- optimizer/ — не наша ниша
- vision/ — отдельно, когда будет спрос
- audio/ — отдельно, когда будет спрос
- onnx/ — перекрывается onnx-backend

> **Примечание про 20+ vs 8-10 пакетов:** в переписке предлагалось ~20 независимых пакетов (core, tensor, math, dataframe, dataset, nn, autograd, optimizer, onnx, llama, huggingface, tokenizer, embedding, vector, pipeline, vision, audio и др.). Это корректное **стратегическое видение** для fully-fledged платформы. Однако для MVP достаточно 8-10 пакетов. По мере роста проект может расширяться до полного набора — но не всё сразу. Оценка «overengineering» в выводах (раздел 10.1, пункт 6) относится именно к стартовому набору, а не к финальному видению.

---

## 6. Стратегия развития: что строить

### 6.1. Позиционирование

**Мы НЕ делаем:**
- PHP-библиотеку для обучения нейросетей (проиграем Python)
- «TensorFlow для PHP» (уже есть, но никому не нужно)
- Ещё одну ML-библиотеку (есть RubixML)

**Мы ДЕЛАЕМ:**
- **Единый интерфейс для инференса AI-моделей в PHP**
- Мост между PHP-экосистемой и миром AI-моделей
- Удобный SDK, который позволяет PHP-разработчику использовать современные модели (LLM, embedding, vision, audio) не выходя из языка

### 6.2. Real-world use cases

1. **Laravel/Symfony приложение** → нужен embedding для поиска → `AI::embed($text)`
2. **RAG-система на PHP** → нужен LLM для ответа → `AI::chat($messages)`
3. **E-commerce** → классификация товаров → `AI::classify($image)`
4. **SaaS** → модерация контента → `AI::moderate($text)`
5. **Аналитика** → предиктивная модель → `AI::predict($data)`

### 6.3. Почему сейчас PHP может

```
2018: PHP 7.2 — ни FFI, ни типов, ни JIT      → невозможно
2020: PHP 7.4 — FFI, typed properties           → теоретически
2021: PHP 8.0 — JIT, Attributes, WeakMap        → прототип
2022: PHP 8.1 — Fibers, Enums, readonly         → архитектура
2023: PHP 8.2 — readonly classes, DNF types     → стабильно
2024: PHP 8.4 — property hooks, lazy objects    → удобно
2025: PHP 8.5 — pipe operator, clone with       → элегантно
2026: PHP 8.6 — ...                             → будущее
```

**Только с PHP 8.5 стек стал достаточно зрелым для элегантного AI API.**

---

## 7. Gap-анализ: чего не хватает прямо сейчас

| Пробел | Приоритет | Что делать |
|---|---|---|
| **llama.cpp биндинг** | 🔴 Критичный | Нет рабочего биндинга. Нужно написать через FFI (C API llama.cpp стабилен). |
| **Единый API над бэкендами** | 🔴 Критичный | Никто не сделал. Нужен пакет core с интерфейсами. |
| **Токенизатор** | 🟡 Высокий | Нужен для LLM. Варианты: (1) биндинг к tokenizers-cpp через FFI, (2) PHP-реализация BPE. |
| **Vector Store** | 🟡 Высокий | SQLite + sqlite-vec extension через FFI. Либо чистый PHP с HNSW. |
| **DataFrame API** | 🟢 Средний | Можно отложить. Сначала Tensor. |
| **Streaming inference** | 🟡 Высокий | Fibers-based стриминг токенов для чата. |
| **Model кэширование** | 🟢 Средний | HuggingFace API уже есть. Нужна обвязка. |
| **Безопасная загрузка моделей** | 🟢 Средний | Подпись весов через Sodium. |

---

## 8. Финальная архитектурная модель

### 8.1. Слои

```
┌──────────────────────────────────────────────────────────────────┐
│                         USER LAND CODE                            │
│                                                                    │
│  use FerryAI\AI;                                                    │
│                                                                    │
│  $answer = AI::chat('What is PHP?');                              │
│  $vector = AI::embed('hello world');                              │
│  $label  = AI::classify($image);                                  │
│                                                                    │
└──────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌──────────────────────────────────────────────────────────────────┐
│                      FACADE / BUILDER LAYER                       │
│                                                                    │
│  AI::backend('onnx');    AI::backend('llama');                    │
│  AI::device('cuda');     AI::device('cpu');                       │
│                                                                    │
│  Предоставляет конфигурацию в одном месте.                        │
│  Аналогично Laravel Facades, но без фреймворка.                   │
│                                                                    │
└──────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌──────────────────────────────────────────────────────────────────┐
│                       BACKEND DRIVER LAYER                        │
│                                                                    │
│  Interface BackendDriver {                                        │
│      public function run(Model $model, array $inputs): array;     │
│      public function availableDevices(): array;                   │
│      public function loadModel(string $path): Model;              │
│  }                                                                │
│                                                                    │
│  class OnnxBackend implements BackendDriver { ... }               │
│  class LlamaBackend implements BackendDriver { ... }              │
│  class CpuNativeBackend implements BackendDriver { ... }          │
│                                                                    │
└──────────────────────────────────────────────────────────────────┘
                                  │
            ┌─────────────────────┼─────────────────────┐
            ▼                     ▼                     ▼
    ┌───────────────┐   ┌───────────────┐   ┌───────────────┐
    │ ONNX Runtime  │   │  llama.cpp    │   │  RubixML/     │
    │ (FFI)         │   │  (FFI)        │   │  Tensor       │
    │               │   │               │   │ (C extension, │
    │ phpmlkit/     │   │ НОВЫЙ ПАКЕТ   │   │  OpenBLAS)    │
    │ onnxruntime   │   │               │   │               │
    └───────────────┘   └───────────────┘   └───────────────┘
```

### 8.2. Ключевые интерфейсы

> Ниже — концептуальные эскизы ранней проработки. **Канонические (актуальные) сигнатуры** — в `INTERFACE_CONTRACTS.md`; здесь оставлены упрощённые формы для наглядности идеи, но enum'ы приведены к актуальному виду.

```php
// core/src/Contracts/Backend.php  (полная сигнатура — INTERFACE_CONTRACTS.md §1.1)
interface Backend
{
    /** @return Device[] */
    public function availableDevices(): array;
    public function load(string $source, ?Device $device = null): Model;
    public function version(): string;
    public function isAvailable(): bool;
}

// core/src/Contracts/Model.php  (полная сигнатура — INTERFACE_CONTRACTS.md §1.2)
interface Model
{
    /** @return array<string, mixed> */
    public function run(array $inputs): array;
    public function inputs(): array;
    public function outputs(): array;
    public function metadata(): ModelMetadata;
    public function device(): Device;
    public function unload(): void;
}

// core/src/Contracts/Tensor.php  (полная сигнатура — INTERFACE_CONTRACTS.md §1.3)
interface Tensor extends ArrayAccess, Countable, JsonSerializable
{
    public function shape(): Shape;
    public function dtype(): DType;
    public function to(Device $device): self;
    public function toArray(): array;
    public function data(): mixed; // FFI buffer
    // + арифметика, reshape, slice и т.д.
}

// core/src/Enums/Device.php
enum Device: string
{
    case CPU      = 'cpu';
    case CUDA     = 'cuda';
    case ROCM     = 'rocm';
    case METAL    = 'metal';
    case VULKAN   = 'vulkan';
    case DIRECTML = 'directml';
    case OPENVINO = 'openvino';
    case OPENCL   = 'opencl';
    case AUTO     = 'auto';
}

// core/src/Enums/DType.php
enum DType: string
{
    case Float32 = 'float32';
    case Float16 = 'float16';
    case Int32 = 'int32';
    case Int64 = 'int64';
    case String = 'string';
}
```

### 8.3. Пример использования

```php
use FerryAI\AI;

// Конфигурация один раз
AI::config([
    'backend' => 'onnx',
    'device' => 'auto',  // сам выберет CUDA если есть
    'model_cache' => '/var/cache/models',
]);

// Embedding
$vector = AI::embed('Hello world');
// → [0.123, -0.456, ...]

// LLM Chat
$response = AI::chat([
    ['role' => 'system', 'content' => 'You are helpful assistant.'],
    ['role' => 'user', 'content' => 'What is PHP?'],
]);
// → "PHP is a programming language..."

// Streaming
foreach (AI::stream($messages) as $token) {
    echo $token;  // выводит по токену
}

// Image classification
$label = AI::classify('photo.jpg');
// → ['label' => 'cat', 'confidence' => 0.97]

// Смена бэкенда на лету (если есть llama для LLM)
AI::backend('llama');
$response = AI::chat($messages);  // тот же код, другой движок
```

---

## 9. Приоритетный roadmap

### Фаза 1: MVP (2-3 месяца)

**Цель:** работающий инференс на ONNX Runtime из PHP

- [x] `phpmlkit/onnxruntime` уже существует — берём как зависимость
- [ ] `packages/core` — базовые интерфейсы (Backend, Model, Tensor, Device, DType)
- [ ] `packages/onnx-backend` — адаптер над phpmlkit/onnxruntime
- [ ] `packages/ai` — фасад AI с удобным API
- [ ] Документация + примеры

### Фаза 2: LLM (2-3 месяца)

**Цель:** запуск LLM через llama.cpp

- [ ] `packages/llama-backend` — FFI-биндинг к llama.cpp
  - Загрузка GGUF-моделей
  - Чат-интерфейс
  - Стриминг токенов через Fibers
  - GPU через бэкенды llama.cpp (CUDA, Metal, Vulkan)
- [ ] `packages/tokenizer` — токенизация для LLM
  - Загрузка tokenizer.json из HuggingFace
  - BPE/WordPiece кодирование/декодирование

### Фаза 3: Экосистема (3-4 месяца)

- [ ] `packages/embedding` — унифицированный API для эмбеддингов
- [ ] `packages/pipeline` — пайплайны: tokenize → embed → store
- [ ] `packages/vector` — Vector Store (SQLite + sqlite-vec)
- [ ] `packages/model-hub` — загрузка/кэширование из HuggingFace
- [ ] `packages/cpu-backend` — интеграция с RubixML/Tensor для простых моделей

### Фаза 4: Production (4-6 месяцев)

- [ ] Shared-memory inference (несколько workers, одни веса)
- [ ] Асинхронный inference (Fibers-based)
- [ ] Профилирование и оптимизация
- [ ] Нативные бинарники для всех платформ
- [ ] Интеграции с Laravel / Symfony

---

## 10. Выводы

### 10.1. Основной вывод

**Исходная переписка архитектурно верна на 90%.** Все ключевые идеи подтверждены независимым исследованием:

1. ✅ FANN — не нужно. Полностью подтверждено.
2. ✅ Мульти-бэкенд архитектура — правильно, это главная ценность.
3. ✅ FFI как мост к нативным библиотекам — правильно, работает.
4. ✅ GPU через ONNX Runtime / llama.cpp — правильно, не нужно писать своё.
5. ✅ Двухфазная стратегия (FFI → расширение) — разумно, но фазой 2 можно и не заниматься: FFI-решения уже достаточно хороши.
6. ✅ Пакетная архитектура — правильно. 20+ пакетов — верное стратегическое видение; для MVP достаточно 8-10. Не overengineering, а staged delivery (см. примечание в разделе 5.3).

### 10.2. Что реально нужно и важно

| Задача | Статус |
|---|---|
| Единый AI API для PHP | **НЕТ** — главный пробел, который нужно закрыть |
| ONNX Runtime инференс | **ЕСТЬ** — onnxruntime-php / phpmlkit/onnxruntime |
| llama.cpp инференс | **НЕТ** — нужно написать |
| Токенизатор | **НЕТ** — нужно написать или биндинг |
| Vector Store | **НЕТ** — нужно сделать |
| Embedding | **ЧАСТИЧНО** — ONNX умеет, нет удобного API |
| Pipeline API | **НЕТ** — нужно сделать |
| Model Hub (HF) | **ЧАСТИЧНО** — huggingface-php есть, нет кэширования |
| Обучение / Autograd | **НЕ НУЖНО** — не ниша PHP |

### 10.3. Ключевые решения

1. **Базовый бэкенд — ONNX Runtime.** Зрелый, кроссплатформенный, с GPU. Две рабочие PHP-библиотеки уже существуют.
2. **LLM бэкенд — llama.cpp.** Стандарт де-факто для локального запуска LLM. Нужно написать PHP-биндинг.
3. **НЕ пишем свои CUDA-ядра.** Используем существующие движки, которые уже решают GPU-ускорение.
4. **НЕ конкурируем с Python в обучении.** Наша ниша — inference из PHP-приложений.
5. **Используем phpmlkit/onnxruntime** как основу onnx-бэкенда, а не ankane/onnxruntime-php. Обоснование: несмотря на меньшую зрелость (⭐8 vs ⭐149), phpmlkit даёт zero-copy буферы, полную поддержку ONNX-типов (sequences, maps, optionals), NDArray-интероп и прекомпилированные бинарники под платформы (cpu, cuda12, cuda13). Это критично для production-инференса. При этом ankane/onnxruntime-php остаётся резервным вариантом и референсной реализацией.
6. **Максимально используем существующее:** RubixML для простых моделей, HuggingFace PHP для загрузки, SQLite для векторного поиска.

### 10.4. Финальная мысль

Этот проект имеет реальный смысл и рыночную нишу. PHP — язык 75%+ веба. Миллионы PHP-разработчиков хотят использовать AI, но вынуждены писать микросервисы на Python. Унифицированная платформа FerryAI, позволяющая делать инференс прямо в PHP-приложении — это не «TensorFlow для PHP», это **«AI для PHP-разработчика»**.

Главное — не изобретать велосипеды, а построить мост между зрелыми нативными движками и удобным PHP API.

---

*Документ подготовлен на основе глубокого анализа исходной переписки и независимого веб-исследования экосистемы PHP AI/ML по состоянию на июнь 2026.*
