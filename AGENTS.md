# AGENTS.md — FerryAI (ferry-ai/php-inference)

Унифицированный **inference**-рантайм для PHP 8.5+: единый API поверх нативных движков
(ONNX Runtime, llama.cpp, RubixML/Tensor). Только вывод, **не обучение**.

- **«Прямой» инференс** = прямо в PHP-приложении, без Python-микросервисов. Механизм доступа
  к нативным движкам — **PHP FFI** (единственный мост; см. `docs/SKILL.md` §Architecture Rules,
  `docs/RESEARCH_ARCHITECTURE.md` §2.2/§8). Компилируемое нативное расширение — лишь возможная
  будущая фаза, по докам не приоритетна.
- Namespace: `FerryAI\` · Vendor: `ferry-ai/*` · Монорепо: `packages/{name}/`
- Полные правила и конвенции — в `docs/SKILL.md`. Архитектура — `docs/TECHNICAL_SPECIFICATION.md`.

## Стадия проекта (все фазы реализованы)

Все 4 фазы завершены. `composer.json`, `packages/`, `vendor/` присутствуют.
- Команды `composer …` работают.
- Пустые папки: `tests/Integration/`, `tests/Verification/`, `docs/specs/`.
- Для старта нового разработчика: `docs/REPOSITORY_INFRASTRUCTURE.md`, `docs/README.md`.

## Команды (единственный источник правды по инструментам)

> Окружение: **Windows PowerShell 5.1** — `&&` НЕ работает. Команды разделять `;`
> или запускать по одной. Пример: `composer cs-fix; composer stan; composer test`.

| Задача | Команда |
|---|---|
| Стиль (автофикс, PER-CS 2.0) | `composer cs-fix` |
| Стиль (проверка) | `composer cs-check` |
| PHPStan (level 8) | `composer stan` |
| Psalm (level 3) | `composer psalm` |
| Оба статанализатора | `composer analyse` |
| Все линтеры (cs-check + analyse) | `composer lint` |
| Юнит-тесты | `composer test` |
| Интеграционные (нужны нативные либы) | `composer test-integration` |
| Runtime-верификация багов/аудита | `composer verify` |
| Мутационное тестирование (Infection) | `composer mutation` |
| Покрытие (HTML + текст) | `composer coverage` |
| **Pre-commit: lint + test** | `composer check` |

Перед завершением/коммитом гейт — `composer check` (см. агент `verification-before-completion`).

## Тестирование (TDD)

Три слоя (см. `docs/SKILL.md` §Testing Doctrine):
- **Contract** — `packages/*/tests/Unit/Contracts/` — абстрактные тесты любого интерфейса.
- **Unit** — `packages/*/tests/Unit/` — изоляция, FFI замокан.
- **Integration** — `tests/Integration/`, группа `@group integration`, реальные модели.

Отдельно — **Verification** (`tests/Verification/`, `@coversNothing`, `composer verify`): runtime-тесты
для воспроизведения багов и проверки утверждений аудита. Валидированные дизайн-спеки — `docs/specs/`.

Правила:
- **FFI-моки — норма, не запрет.** Паттерн interface-and-mock: `NativeLlamaCpp` (боевой) /
  `MockLlamaCpp` (тест). Реальные объекты используем везде, кроме нативной FFI-границы.
- Локально пропустить нативные тесты: `FERRY_AI_SKIP_NATIVE=1`.
- Покрытие: контракты/enum/value-objects/исключения — 100%; бэкенды — ≥90%; Infection MSI ≥70%.

## Верификация и аудит

Проверять поведение **запуском**, а не чтением исходников (runtime test, not source inspection).
- Баг/находку аудита подтверждаем runtime-тестом в `tests/Verification/` (`@coversNothing`),
  вызывающим реальную функцию и наблюдающим неверное поведение; запуск — `composer verify`.
- Утверждать «готово/работает/passes» только после **свежего** прогона нужной команды из §Команды
  (см. агент `verification-before-completion`). Нет вывода команды — нет утверждения.

## Архитектурные правила (не обсуждается)

1. **Inference-only** — грузим модели, гоняем forward pass. Ни training, ни autograd, ни оптимизаторов.
2. **FFI — единственный мост** к нативному коду. Никакого `shell_exec` к Python.
3. **Изоляция бэкендов** — бэкенды не знают друг о друге; их сводит только пакет `ai`.
4. **Контракты — истина** — сигнатуры в `docs/INTERFACE_CONTRACTS.md`; реализация не отклоняется.
5. **Исключения** — все наследуют `FerryAIException`, у каждого `errorCode()` вида `FERRY_AI_*`.
6. **Zero-copy** — не копируем данные PHP↔native без нужды; `toArray()` помечать как дорогой.
7. **Без жёсткой привязки** к Laravel/Symfony; модели в репозиторий не коммитим.

## Документирование и язык (не обсуждается)

1. **Документируй каждый шаг.** Каждый реализованный шаг фиксируется в журнале разработки
   `docs/BUILD_LOG.md` (English): что сделано, зачем, какие файлы, тесты, результат `composer check`.
   Запись добавляется в том же цикле, что и код шага — «нет записи в журнале ⇒ шаг не завершён».
2. **Комментарии в коде — только на английском.** Все комментарии и PHPDoc в `.php` и в конфигах
   (`.neon`, `.xml`, `.dist`, `.gitattributes`, …) пишутся исключительно на английском.
3. **Проектная документация — только на английском.** Всё в `docs/` и любые новые документы —
    English-only. Существующие design-доки (`docs/SKILL.md`, `docs/TECHNICAL_SPECIFICATION.md`, … ) и этот
    `AGENTS.md` остаются как есть; правило применяется ко всей вновь создаваемой документации.

## Phase Awareness

Каждая задача принадлежит фазе (`docs/IMPLEMENTATION_PHASE_1..4.md`). Не реализовывать фичи
поздней фазы раньше времени — заглушка: `throw new \RuntimeException('Not implemented in Phase 1.')`.
- Phase 1 (MVP): core, tensor, onnx-backend, ai · Phase 2: llama-backend, tokenizer
- Phase 3: embedding, vector, model-hub, pipeline, cpu-backend · Phase 4: dataframe, laravel, symfony

Порядок неизменен: **сперва ядро платформы** (фазы 1–3) должно быть готово и стабильно.
Пакеты `dataframe`, `laravel`, `symfony` создаются **последними** (Фаза 4) и только после этого —
не начинать их раньше готовности ядра.

## Рабочий цикл (создание файла)

1. Путь и зависимости — `docs/FILE_TREE.md`. 2. Контракт — `docs/INTERFACE_CONTRACTS.md`.
3. Шаг реализации — `docs/IMPLEMENTATION_PHASE_X.md`. 4. Тест → код → `composer check`.

## Коммиты

`type(scope): description` — types: feat/fix/docs/style/refactor/perf/test/chore/ci/build/revert;
scope — имя пакета (`core`, `onnx-backend`, `ai`, …).
Пример: `feat(core): add Shape value object with broadcasting validation`.

## Карта документов

| Нужно | Файл |
|---|---|
| Правила и конвенции для ИИ | `docs/SKILL.md` |
| Почему приняты решения | `docs/RESEARCH_ARCHITECTURE.md` |
| Архитектура (библия) | `docs/TECHNICAL_SPECIFICATION.md` |
| Сигнатуры интерфейсов | `docs/INTERFACE_CONTRACTS.md` |
| Где что лежит + порядок сборки | `docs/FILE_TREE.md` |
| Пошаговая реализация | `docs/IMPLEMENTATION_PHASE_1..4.md` |
| CI/CD, composer, публикация | `docs/REPOSITORY_INFRASTRUCTURE.md` |
| Внешние источники и стек | `docs/SOURCES.md` |
| Журнал разработки | `docs/BUILD_LOG.md` |
| Навигатор по всему | `docs/README.md` |
