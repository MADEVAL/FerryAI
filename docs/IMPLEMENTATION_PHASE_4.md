# FerryAI — Phase 4 Build Record (Production)

> **STATUS: COMPLETED.** All main steps implemented. 568 tests. Preserved as build record.

---

> Версия: 1.0  
> Цель: стабильный, масштабируемый, production-ready продукт  
> Длительность: 4–6 месяцев  
> Пакеты: все существующие + dataframe + laravel + symfony  
> Новых файлов: 14 основных (+ 6 DataFrame отложенных) + доработки существующих  
> Коммит-стратегия: 1 коммит = 1 логически завершённое изменение

---

## 0. ПРЕДВАРИТЕЛЬНЫЕ УСЛОВИЯ

- [ ] Фазы 1–3 завершены и проходят интеграционные тесты
- [ ] Настроен CI/CD (GitHub Actions)
- [ ] Определены целевые платформы для нативных бинарников
- [ ] Прочитаны: `TECHNICAL_SPECIFICATION.md` разделы 23–29
- [ ] Настроен мониторинг (логи, метрики)

---

## ЧАСТЬ 1: ОПТИМИЗАЦИЯ ПРОИЗВОДИТЕЛЬНОСТИ

---

## ШАГ 118: Shared Memory Inference

**Пакет:** `ai` (новый файл `SharedMemoryManager.php`)

**Назначение:** Разделение весов моделей между несколькими PHP-воркерами.

**Детали реализации:**
- `class SharedMemoryManager`
- `allocateModel(string $modelId, string $modelPath): int` — загружает модель в System V shared memory (shmop). Возвращает shm_key.
- `attachModel(string $modelId): int` — подключается к существующему сегменту
- `detachModel(string $modelId): void`
- `isShared(string $modelId): bool`
- Работает только для llama.cpp (поддерживает mmap из коробки)
- Для ONNX Runtime — заглушка (ONNX не поддерживает shared memory напрямую)
- Использует `shmop_open`, `shmop_read`, `shmop_write`
- **Ограничение:** read-only доступ. Инференс выполняется в каждом процессе независимо.
- **Экономия:** при 10 FPM-воркерах и модели 7 GB — 7 GB вместо 70 GB RAM

---

## ШАГ 119: Модельный пул (Model Pool)

**Пакет:** `ai` (новый файл `ModelPool.php`)

**Назначение:** Предзагрузка и переиспользование моделей между запросами.

**Детали реализации:**
- `class ModelPool`
- `warmup(array $modelIds): void` — предзагружает модели при старте
- `acquire(string $modelId): Model` — получить модель из пула
- `release(string $modelId): void` — вернуть модель в пул
- `evict(string $modelId): void` — выгрузить модель
- `size(): int` — количество моделей в пуле
- `memoryUsage(): int` — суммарный размер в RAM
- **Стратегия удержания:** keep-alive. Модели остаются загруженными пока есть место.
- **Потокобезопасность:** flock на уровне файла для предотвращения гонок

**Изменения в OnnxBackend и LlamaBackend:**
- При `load()` сначала проверять ModelPool (acquire)
- При `unload()` возвращать модель в пул (release)

---

## ШАГ 120: Асинхронный инференс

**Пакет:** `ai` (новый файл `AsyncInference.php`)

**Назначение:** Неблокирующий инференс для долгоживущих процессов (RoadRunner, FrankenPHP, Swoole).

**Детали реализации:**
- `class AsyncInference`
- `runAsync(callable $inference, ...$args): \Fiber` — выполняет инференс в Fiber
- `wait(\Fiber $fiber, int $timeoutMs = 30000): mixed` — ожидает результат с таймаутом
- `runParallel(array $tasks): array` — выполняет несколько инференсов параллельно (через Fibers)
  - $tasks = [['model' => '...', 'input' => '...'], ...]
  - Возвращает массив результатов в том же порядке
- Обработка таймаута: если Fiber не завершился за timeoutMs → FiberException с частичным результатом

---

## ШАГ 121: Потоковая загрузка больших моделей

**Пакет:** `model-hub` (дополнение к `Downloader.php`)

**Детали реализации:**
- `class StreamLoader` (новый файл `StreamLoader.php`)
- `loadMmap(string $path): resource` — mmap файла для llama.cpp
- `loadStream(string $path, int $chunkSize = 1048576): \Generator` — потоковое чтение чанками (1 MB)
- Используется для моделей > 18 GB (LLaMA 70B, Mixtral)
- Интеграция с LlamaBackend: передача mmap-файла вместо полной загрузки в RAM

---

## ЧАСТЬ 2: КРОССПЛАТФОРМЕННЫЕ БИНАРНИКИ

---

## ШАГ 122: Автоопределение платформы и автоскачивание

**Пакет:** `core` (новый файл `PlatformDetector.php`)

**Назначение:** Определение ОС и архитектуры для выбора правильного бинарника.

**Детали реализации:**
- `class PlatformDetector`
- `os(): string` — `linux` | `macos` | `windows`
- `arch(): string` — `x86_64` | `aarch64`
- `libExtension(): string` — `.so` | `.dylib` | `.dll`
- `platformKey(): string` — `linux-x86_64`, `macos-arm64` и т.д.

**Пакет:** `ai` (новый файл `NativeBinaryManager.php`)

**Назначение:** Автоматическое скачивание и верификация нативных бинарников.

**Детали реализации:**
- `class NativeBinaryManager`
- `resolve(string $library): string` — возвращает путь к shared library
  - Ищет в системе (LD_LIBRARY_PATH, DYLD_LIBRARY_PATH, PATH)
  - Ищет в локальном кэше (`~/.ferry-ai/bin/`)
  - Если нет — скачивает с GitHub Releases проекта
- `download(string $library, string $version): string`
  - Формирует URL: `https://github.com/MADEVAL/ferry-ai-native-binaries/releases/download/v{version}/{lib}-{platform}.{ext}`
  - Скачивает, проверяет SHA-256
  - Сохраняет в локальный кэш
- `verify(string $path, string $expectedSha256): bool`
- `cleanup(): void` — удаляет старые версии бинарников

---

## ШАГ 122a: Расширенные ONNX-провайдеры (Intel/AMD)

**Пакет:** `onnx-backend` (2 новых файла в `src/Provider/`)

**Назначение:** расширение аппаратной поддержки ONNX Runtime на Intel и AMD.

**Детали реализации:**
- `class OpenVinoProvider implements ExecutionProvider`
  - `name(): string` — `'OpenVINOExecutionProvider'`
  - `device(): Device` — `Device::OPENVINO`
  - `isAvailable(): bool` — проверяет сборку ONNX Runtime с OpenVINO
  - `configure(): array` — настройки device_type (CPU/GPU/NPU)
- `class RocmProvider implements ExecutionProvider`
  - `name(): string` — `'ROCMExecutionProvider'`
  - `device(): Device` — `Device::ROCM`
  - `isAvailable(): bool` — проверяет наличие ROCm (AMD GPU)
  - `configure(): array` — device_id, memory_limit

**Изменения в `OnnxRuntimeFactory` / `OnnxBackend`:**
- Регистрация новых провайдеров в `availableProviders()` и `availableDevices()`

**Критерий приёмки:**
- Оба класса реализуют `ExecutionProvider`
- `device()` возвращает `Device::OPENVINO` / `Device::ROCM`

---

## ЧАСТЬ 3: ИНТЕГРАЦИИ С ФРЕЙМВОРКАМИ

---

## ШАГ 123: Интеграция с Laravel

**Пакет:** `laravel` (2 файла)

### laravel/src/AIServiceProvider.php

**Детали реализации:**
- `class AIServiceProvider extends \Illuminate\Support\ServiceProvider`
- `register(): void`
  - Публикует конфиг `config/ferry-ai.php`
  - Регистрирует синглтон AI
  - Вызывает `AI::config(config('ferry-ai'))`
  - Регистрирует бэкенды (onnx, llama, cpu)
- `boot(): void`
  - Если `config('ferry-ai.warmup')` — выполняет предзагрузку моделей
  - Публикует миграции (если есть)
  - Регистрирует команды artisan:
    - `ferry-ai:models:list` — список закэшированных моделей
    - `ferry-ai:models:download {id}` — скачать модель
    - `ferry-ai:models:prune` — очистить кэш
    - `ferry-ai:tokenize {text}` — протестировать токенизатор
    - `ferry-ai:chat {message}` — протестировать чат

### laravel/src/Facades/AI.php

**Детали реализации:**
- Laravel Facade, делегирует к `FerryAI\AI`
- Позволяет использовать `\AI::chat(...)` в Laravel-коде

### Конфиг `config/ferry-ai.php` (публикуется):
```php
return [
    'backend' => env('FERRY_AI_BACKEND', 'auto'),
    'device'  => env('FERRY_AI_DEVICE', 'auto'),
    'model_cache' => env('FERRY_AI_MODEL_CACHE', storage_path('models')),
    'max_tokens' => env('FERRY_AI_MAX_TOKENS', 2048),
    'temperature' => env('FERRY_AI_TEMPERATURE', 0.7),
    'top_p' => env('FERRY_AI_TOP_P', 1.0),
    'verify_signatures' => env('FERRY_AI_VERIFY_SIGNATURES', true),
    'backends' => [
        'onnx' => [
            'providers' => explode(',', env('FERRY_AI_ONNX_PROVIDERS', 'CUDA,CPU')),
            'graph_optimization' => env('FERRY_AI_ONNX_OPTIMIZATION', 'ALL'),
        ],
        'llama' => [
            'model_path' => env('FERRY_AI_LLAMA_MODEL_PATH'),
            'n_ctx' => env('FERRY_AI_LLAMA_N_CTX', 2048),
            'n_gpu_layers' => env('FERRY_AI_LLAMA_GPU_LAYERS', 0),
        ],
    ],
    'warmup' => env('FERRY_AI_WARMUP') ? explode(',', env('FERRY_AI_WARMUP')) : [],
    'log_channel' => env('FERRY_AI_LOG_CHANNEL', 'stack'),
];
```

---

## ШАГ 124: Интеграция с Symfony

**Пакет:** `symfony` (3 файла)

### symfony/src/AIBundle.php

**Детали реализации:**
- `class AIBundle extends \Symfony\Component\HttpKernel\Bundle\Bundle`
- `boot(): void` — инициализирует AI::config из параметров контейнера

### symfony/src/DependencyInjection/Configuration.php

**Детали реализации:**
- `class Configuration implements \Symfony\Component\Config\Definition\ConfigurationInterface`
- Дерево конфигурации (аналогично Laravel-конфигу)

### symfony/src/DependencyInjection/FerryAIExtension.php

**Детали реализации:**
- `class FerryAIExtension extends \Symfony\Component\DependencyInjection\Extension\Extension`
- Загружает конфигурацию из `config/packages/ferry_ai.yaml`
- Регистрирует сервисы: AI, BackendRegistry, бэкенды

---

## ЧАСТЬ 4: ДОРАБОТКИ ПАКЕТОВ

---

## ШАГ 125: StreamResponse для HTTP

**Пакет:** `ai` (доработка `StreamResponse.php`, создан в Фазе 1 как заглушка)

**Детали реализации:**
- `class StreamResponse`
- `create(Generator $tokens): \Psr\Http\Message\ResponseInterface`
  - Создаёт PSR-7 Response с `Content-Type: text/event-stream`
  - Body — кастомный Stream, который читает из Generator
- `toSse(Generator $tokens): \Psr\Http\Message\ResponseInterface`
  - Server-Sent Events формат: `data: {token}\n\n`
- `toNdjson(Generator $tokens): \Psr\Http\Message\ResponseInterface`
  - Newline-Delimited JSON: `{"token": "..."}\n`

---

## ШАГ 126: Логирование и мониторинг

**Пакет:** `core` (новый файл `Logger.php`)  
**Пакет:** `ai` (новый файл `Metrics.php`)

**Детали реализации:**
- `class Logger`
  - PSR-3 совместимый логгер
  - Уровни: debug, info, warning, error
  - Контекст: бэкенд, модель, длительность
  - Формат: JSON lines или текст
- `class Metrics`
  - Счётчики: `inference_count`, `tokens_generated`
  - Таймеры: `inference_duration_ms`, `model_load_duration_ms`
  - Метки: backend, model, device
  - Экспорт в Prometheus / StatsD / логи
  - `record(string $metric, float $value, array $tags = []): void`
  - `increment(string $metric, array $tags = []): void`
  - `timing(string $metric, float $durationMs, array $tags = []): void`

**Интеграция метрик во все бэкенды:**
- OnnxBackend::load() → Metrics::timing('model_load', ..., ['backend' => 'onnx'])
- LlamaModel::runComplete() → Metrics::timing('inference', ..., ['backend' => 'llama'])
- AI::embed() → Metrics::increment('embed_count')

---

## ШАГ 127: Профилирование и бенчмарки

**Пакет:** `ai` (новый файл `Profiler.php`)

**Детали реализации:**
- `class Profiler`
- `start(string $label): void`
- `end(string $label): float` — возвращает длительность в мс
- `report(): array` — все замеры: [label => [count, total_ms, avg_ms, min_ms, max_ms]]
- `reset(): void`

**Бенчмарк-скрипт** `benchmarks/` (корень монорепо):
- `embed.php` — замеры эмбеддинга (одиночный / батч / throughput)
- `chat.php` — замеры LLM (время до первого токена / tokens per second)
- `vector.php` — замеры Vector Store (insert / search / batch)

---

## ШАГ 128: Обработка ошибок и retry

**Пакет:** `core` (новый файл `RetryHandler.php`)

**Детали реализации:**
- `class RetryHandler`
- `retry(callable $fn, int $maxAttempts = 3, int $delayMs = 1000, string $backoff = 'exponential'): mixed`
  - Экспоненциальная задержка: 1s, 2s, 4s, ...
  - Линейная: 1s, 1s, 1s
- `shouldRetry(\Throwable $e): bool`
  - Retry: InferenceException (нехватка памяти), сетевые ошибки, таймауты FFI
  - Не retry: ModelLoadException, ShapeMismatchException, ConfigurationException

**Интеграция в Model Hub:**
- Downloader использует RetryHandler для сетевых ошибок

---

## ЧАСТЬ 5: ДОКУМЕНТАЦИЯ И CI/CD

---

## ШАГ 129: Полная документация

**Файлы:**
- `README.md` (корень) — обзор, установка, Quick Start
- `docs/getting-started.md` — первый запуск
- `docs/configuration.md` — все опции конфигурации
- `docs/backends/onnx.md` — ONNX Runtime: установка, провайдеры, модели
- `docs/backends/llama.md` — llama.cpp: установка, параметры, форматы
- `docs/backends/cpu.md` — CPU fallback
- `docs/embedding.md` — эмбеддинги
- `docs/vector-store.md` — векторное хранилище
- `docs/pipeline.md` — пайплайны
- `docs/model-hub.md` — управление моделями
- `docs/tokenizer.md` — токенизация
- `docs/streaming.md` — стриминг токенов
- `docs/security.md` — безопасность и верификация
- `docs/laravel.md` — интеграция с Laravel
- `docs/symfony.md` — интеграция с Symfony
- `docs/deployment.md` — деплой: FPM, RoadRunner, FrankenPHP, Docker
- `docs/troubleshooting.md` — частые ошибки и решения
- `docs/api-reference.md` — полное API (генерируется из phpdoc)
- `CHANGELOG.md` — список изменений по версиям

---

## ШАГ 130: CI/CD

**Файл:** `.github/workflows/ci.yml`

**Jobs:**
1. **Tests (матрица):**
   - PHP: 8.5, 8.6
   - OS: ubuntu-latest, macos-latest, windows-latest
   - Команда: `composer test` (все тесты)
2. **Static Analysis:**
   - PHPStan level 8
   - Psalm level 3
3. **Code Style:**
   - PHP CS Fixer (PER-CS 2.0)
4. **Security:**
   - Composer audit
5. **Build Binaries (release only):**
   - Компиляция ONNX Runtime для платформ
   - Компиляция llama.cpp для платформ
   - Сборка sqlite-vec для платформ
   - Загрузка артефактов в GitHub Release

---

## ШАГ 131: Docker-образ

**Файл:** `Dockerfile`

**Детали реализации:**
- Базовый образ: `php:8.5-cli` + `php:8.5-fpm`
- Установка расширений: ffi, pdo_sqlite, sodium, zip, opcache, shmop
- Копирование нативных бинарников (ONNX Runtime, llama.cpp)
- Предустановка ferry-ai/php-inference
- Предварительное скачивание популярных моделей (опционально)
- Entrypoint: проверка окружения (`ferry-ai check`)

**docker-compose.yaml:**
- PHP-FPM + Nginx
- Volume для кэша моделей
- Переменные окружения для конфигурации

---

## ЧАСТЬ 6: ПАКЕТ `dataframe` (отложен)

---

## ШАГ 132–137: DataFrame

Создаются только при наличии спроса и ресурсов. Подробный план — в отдельном документе.

---

## ИТОГОВЫЙ ИНТЕГРАЦИОННЫЙ ТЕСТ ФАЗЫ 4

```bash
# 1. Проверка окружения
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config([]);
echo 'Environment: PHP ' . PHP_VERSION . ' on ' . PHP_OS_FAMILY . PHP_EOL;
echo 'Available backends: ' . json_encode(array_keys(AI::availableBackends())) . PHP_EOL;
"

# 2. Проверка requirements
php -r "
require 'vendor/autoload.php';
// Проверка обязательных расширений
\$required = ['ffi', 'json', 'hash', 'fileinfo'];
\$issues = [];
foreach (\$required as \$ext) {
    if (!extension_loaded(\$ext)) \$issues[] = \"Missing extension: \$ext\";
}
if (count(\$issues) > 0) {
    echo 'Issues found:' . PHP_EOL;
    foreach (\$issues as \$issue) echo '  - ' . \$issue . PHP_EOL;
} else {
    echo 'All required extensions loaded' . PHP_EOL;
}
"

# 3. Бенчмарк эмбеддингов
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
AI::config(['backend' => 'onnx', 'device' => 'cpu']);
\$start = microtime(true);
for (\$i = 0; \$i < 10; \$i++) {
    AI::embed('Hello world ' . \$i);
}
\$duration = (microtime(true) - \$start) * 1000;
echo '10 embeddings: ' . round(\$duration) . 'ms (' . round(\$duration/10) . 'ms each)' . PHP_EOL;
"

# 4. Стриминг в контексте HTTP
php -r "
require 'vendor/autoload.php';
use FerryAI\AI;
use FerryAI\StreamResponse;
AI::config(['backend' => 'llama']);
\$response = StreamResponse::toSse(AI::stream([['role' => 'user', 'content' => 'Hi']]));
echo 'SSE Response status: ' . \$response->getStatusCode() . PHP_EOL;
"

# 5. Laravel Service Provider
php -r "
require 'vendor/autoload.php';
// Симулируем Laravel-окружение
\$app = new class {
    public function singleton(\$abstract, \$concrete) {}
    public function runningInConsole() { return true; }
};
\$provider = new \FerryAI\Laravel\AIServiceProvider(\$app);
\$provider->register();
\$provider->boot();
echo 'Laravel integration OK' . PHP_EOL;
"

# 6. Все unit-тесты
php vendor/bin/phpunit
```

**Все тесты должны пройти без ошибок.**

---

## КРИТЕРИИ ГОТОВНОСТИ ФАЗЫ 4 (Production Readiness)

### Производительность
- [ ] Эмбеддинг (384-dim): < 10ms на CPU, < 2ms на GPU
- [ ] LLM: time-to-first-token < 500ms
- [ ] LLM: tokens-per-second > 20 на CPU, > 50 на GPU
- [ ] Vector Store: поиск в 100k векторах < 50ms
- [ ] Pipeline: 1000 элементов через 3 стадии < 5 секунд

### Стабильность
- [ ] 1000 последовательных инференсов без утечек памяти
- [ ] 24-часовой стресс-тест без падений
- [ ] Все исключения обрабатываются и не обрушивают процесс
- [ ] Модели корректно выгружаются (нет abandoned FFI-ресурсов)

### Кроссплатформенность
- [ ] Linux x86_64: все тесты проходят
- [ ] Linux aarch64: все тесты проходят (или документированные ограничения)
- [ ] macOS x86_64: все тесты проходят
- [ ] macOS arm64: все тесты проходят
- [ ] Windows x86_64: все тесты проходят (или документированные ограничения)

### Безопасность
- [ ] Нет зависимостей с известными CVE (`composer audit`)
- [ ] Модели из недоверенных источников не загружаются без верификации
- [ ] Нет доступа к файловой системе за пределами `model_cache`
- [ ] Таймауты предотвращают зависания

### Документация
- [ ] Полный API reference
- [ ] Getting Started за < 5 минут до первого инференса
- [ ] Примеры для всех use cases (RAG, чат, классификация)
- [ ] Руководство по деплою

### Инфраструктура
- [ ] CI/CD проходит на всех платформах
- [ ] Docker-образ собирается и работает
- [ ] Нативные бинарники доступны для всех платформ
- [ ] Автоскачивание бинарников работает

---

> **План реализации Фазы 4 завершён. После успешного прохождения всех шагов и критериев готовности платформа считается production-ready.**

---

## ИТОГОВАЯ СВОДКА ВСЕХ ФАЗ

| Фаза | Шаги | Файлов | Длительность | Ключевой результат |
|---|---|---|---|---|
| Фаза 1 | 1–52 | 53 | 2–3 мес. | ONNX инференс |
| Фаза 2 | 53–75 | 23 (21 новый + 2 обновления) | 2–3 мес. | LLM чат + стриминг |
| Фаза 3 | 76–117 | 42 (40 новых + 2 обновления) | 3–4 мес. | Экосистема (embed, vector, pipeline, model-hub) |
| Фаза 4 | 118–131 (+122a) | 14 основных + 2 ONNX-провайдера (Intel/AMD) (+ 6 DataFrame отложенных) | 4–6 мес. | Production |
| **Всего** | **131 (+122a)** | **137 файлов** (см. FILE_TREE.md) | **11–16 мес.** | FerryAI v1.0 |
