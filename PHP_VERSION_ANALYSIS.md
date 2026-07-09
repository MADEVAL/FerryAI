# Анализ зависимости FerryAI от версии PHP

## 1. Текущее состояние

Все **15 composer.json** декларируют `"php": ">=8.3"`, CI тестирует **PHP 8.3 и 8.5**.
`\Pdo\Sqlite` (PHP 8.4+) больше не hard-block — код использует `\PDO` + `getAttribute()`
проверку драйвера + `method_exists($pdo, 'loadExtension')` для определения доступности
`loadExtension` на рантайме.

## 2. Реальные фичи по версиям (что используется в коде)

| Версия | Фича | Распространённость | Критичность |
|--------|------|-------------------|-------------|
| **8.4** | `\Pdo\Sqlite::loadExtension` | 1 файл (`vector/src/SQLiteStore.php` — `\Pdo\Sqlite::connect()` при наличии класса) | Опционально: vec0 ANN только на 8.4+, иначе BruteForceIndex |
| **8.3** | `#[\Override]` | **59+** использований во всех пакетах | Массово, но чисто косметический |
| **8.3** | Typed class constants (`const int`) | ~15 мест | Легко заменить |
| **8.2** | `readonly class` | 11 классов (ValueObjects + Column + GbnfGrammar) | Все ValueObjects — переписать на `readonly` поля |
| **8.1** | Enums (`BackendType`, `Device`, `DType`, ...) | 8 enum-ов | **Фундаментально**, без них рухнет всё |
| **8.1** | `readonly` свойства | 94+ мест | **Везде**, основа иммутабельности |
| **8.1** | First-class callables (`$this->method(...)`) | ~10 мест | Точечно |
| **8.0** | `match` | 37+ мест | Точечно, можно заменить на `switch` |

## 3. До какой версии можно опустить

| Целевая версия | Объём изменений | Реалистичность |
|----------------|-----------------|----------------|
| **8.4** | Только composer.json (15 файлов) | **Тривиально** |
| **8.3** | Уже сделано — `\Pdo\Sqlite` заменён на `\PDO` + `getAttribute()` + `method_exists()` | **Сделано** |
| **8.2** | Всё из 8.3 + убрать `#[\Override]` (59+ мест, механическая правка) + typed constants → docblocks | **Средне** (~2-3 часа) |
| **8.1** | Всё выше + `readonly class` → `readonly` поля (11 классов, структурная переделка) | **Тяжело** (~1 день) |
| **8.0** | Всё выше + enums → константы-классы + `match` → `switch` + first-class callables → closures | **Очень тяжело** (~3-5 дней) |

## 4. Как сделан переход на 8.3

1. **`SqliteVecExtension.php`**: `instanceof \Pdo\Sqlite` → `$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite'`, `class_exists` → `method_exists($pdo, 'loadExtension')`.
2. **`SQLiteStore.php`**: сохранён `class_exists(\Pdo\Sqlite::class)` guard для `\Pdo\Sqlite::connect()` — на 8.4+ даёт класс с `loadExtension`, на 8.3 fallback `new \PDO()`.
3. **15 composer.json**: `>=8.5` → `>=8.3`.
4. **CI**: матрица `['8.3', '8.5']`.
5. **README/docs**: все упоминания `8.5+` → `8.3+`.

## 5. Итог

**Нижняя граница: PHP 8.3.** На 8.3 всё работает кроме vec0 ANN (sqlite-vec), которое
требует `\Pdo\Sqlite::loadExtension` (8.4+) — прозрачный fallback на BruteForceIndex.
Рекомендуемая версия для production: **PHP 8.4+** (полная функциональность).
