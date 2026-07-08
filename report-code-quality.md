# Ferry AI — Отчёт код-ревью

Дата: 2026-07-07
Область: `bin/`, `benchmarks/`, `docs/`, `examples/`, `native/`, `packages/`, `scripts/`, `tests/`
Метод: полное чтение исходников (154 src-файла в 14 пакетах + C-обёртка), трассировка пути исполнения от входа до последствия. В отчёт включены только проблемы, подтверждённые чтением реального кода и прослеживанием вызовов.

Легенда приоритетов:
- 🔴 КРИТИЧНО — уязвимость с реальным вектором атаки
- 🟠 ВАЖНО — ненадёжность с реальными последствиями в production
- 🟡 УЛУЧШЕНИЕ — качество/архитектура
- 🔵 РЕФАКТОРИНГ — спорные, но рабочие решения

Сводка: 1 🔴 · 12 🟠 · 10 🟡 · 4 🔵

---

## 🔴 КРИТИЧНО

## [КРИТИЧНО] Object injection через `unserialize()` при загрузке CPU-модели

Файл: `packages/cpu-backend/src/CpuNativeBackend.php`, строка 52 (дубль: `packages/cpu-backend/src/RubixMLAdapter.php`, строка 62)
Категория: Безопасность

Проблема: содержимое файла модели десериализуется через `unserialize()` без опции `allowed_classes`. Магические методы (`__wakeup`/`__destruct`) объектов срабатывают в момент десериализации — до проверки `is_array()`. Это основной путь загрузки, когда `rubix/ml` не установлен.

Доказательство:
```php
// CpuNativeBackend.php:46-56 — вызывается из AI::predict() -> loadPooled() -> backend->load()
$content = \file_get_contents($source);
// ...
$data = @\unserialize($content);            // строка 52 — нет allowed_classes
if ($data === false || !\is_array($data)) {
    throw new ModelLoadException($source, 'Invalid or unsupported RubixML model format');
}
```
```php
// RubixMLAdapter.php:62 — fallback-путь, тот же дефект
$data = \unserialize($content);
```

Вектор / последствие: файл модели из недоверенного источника (скачанный с HF, загруженный пользователем, подменённый в кэше) при загрузке запускает gadget-цепочку → RCE / удаление файлов / SSRF в момент `AI::predict()`.

Решение: `unserialize($content, ['allowed_classes' => false])` (путь ожидает только массив, `is_array`-гард уже есть). В `RubixMLAdapter` — ограничить список ожидаемыми классами оценщиков или тоже `false` для табличных данных.

---

## 🟠 ВАЖНО

## [ВАЖНО] Скачанный нативный бинарник загружается через FFI без проверки целостности

Файл: `packages/ai/src/NativeBinaryManager.php`, строки 24, 62-70
Категория: Безопасность

Проблема: `download()` пишет `.so/.dll` из сети в кэш, но метод `verify()` (SHA-256) на этом пути не вызывается; `file_put_contents` не проверяет результат. Далее `resolve()` сначала ищет библиотеку в `PATH` и возвращает первое совпадение, которое затем грузится через `FFI::cdef` в `AIFactory`.

Доказательство:
```php
$data = @\file_get_contents($url);
if ($data === false) { throw new IoException(...); }
\file_put_contents($destPath, $data);          // строка 68 — без verify(), без проверки записи
return $destPath;
```
```php
$systemPath = $this->findInSystem($library);   // строка 24 — PATH раньше кэша
if ($systemPath !== null) { return $systemPath; }
```

Вектор / последствие: MITM/компрометация хоста релизов/подмена `FERRY_AI_NATIVE_BINARIES_URL` (или запись в ранний элемент `PATH`) → загрузка вредоносной библиотеки → выполнение нативного кода в процессе PHP.

Решение: требовать и проверять ожидаемый SHA-256 внутри `download()` до того, как файл станет доступен (удалять при несовпадении); предпочитать контролируемый кэш перед `PATH`; проверять код возврата записи.

---