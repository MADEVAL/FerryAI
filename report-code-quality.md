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

## [ВАЖНО] `SamplingParams`: NaN обходит валидацию диапазонов

Файл: `packages/core/src/ValueObjects/SamplingParams.php`, строки 27, 31
Категория: Надёжность

Проблема: проверки вида `$x < min || $x > max` не отсекают `NAN` (любое сравнение с NaN — `false`). Плюс `frequencyPenalty` и `presencePenalty` не валидируются вообще.

Доказательство:
```php
if ($temperature < 0.0 || $temperature > 2.0) { throw ...; }   // NAN проходит
if ($topP < 0.0 || $topP > 1.0) { throw ...; }                 // NAN проходит
// frequencyPenalty / presencePenalty — без единой проверки
```

Вектор / последствие: `new SamplingParams(temperature: NAN)` конструируется успешно, NaN течёт в softmax → NaN-логиты → некорректный вывод без исключения.

Решение: проверять через положительную форму: `if (!($temperature >= 0.0 && $temperature <= 2.0))`; добавить диапазоны для `frequencyPenalty`/`presencePenalty` (например `[-2.0, 2.0]`).

---

## [ВАЖНО] GGUF: тип int8 читает байт дважды и сбивает позицию потока

Файл: `packages/model-hub/src/Format/GgufInspector.php`, строка 155
Категория: Надёжность

Проблема: тернарный оператор читает 1 байт в условии (потребляя его), затем в ветке `else` читает **ещё один** байт для `unpack`. На значение типа int8 расходуется 2 байта вместо 1 — все последующие поля метаданных читаются со смещением.

Доказательство:
```php
1 => self::readBytes($handle, 1) === '' ? 0 : \unpack('c', self::readBytes($handle, 1))[1] ?? 0,
```
`GgufInspector::metadata()` — публичный API, есть unit-тест (`GgufInspectorTest.php:62`), требующий непустой результат для валидного GGUF.

Вектор / последствие: любое int8-поле и всё, что идёт после него (имя, словарь, счётчики тензоров), парсится из неверного оффсета — тихо неверные метаданные.

Решение: прочитать байт один раз:
```php
1 => (($b = self::readBytes($handle, 1)) === '') ? 0 : (\unpack('c', $b)[1] ?? 0),
```

---

## [ВАЖНО] Grammar-сэмплер отбрасывает не-ASCII токены и рассинхронизирует грамматику

Файл: `packages/llama-backend/src/Sampling/GrammarSampler.php`, строки 89, 121
Категория: Надёжность

Проблема: `computeValidFirstChars()` тестирует только печатный ASCII (32–126). Fast-path в строке 89 отбрасывает любой токен, чей первый байт (≥128, т.е. любой UTF-8 многобайтовый символ) отсутствует в наборе — до вызова `isViable()`. Когда все кандидаты отброшены, управление уходит на безусловный `delegate` (строка 104), который **не дописывает** выбранный кусок в `$this->accumulated`.

Доказательство:
```php
for ($c = 32; $c <= 126; ++$c) { /* только ASCII */ }         // строка 121
// ...
if ($validFirstChars !== null && !isset($validFirstChars[$piece[0]])) {
    continue;                                                 // строка 89 — CJK/emoji отсеиваются
}
// ...
return $this->delegate->sample($logits, $params);             // строка 104 — accumulated НЕ обновлён
```

Вектор / последствие: при генерации строковых значений с не-ASCII содержимым (грамматика JSON допускает `[^"]*`) состояние грамматики рассинхронизируется, дальнейшие `isViable`-проверки идут по устаревшему префиксу — ограничение грамматики фактически нарушается.

Решение: тестировать также байты 128–255 (и, при необходимости, 0–31), либо убрать fast-path и всегда вызывать `isViable()`.

---

## [ВАЖНО] Экспоненциальный рост GBNF из JSON Schema при опциональных свойствах

Файл: `packages/llama-backend/src/Grammar/JsonSchemaConverter.php`, строка 169
Категория: Надёжность

Проблема: `objectTail()` для опционального свойства встраивает подстроку `$sub` дважды — внутри сегмента и как альтернативу. Размер грамматики растёт как O(2^n) по числу опциональных свойств.

Доказательство:
```php
$sub = $this->objectTail($pieces, $i + 1);
$segment = 'ws "," ws ' . $piece . ($sub === '""' ? '' : ' ' . $sub);  // $sub #1
// ...
return '((' . $segment . ') | ' . $sub . ')';                          // $sub #2 → удвоение
```

Вектор / последствие: схема с ~25+ опциональными полями порождает грамматику в сотни МБ → исчерпание памяти (DoS), если схема приходит из недоверенного источника.

Решение: генерировать отдельные именованные GBNF-правила для групп свойств вместо инлайна; либо ограничить число опциональных свойств.

---

## [ВАЖНО] Нативный токенизатор отбрасывает валидный конечный токен id 0

Файл: `packages/tokenizer/src/HuggingFaceTokenizer.php`, строка 69
Категория: Надёжность

Проблема: после копирования ровно `$r->len` идентификаторов код безусловно срезает хвостовые нули. Токен с id `0` — легитимен во многих словарях (часто `<unk>`/`<pad>`/`!`), поэтому любая последовательность, заканчивающаяся на id 0, тихо укорачивается.

Доказательство:
```php
for ($i = 0; $i < $r->len; ++$i) { $ids[] = $r->token_ids[$i]; }
// ...
// tokenizers-cpp always writes 128 elements (fixed buffer); trim trailing zeros.
while ($ids !== [] && \end($ids) === 0) { \array_pop($ids); }  // строка 69
```
Раз копирование уже использует точную длину `$r->len`, обрезка нулей не нужна и вредна.

Вектор / последствие: неверные последовательности токенов → неверные эмбеддинги/классификация для входов, оканчивающихся на токен id 0.

Решение: доверять `$r->len` и убрать обрезку. Если буфер действительно фиксированный, возвращать из нативного слоя истинную длину.

---

## [ВАЖНО] CSV: разделитель переопределяется (файл переоткрывается) на каждой строке

Файл: `packages/dataframe/src/IO/CsvReader.php`, строка 27
Категория: Надёжность

Проблема: `detectDelimiter($path)` вызывается внутри условия `while`, т.е. на каждую строку данных заново выполняется `fopen`/`fgets`/`fclose` того же файла.

Доказательство:
```php
while (($row = \fgetcsv($handle, 0, $this->detectDelimiter($path), '"', '\\')) !== false) {
```

Вектор / последствие: O(строк) лишних открытий файла и второй дескриптор на тот же файл в каждой итерации — серьёзная деградация на больших/медленных CSV.

Решение: вычислить разделитель один раз перед циклом и сохранить в локальную переменную.

---

## [ВАЖНО] C-обёртка не валидирует аргументы на границе FFI (переполнение буфера / разыменование NULL)

Файл: `native/llama-wrapper/ferry_llama.c`, строки 112, 115, 123, 173-210
Категория: Надёжность / Безопасность

Проблема: ни одна экспортируемая функция не проверяет указатели и размеры. Наиболее опасное:
- `ferry_eval`: при отрицательном `out_size` проверка `n_vocab > out_size` истинна → `n_vocab` становится отрицательным → `(size_t) n_vocab` в `memcpy` = гигантское число.
- `ferry_tokenize`: `strlen(text)` без проверки `text != NULL`.
- `ferry_eval_topk`: пишет до `k` элементов в `out_ids`/`out_logits`, но параметра размера буфера нет вообще.

Доказательство:
```c
if (n_vocab > out_size) n_vocab = out_size;              // 112: out_size<0 => n_vocab<0
memcpy(out, logits, (size_t) n_vocab * sizeof(float));   // 115: SIZE_MAX*4
```
```c
return llama_tokenize(vocab, text, (int) strlen(text), ...); // 123: text может быть NULL
```
```c
FERRY_API int ferry_eval_topk(..., int k, int * out_ids, float * out_logits) { // нет out_size
    out_logits[pos] = v; out_ids[pos] = t;               // запись по pos<k без границы буфера
```

Вектор / последствие: аргументы приходят из PHP-кода Ferry (граница доверия внутри процесса), но любая ошибка/несоответствие размеров ведёт к SIGSEGV или порче кучи в процессе PHP. Функция, пересекающая границу FFI, обязана защищать себя.

Решение: в начале каждой функции отклонять `NULL`-указатели (`return -1/-2`) и `out_size <= 0`; добавить параметр `out_size` в `ferry_eval_topk` и ограничивать каждую запись `pos < out_size`.

---

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

## [ВАЖНО] Утечка нативной модели при ошибке создания контекста

Файл: `packages/llama-backend/src/Runtime/NativeLlamaRuntime.php`, строки 53-55
Категория: Надёжность

Проблема: `ferry_load_model` выделяет нативную память, затем вызывается `newContext`. Если создание контекста завершится ошибкой/исключением, указатель модели нигде не освобождается (`freeModel` не вызывается).

Доказательство:
```php
$model = $ffi->loadModel($modelPath, $modelParams->nGpuLayers);   // выделение
$threads = $contextParams->nThreads > 0 ? $contextParams->nThreads : 4;
$context = $ffi->newContext($model, $contextParams->nCtx, $threads); // при провале $model теряется
```

Вектор / последствие: повторные неудачные загрузки накапливают утёкшую RAM/VRAM.

Решение: обернуть создание контекста в try/catch и вызывать `$ffi->freeModel($model)` перед пробросом исключения.

---

## [ВАЖНО] RetryHandler повторяет невосстановимые ошибки (в т.ч. ValidationException)

Файл: `packages/core/src/RetryHandler.php`, строки 24, 48-67
Категория: Надёжность

Проблема: `catch (\Throwable)` перехватывает и `\TypeError`/`\Error`, и валидационные ошибки. `shouldRetry()` не исключает `ValidationException` — некорректный ввод повторяется впустую (3× со сном).

Доказательство:
```php
} catch (\Throwable $e) { $lastException = $e; $attempt++; /* ... */ if (!self::shouldRetry($e)) break; }
```
```php
public static function shouldRetry(\Throwable $e): bool {
    // ModelLoad / ShapeMismatch / Configuration / ModelNotFound исключены,
    // но ValidationException и \Error — нет
    return true;
}
```

Вектор / последствие: `\TypeError` от неверных аргументов и `ValidationException` от плохих `SamplingParams` прогоняются через полный retry-цикл с задержками, хотя успех невозможен.

Решение: ловить `\Exception` (не `\Throwable`), добавить `ValidationException` в список не-повторяемых.

---

## 🟡 УЛУЧШЕНИЕ

## [УЛУЧШЕНИЕ] RetryHandler: нет потолка backoff и бросается нативный `\RuntimeException`

Файл: `packages/core/src/RetryHandler.php`, строки 38, 45
Категория: Надёжность / Архитектура

Проблема: экспоненциальная задержка `2 ** ($attempt-1)` без ограничения — при умеренном `maxAttempts` даёт очень долгие `usleep` (напр. 512 c на 10-й попытке); при абсурдно больших `maxAttempts` `2**n` даёт `INF`, `(int) INF === 0` → мгновенный цикл. Плюс fallback `new \RuntimeException('Retry exhausted')` нарушает правило AGENTS.md «все исключения наследуют `FerryAIException`».

Доказательство:
```php
$delay = $backoff === 'exponential' ? (int) ($delayMs * (float) (2 ** ($attempt - 1))) : $delayMs;
// ...
throw $lastException ?? new \RuntimeException('Retry exhausted');   // строка 45
```

Решение: ограничить `min($delay, MAX_BACKOFF_MS)`; заменить `\RuntimeException` на исключение, наследующее `FerryAIException`.

---

## [УЛУЧШЕНИЕ] `Hub`: утечка временного файла, `register()` игнорирует `$sha256`, необратимый ключ, потерянный путь в генераторе

Файл: `packages/model-hub/src/Hub.php`, строки 42/59, 166, 179, 118
Категория: Надёжность / Качество

Проблема (несколько мелких, один файл):
1. `download()` качает в `sys_get_temp_dir()`, затем `cache->put()` **копирует** (не перемещает) — временный файл никогда не удаляется (утечка диска).
2. `register(string $name, string $path, ?string $sha256 = null)` принимает `$sha256`, но нигде его не использует — мёртвый параметр, ложное ощущение проверки.
3. `checkUpdates()`: `str_replace('_', '/', $cacheKey)` не обратен к `str_replace('/', '_', ...)` — id с подчёркиваниями искажаются, суффикс версии теряется.
4. `downloadWithProgress()`: путь назначения не отдаётся через `yield` (в кэш-ветке уходит в `getReturn()`, в live-ветке не отдаётся вовсе) — итерирующий прогресс не узнаёт, куда записан файл.

Доказательство:
```php
$destPath = \sys_get_temp_dir() . '/' . $cacheKey . '.model'; // 42
$this->cache->put($cacheKey, $destPath);                      // 59 — copy, temp не удалён
```
```php
public function register(string $name, string $path, ?string $sha256 = null): void {
    $this->cache->put($name, $path);                          // 168 — $sha256 не используется
}
```
```php
$modelId = \str_replace('_', '/', $cacheKey);                 // 179 — необратимо
```

Решение: `rename` вместо `copy` или `unlink` temp; либо использовать `$sha256` в `register` (проверять/сохранять), либо убрать параметр; хранить оригинальный id рядом с ключом; отдавать финальный путь через `yield`.

---

## [УЛУЧШЕНИЕ] `AI::warmup()` не работает: ключ прогрева не совпадает с ключом выборки

Файл: `packages/ai/src/AI.php`, строка 487; `packages/ai/src/ModelPool.php`, строка 45
Категория: Надёжность

Проблема: `ModelPool::warmup()` кладёт модель под голый `$modelId`, а `AI::loadPooled()` ищет по составному ключу `backendClass|path|device`. Прогретые записи никогда не находятся.

Доказательство:
```php
// ModelPool.php:45
$this->put($modelId, $model, $model->metadata()->sizeBytes);
// AI.php:487
$key = $backend::class . '|' . $modelPath . '|' . ($device === null ? 'auto' : $device->value);
$cached = $pool->acquire($key);
```

Вектор / последствие: первый реальный запрос всё равно грузит модель «вхолодную», а прогретая копия навсегда занимает память/бюджет пула.

Решение: строить один и тот же составной ключ в `warmup` и `loadPooled` через общий билдер ключа.

---

## [УЛУЧШЕНИЕ] Реестр `EmbeddedModels` не используется — молча неверный pooling по умолчанию

Файл: `packages/embedding/src/Embedder.php`, строки 34-40
Категория: Архитектура

Проблема: `Embedder` выбирает стратегию pooling только из аргумента конструктора (по умолчанию `'mean'`) и никогда не сверяется с `EmbeddedModels`, где для моделей задано рекомендуемое поле `pooling` (например `bge-*` → `cls`) и `dimension`. Реестр — мёртвые данные (используется только в собственном тесте).

Доказательство:
```php
$this->poolingStrategy = match ($pooling) {
    'mean' => new MeanPooling(), 'cls' => new ClsPooling(),
    'eos'  => new EosPooling(),  'max' => new MaxPooling(),
    default => new MeanPooling(),
};
```

Вектор / последствие: эмбеддинг модели, требующей `cls`, с настройками по умолчанию молча получает `mean` → деградированные эмбеддинги без предупреждения.

Решение: при совпадении `EmbeddedModels::get($modelName)` брать `pooling`/`dimension` из реестра, если вызывающий явно не переопределил.

---

## [УЛУЧШЕНИЕ] Несогласованная обработка attention-mask между стратегиями pooling

Файл: `packages/embedding/src/Pooling/MaxPooling.php` (строки 21-27), `EosPooling.php` (строка 18)
Категория: Качество

Проблема: контракт `PoolingStrategy` документирует `attentionMask` (1 = токен, 0 = паддинг). `MeanPooling` его учитывает, а `MaxPooling` и `EosPooling` принимают параметр и полностью игнорируют. В текущем рантайме `Embedder::embed` строит маску из одних единиц (паддинга нет), поэтому последствий в продакшене сейчас нет — это латентный баг и нарушение контракта, который проявится, если pooling когда-либо получит батч с паддингом.

Доказательство:
```php
// MaxPooling.php — параметр $attentionMask не используется
for ($i = 1; $i < $seqLen; $i++) { for ($j = 0; $j < $hiddenDim; $j++) { /* max по всем позициям */ } }
// EosPooling.php:18 — возвращает последнюю строку, игнорируя маску
return $hiddenStates[$lastIndex];
```

Решение: вынести общий помощник «позиции по маске» в базу `PoolingStrategy` и применять во всех стратегиях единообразно (в `Max` — пропускать `mask[i]==0`; в `Eos` — брать последний индекс с `mask[i]!=0`).

---

## [УЛУЧШЕНИЕ] Игнорируемые параметры интерфейса рантайма: `$special` и `$nPast`

Файл: `packages/llama-backend/src/Runtime/NativeLlamaRuntime.php`, строки 92-97, 111-116
Категория: Качество

Проблема: `LlamaRuntimeInterface` объявляет `tokenize(..., bool $special)` и `evaluate(..., int $nPast)`, но реализация их не передаёт в FFI. Для `$nPast` это осознанно (нативный слой сам ведёт позицию KV-кэша, см. комментарий в `ferry_llama.c`), но параметр в контракте вводит в заблуждение; `$special` же просто теряется без альтернативы в нативном API.

Доказательство:
```php
public function tokenize(LlamaSession $session, string $text, bool $addBos = true, bool $special = true): array {
    return $s->ffi->tokenize($s->model, $text, $addBos);   // $special отброшен
}
public function evaluate(LlamaSession $session, array $tokens, int $nPast): array {
    return $s->ffi->eval($s->context, $s->model, $tokens, $s->nVocab); // $nPast отброшен
}
```

Решение: реализовать `$special` в нативной обёртке либо убрать из контракта; для `$nPast` — либо задействовать, либо удалить из сигнатуры и задокументировать автоматическое отслеживание позиции.

---

## [УЛУЧШЕНИЕ] Нарушение правила «все исключения наследуют FerryAIException»

Файл: `packages/core/src/RetryHandler.php:45`; `packages/llama-backend/src/Runtime/NativeLlamaRuntime.php:149,165`; `packages/cpu-backend/src/RubixMLAdapter.php:39,80,98,102`; `packages/tokenizer/src/HuggingFaceTokenizer.php:240`
Категория: Архитектура

Проблема: AGENTS.md требует, чтобы все исключения расширяли `FerryAIException` с `errorCode()`. В указанных местах бросаются нативные `\RuntimeException`/`\InvalidArgumentException`, которые ускользают из `catch (FerryAIException)` и ломают систему кодов `FERRY_AI_*`. (Все 12 классов в `Exception/` правилу соответствуют — нарушения только в этих не-exception-классах.)

Доказательство:
```php
throw new \RuntimeException('Retry exhausted');                 // RetryHandler.php:45
throw new \RuntimeException('ferry_llama wrapper not found...'); // NativeLlamaRuntime.php:149
throw new \InvalidArgumentException('NativeLlamaRuntime requires a NativeLlamaSession.'); // :165
throw new \RuntimeException('RubixML is not installed');        // RubixMLAdapter.php:39
```

Решение: заменить на подходящие подклассы `FerryAIException` (`InvalidStateException`, `BackendNotAvailableException`, новый `RetryExhaustedException` и т.п.).

---

## [УЛУЧШЕНИЕ] Общая память: сегменты доступны всем (0644) и коллизии ключей crc32

Файл: `packages/ai/src/SharedMemoryManager.php`, строки 45, 125
Категория: Безопасность / Надёжность

Проблема: сегменты создаются с правами `0644` (читаемы любым пользователем хоста); System V-ключ берётся как `crc32($modelId) & 0x7FFFFFFF` — 32-битная контрольная сумма, разные id могут дать один ключ, при этом `allocateModel` не проверяет владельца существующего сегмента. Функция опциональна: `ModelPool::shareModel()` из фасада `AI` сейчас не вызывается, но это публичный API.

Доказательство:
```php
$shmId = \shmop_open($key, 'c', 0644, $size);          // 45 — world-readable
// ...
return \crc32($modelId) & 0x7FFFFFFF;                  // 125 — узкий ключ, возможны коллизии
```

Вектор / последствие: при использовании shared memory локальный пользователь может прочитать байты модели (утечка весов); коллизия ключей → одна модель читает байты другой между FPM-воркерами.

Решение: права `0600`; ключ из более широкого хэша + реестр id→key с проверкой владения; при существующем сегменте проверять/пересоздавать по несовпадению размера.

---

## [УЛУЧШЕНИЕ] Logger: незачекан результат записи, нет ротации

Файл: `packages/core/src/Logger.php`, строка 70
Категория: Надёжность

Проблема: результат `file_put_contents` не проверяется — при полном диске/отказе прав сообщения молча теряются; файл лога растёт без ограничения.

Доказательство:
```php
\file_put_contents($this->logFile, $entry . "\n", FILE_APPEND | LOCK_EX);  // без проверки
```

Решение: проверять возврат, при ошибке — fallback в `error_log()`; добавить опциональную ротацию по размеру.

---

## [УЛУЧШЕНИЕ] `inferShape` не детектирует «рваные» (jagged) массивы

Файл: `packages/core/src/Tensor/CommonTensorOps.php`, строки 19-31
Категория: Надёжность

Проблема: форма выводится по первому элементу на каждом уровне; неоднородные вложенные массивы (`[[1,2],[3]]`) принимаются молча.

Доказательство:
```php
while (\is_array($node)) {
    $dims[] = \count($node);
    $key = \array_key_first($node);
    $node = $key === null ? null : ($node[$key] ?? null);
}
```

Вектор / последствие: неверная форма и рассинхрон количества элементов для `strides()`/`reshape()` без ошибки. (В текущем рантайме входы регулярны, поэтому это латентно.)

Решение: после вывода формы проверять, что произведение размерностей равно числу листовых элементов; иначе — `ShapeMismatchException`.

---

## 🔵 РЕФАКТОРИНГ

## [РЕФАКТОРИНГ] `AsyncInference::runParallel()` фактически последовательный

Файл: `packages/ai/src/AsyncInference.php`, строка 73
Категория: Качество

Проблема: метод просто перебирает задачи в `foreach` — никакого планирования Fiber или конкурентности нет.

Доказательство:
```php
foreach ($tasks as $i => $task) { $results[$i] = $task(); }
```

Решение: реализовать реальное чередование Fiber (запустить все, круговой `resume`) либо переименовать в `runSequential`/задокументировать ограничение.

---

## [РЕФАКТОРИНГ] Дублирование логики стриминга скачивания

Файл: `packages/model-hub/src/Downloader.php` и `packages/model-hub/src/HuggingFaceClient.php`
Категория: Качество

Проблема: обе реализации независимо повторяют паттерн `fopen → чтение чанками (8192) → fwrite` с почти идентичной обработкой (~60 строк дубля).

Решение: вынести общий `StreamDownloader`; или заставить `HuggingFaceClient::downloadFile` делегировать в `Downloader`.

---

## [РЕФАКТОРИНГ] Дублирование конфигурации Laravel/Symfony адаптеров

Файл: `packages/laravel/src/AIServiceProvider.php` и `packages/symfony/src/DependencyInjection/FerryAIExtension.php`
Категория: Качество / Архитектура

Проблема: приватный `env()` и построение массива дефолтов скопированы байт-в-байт в обоих провайдерах. Плюс `symfony/.../Configuration.php` описывает дерево конфигурации, которое `FerryAIExtension` не использует (мёртвый класс), а `AIServiceProvider::$app` хранится, но не читается.

Решение: вынести дефолты в общий `FerryAI\Core\ConfigDefaults` (или трейт); задействовать `Configuration::getConfigTree()` в расширении или удалить его; убрать неиспользуемый `$app`.

---

## [РЕФАКТОРИНГ] Хардкод путей/паролей окружения разработки в релизных артефактах

Файл: `native/llama-wrapper/build.ps1:32`; `examples/21-postgres-vector.php:22`
Категория: Качество

Проблема: `build.ps1` жёстко задаёт путь к `vcvars64.bat` только для VS2022 Community; пример Postgres по умолчанию использует общеизвестный пароль `postgres`.

Доказательство:
```powershell
$vcvars = "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat"
```
```php
$pass = getenv('FERRY_AI_PG_PASSWORD') ?: 'postgres';
```

Решение: искать `vcvars64.bat` через `vswhere.exe`; в примере не задавать пароль по умолчанию (пустой или явная ошибка).

---

## Что проверено и НЕ является дефектом (отклонено при верификации)

- **`SamplerMath::softmax` ветка `$sum <= 0.0`** — не мёртвый код: достижима при `-INF`-логитах; оставлена как защитная.
- **`MaxPooling`/`EosPooling` дают «неверные эмбеддинги»** — в текущем пути `Embedder::embed` маска всегда из единиц (паддинга нет), поэтому активного дефекта в проде нет; понижено до латентной несогласованности контракта (см. 🟡).
- **`SignatureVerifier` «обходится»** — при передаче не-пути `file_get_contents` вернёт `false` и `verify()` вернёт `false` (fail-closed, безопасно). Это несогласованность API (sha256 — значение, signature/publicKey — пути), а не обход проверки.
- **`Embedder::normalize` / `cosineSimilarity`** — деление на норму защищено (`=== 0.0`), деления на ноль нет.
- **`ArrayTensor::transpose`** — оси валидируются (`assertAxesPermutation`); дефект только в `CpuNativeTensor::transpose`, где такой проверки нет (несогласованность бэкендов).
- **ONNX-провайдеры** — массив провайдеров корректно освобождается; неинициализированного чтения нет.
- **`crc32`/shared memory** — реальный эффект только при явном вызове `ModelPool::shareModel()`, который фасад `AI` сейчас не использует.
