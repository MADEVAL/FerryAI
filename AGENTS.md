# AGENTS.md — FerryAI (ferry-ai/php-inference)

Унифицированный **inference**-рантайм для PHP 8.5+: единый API поверх нативных движков
(ONNX Runtime, llama.cpp, RubixML/Tensor). Только вывод, **не обучение**.

- **«Прямой» инференс** = прямо в PHP-приложении, без Python-микросервисов. Механизм доступа
  к нативным движкам — **PHP FFI** (единственный мост; см. `SKILL.md` §Architecture Rules,
  `RESEARCH_ARCHITECTURE.md` §2.2/§8). Компилируемое нативное расширение — лишь возможная
  будущая фаза, по докам не приоритетна.
- Namespace: `FerryAI\` · Vendor: `ferry-ai/*` · Монорепо: `packages/{name}/`
- Полные правила и контекст — в `SKILL.md`. Архитектура — `TECHNICAL_SPECIFICATION.md`.

## Стадия проекта (сейчас — проектирование)

В репозитории пока только документы и `.opencode/` — нет `composer.json`, `packages/`, `vendor/`.
- Пока нет `composer.json`/`packages/`: задача = работа с документами (см. `README.md`-оркестратор).
  Команды `composer …` заработают после инициализации репо по `REPOSITORY_INFRASTRUCTURE.md §0`.
- Уже созданы пустые папки (`.gitkeep`): `tests/Integration/`, `tests/Verification/`, `docs/specs/`.

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

Три слоя (см. `SKILL.md` §Testing Doctrine):
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
4. **Контракты — истина** — сигнатуры в `INTERFACE_CONTRACTS.md`; реализация не отклоняется.
5. **Исключения** — все наследуют `FerryAIException`, у каждого `errorCode()` вида `FERRY_AI_*`.
6. **Zero-copy** — не копируем данные PHP↔native без нужды; `toArray()` помечать как дорогой.
7. **Без жёсткой привязки** к Laravel/Symfony; модели в репозиторий не коммитим.

## Phase Awareness

Каждая задача принадлежит фазе (`IMPLEMENTATION_PHASE_1..4.md`). Не реализовывать фичи
поздней фазы раньше времени — заглушка: `throw new \RuntimeException('Not implemented in Phase 1.')`.
- Phase 1 (MVP): core, tensor, onnx-backend, ai · Phase 2: llama-backend, tokenizer
- Phase 3: embedding, vector, model-hub, pipeline, cpu-backend · Phase 4: production, laravel, symfony

## Рабочий цикл (создание файла)

1. Путь и зависимости — `FILE_TREE.md`. 2. Контракт — `INTERFACE_CONTRACTS.md`.
3. Шаг реализации — `IMPLEMENTATION_PHASE_X.md`. 4. Тест → код → `composer check`.

## Коммиты

`type(scope): description` — types: feat/fix/docs/style/refactor/perf/test/chore/ci/build/revert;
scope — имя пакета (`core`, `onnx-backend`, `ai`, …).
Пример: `feat(core): add Shape value object with broadcasting validation`.

## Карта документов

| Нужно | Файл |
|---|---|
| Правила и конвенции для ИИ | `SKILL.md` |
| Почему приняты решения | `RESEARCH_ARCHITECTURE.md` |
| Архитектура (библия) | `TECHNICAL_SPECIFICATION.md` |
| Сигнатуры интерфейсов | `INTERFACE_CONTRACTS.md` |
| Где что лежит + порядок сборки | `FILE_TREE.md` |
| Пошаговая реализация | `IMPLEMENTATION_PHASE_1..4.md` |
| CI/CD, composer, публикация | `REPOSITORY_INFRASTRUCTURE.md` |
| Навигатор по всему | `README.md` |
