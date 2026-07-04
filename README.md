# FerryAI — Справочник и Оркестратор

> **Назначение:** единая точка входа в проектную документацию FerryAI.  
> **Правило:** ни один файл не читается без понимания, зачем он нужен. Этот документ — карта и навигатор.

---

## ПРОЕКТ

| | |
|---|---|
| **Пакет** | `ferry-ai/php-inference` |
| **Namespace** | `FerryAI\` |
| **Репозиторий** | https://github.com/MADEVAL/FerryAI |
| **Автор** | Yevhen Leonidov |
| **Лицензия** | MIT |
| **Установка** | `composer require ferry-ai/php-inference` |

---

## БЫСТРЫЙ СТАРТ (ЧТО ЧИТАТЬ ПЕРВЫМ)

```
1. SKILL.md                          ← контекст для ИИ: правила, конвенции, архитектурные границы
2. RESEARCH_ARCHITECTURE.md          ← почему именно так: анализ экосистемы, верификация решений
3. TECHNICAL_SPECIFICATION.md        ← что строим: 35 разделов архитектуры, библия
4. INTERFACE_CONTRACTS.md            ← сигнатуры: каждый метод, каждый параметр, каждое исключение
5. FILE_TREE.md                      ← где что лежит: 137 файлов в 14 пакетах + порядок сборки
6. REPOSITORY_INFRASTRUCTURE.md      ← как настроен репозиторий: composer, CI/CD, тесты, публикация
7. IMPLEMENTATION_PHASE_1.md         ← Фаза 1: 52 шага, 53 файла (ONNX-инференс)
8. IMPLEMENTATION_PHASE_2.md         ← Фаза 2: 23 шага (LLM / llama.cpp)
9. IMPLEMENTATION_PHASE_3.md         ← Фаза 3: 42 шага (Экосистема)
10. IMPLEMENTATION_PHASE_4.md        ← Фаза 4: 14 шагов (Production)
```

---

## НАВИГАТОР: КАКОЙ ДОКУМЕНТ ОТКРЫТЬ

| Ты хочешь... | Открой |
|---|---|
| Понять, ЧТО это за проект | `RESEARCH_ARCHITECTURE.md` → `TECHNICAL_SPECIFICATION.md` §1 |
| Понять, ПОЧЕМУ приняты решения | `RESEARCH_ARCHITECTURE.md` §2, §5, §10 |
| Найти точную сигнатуру метода | `INTERFACE_CONTRACTS.md` |
| Узнать, в каком файле лежит класс | `FILE_TREE.md` |
| Узнать, от чего зависит файл | `FILE_TREE.md` — колонка «Зависит от» |
| Понять порядок создания файлов | `FILE_TREE.md` §«Порядок создания (Фаза 1)» → `IMPLEMENTATION_PHASE_1.md` |
| Узнать, что реализовывать прямо сейчас | `IMPLEMENTATION_PHASE_1.md` (MVP) |
| Написать код для конкретного шага | `IMPLEMENTATION_PHASE_X.md` → нужный шаг |
| Написать тест | `SKILL.md` §Testing Doctrine → `INTERFACE_CONTRACTS.md` (сигнатура) |
| Настроить CI/CD | `REPOSITORY_INFRASTRUCTURE.md` §7 |
| Опубликовать пакет | `REPOSITORY_INFRASTRUCTURE.md` §9 |
| Понять архитектурный слой | `TECHNICAL_SPECIFICATION.md` §4–§8 |
| Понять пакетную структуру | `TECHNICAL_SPECIFICATION.md` §9 → `FILE_TREE.md` |
| Найти бэкенд-интерфейс | `INTERFACE_CONTRACTS.md` §1.1 |
| Найти контракт токенизатора | `INTERFACE_CONTRACTS.md` §1.4 |
| Найти все enum'ы | `INTERFACE_CONTRACTS.md` §2 |
| Найти все исключения | `INTERFACE_CONTRACTS.md` §4 |
| Понять, как мокать FFI | `SKILL.md` §FFI Mocking Strategy |
| Понять, что НЕ делаем | `SKILL.md` §What We NEVER Do |
| Настроить dev-окружение на Windows | `REPOSITORY_INFRASTRUCTURE.md` §8.1 |
| Проверить окружение | `REPOSITORY_INFRASTRUCTURE.md` §13 (чеклист) |
| Принять архитектурное решение | `RESEARCH_ARCHITECTURE.md` — вся логика решений |

---

## АРХИТЕКТУРА ДОКУМЕНТОВ: КАК ОНИ СВЯЗАНЫ

```
RESEARCH_ARCHITECTURE.md
    │  (анализ экосистемы, gap-анализ, верификация)
    │
    └──► TECHNICAL_SPECIFICATION.md
            │  (архитектура: слои, пакеты, компоненты)
            │
            ├──► INTERFACE_CONTRACTS.md
            │       (детальные сигнатуры всех интерфейсов)
            │
            ├──► FILE_TREE.md
            │       (пофайловая карта + порядок сборки)
            │
            └──► IMPLEMENTATION_PHASE_1.md  ──┐
                 IMPLEMENTATION_PHASE_2.md  ──┤
                 IMPLEMENTATION_PHASE_3.md  ──┤── пошаговая реализация
                 IMPLEMENTATION_PHASE_4.md  ──┘

REPOSITORY_INFRASTRUCTURE.md
    │  (composer, CI/CD, тесты, публикация)
    │
    └── независим от остальных, описывает инструментарий

SKILL.md
    │  (правила и конвенции для ИИ-агента)
    │
    └── ссылается на все документы, определяет КАК работать
```

---

## СТАТУС ФАЙЛОВ

| Файл | Назначение | Статус |
|---|---|---|
| `SKILL.md` | Правила и конвенции для ИИ | ✅ Готов |
| `RESEARCH_ARCHITECTURE.md` | Исследование: анализ экосистемы, верификация | ✅ Готов |
| `TECHNICAL_SPECIFICATION.md` | Техническое задание — библия архитектуры | ✅ Готов |
| `INTERFACE_CONTRACTS.md` | Сигнатуры интерфейсов, enum'ов, value objects | ✅ Готов |
| `FILE_TREE.md` | Дерево файлов + порядок сборки | ✅ Готов |
| `REPOSITORY_INFRASTRUCTURE.md` | Инфраструктура: composer, CI/CD, тесты, публикация | ✅ Готов |
| `IMPLEMENTATION_PHASE_1.md` | Фаза 1 MVP: 52 шага, 53 файла | ✅ Готов |
| `IMPLEMENTATION_PHASE_2.md` | Фаза 2 LLM: 23 шага | ✅ Готов |
| `IMPLEMENTATION_PHASE_3.md` | Фаза 3 Экосистема: 42 шага | ✅ Готов |
| `IMPLEMENTATION_PHASE_4.md` | Фаза 4 Production: 14 шагов | ✅ Готов |

---

## ОРКЕСТРАТОР: ПОРЯДОК ДЕЙСТВИЙ ДЛЯ ИИ

### Режим 1: Первое знакомство с проектом

```
1. Открой SKILL.md              → запомни правила
2. Открой RESEARCH_ARCHITECTURE.md → пойми контекст
3. Открой TECHNICAL_SPECIFICATION.md §1–§4 → пойми что строим
4. Открой FILE_TREE.md           → запомни где что лежит
5. Ты готов к работе.
```

### Режим 2: Реализация (Фаза 1)

```
1. Открой IMPLEMENTATION_PHASE_1.md
2. Найди первый невыполненный шаг
3. Открой FILE_TREE.md → найди путь к файлу
4. Открой INTERFACE_CONTRACTS.md → если это интерфейс/enum/value-object/exception
5. Напиши тест (см. SKILL.md §Testing Doctrine)
6. Напиши код
7. Запусти: composer cs-fix && composer stan && composer test
8. Повтори со следующего шага
```

### Режим 3: Реализация (Фаза 2/3/4)

```
1. Открой IMPLEMENTATION_PHASE_X.md
2. Проверь, что все предыдущие фазы завершены
3. Дальше — как в Режиме 2
```

### Режим 4: Исправление бага

```
1. Найди проблемный класс в FILE_TREE.md
2. Открой INTERFACE_CONTRACTS.md → проверь контракт
3. Открой TECHNICAL_SPECIFICATION.md → проверь архитектурный контекст
4. Напиши тест, воспроизводящий баг
5. Исправь
6. composer check
```

### Режим 5: Проектирование нового компонента

```
1. Открой TECHNICAL_SPECIFICATION.md → проверь, не описан ли уже
2. Открой RESEARCH_ARCHITECTURE.md → проверь, не отвергнут ли
3. Предложи решение → обсуди → задокументируй в спецификации
4. Обнови INTERFACE_CONTRACTS.md (новые сигнатуры)
5. Обнови FILE_TREE.md (новые файлы)
6. Создай шаги в IMPLEMENTATION_PHASE_X.md
```

### Режим 6: Настройка CI/CD / репозитория

```
1. Открой REPOSITORY_INFRASTRUCTURE.md
2. Следуй разделу, соответствующему задаче:
   - composer-настройка → §1
   - тестирование → §3
   - статанализ → §4
   - качество кода → §5
   - git → §6
   - CI/CD → §7
   - публикация → §9
```

---

## КЛЮЧЕВЫЕ КОНЦЕПТЫ (НАПОМИНАНИЕ)

- **Inference-only:** не обучаем, autograd не нужен
- **Три бэкенда:** ONNX Runtime (основной), llama.cpp (LLM), CPU Native (fallback)
- **FFI — единственный мост** к нативному коду
- **12 пакетов** в монорепо, `ferry-ai/php-inference` — мета-пакет (14 с laravel/symfony)
- **PHP 8.5** — минимальная версия
- **Zero-copy** — не копируем данные между PHP и нативным кодом без нужды
- **Иммутабельность** — все value objects readonly
- **TDD** — тест → код → рефакторинг

---

> **Этот документ — входная точка. Не начинай работу, не прочитав `SKILL.md`. Не пиши код, не сверившись с `INTERFACE_CONTRACTS.md`. Не создавай файл, не проверив путь в `FILE_TREE.md`.**
