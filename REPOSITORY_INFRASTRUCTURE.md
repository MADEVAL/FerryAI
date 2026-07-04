# PHP AI Platform — Инфраструктура репозитория

> Версия: 1.0  
> Назначение: полное описание инфраструктуры монорепо — composer, тестирование, CI/CD, публикация, инструменты  
> Целевые ОС: Windows (основная разработка), Linux, macOS  
> Принцип: единая конфигурация на все платформы, ни один разработчик не должен гадать, как запустить

---

## 0. СТРУКТУРА КОРНЯ РЕПОЗИТОРИЯ

```
php-inference/
├── .github/
│   ├── workflows/
│   │   ├── ci.yml                    # Основной CI: тесты + статанализ
│   │   ├── release.yml               # Сборка бинарников + Packagist
│   │   └── docs.yml                  # Генерация и публикация документации
│   └── dependabot.yml               # Автообновление зависимостей
├── packages/
│   ├── core/
│   ├── tensor/
│   ├── onnx-backend/
│   ├── llama-backend/
│   ├── cpu-backend/
│   ├── tokenizer/
│   ├── embedding/
│   ├── pipeline/
│   ├── model-hub/
│   ├── vector/
│   ├── dataframe/
│   ├── ai/
│   ├── laravel/
│   └── symfony/
├── benchmarks/
│   ├── embed.php
│   ├── chat.php
│   └── vector.php
├── bin/
│   └── ferry-ai                      # CLI-инструмент
├── docs/
│   ├── index.md
│   ├── getting-started.md
│   ├── configuration.md
│   ├── backends/
│   │   ├── onnx.md
│   │   ├── llama.md
│   │   └── cpu.md
│   ├── embedding.md
│   ├── vector-store.md
│   ├── pipeline.md
│   ├── model-hub.md
│   ├── tokenizer.md
│   ├── streaming.md
│   ├── security.md
│   ├── laravel.md
│   ├── symfony.md
│   ├── deployment.md
│   ├── troubleshooting.md
│   ├── api-reference.md
│   └── specs/                         # Валидированные дизайн-спеки (YYYY-MM-DD-<topic>-design.md)
├── tests/
│   ├── Integration/                   # Интеграционные тесты (cross-package, @group integration)
│   ├── Verification/                  # Runtime-тесты проверки багов/аудита (@coversNothing)
│   └── .env                          # Переменные для тестов
├── .gitattributes
├── .gitignore
├── .editorconfig
├── .php-cs-fixer.dist.php
├── phpstan.neon
├── psalm.xml
├── phpunit.xml.dist
├── infection.json5                   # Мутационное тестирование
├── composer.json                     # Root: мета-пакет + dev-зависимости
├── monorepo-builder.php              # Инструмент монорепо (symplify)
├── CHANGELOG.md
├── CODE_OF_CONDUCT.md
├── CONTRIBUTING.md
├── LICENSE.md
├── README.md
└── SECURITY.md
```

---

## 1. COMPOSER — УПРАВЛЕНИЕ ПАКЕТАМИ

### 1.1. Root `composer.json`

```json
{
    "name": "ferry-ai/php-inference",
    "description": "PHP AI Platform — unified inference API for PHP applications",
    "type": "project",
    "keywords": ["ai", "ml", "onnx", "llama", "embedding", "llm", "vector-store", "rag"],
    "homepage": "https://github.com/MADEVAL/FerryAI",
    "license": "MIT",
    "authors": [
        {
            "name": "Yevhen Leonidov",
            "homepage": "https://github.com/MADEVAL"
        }
    ],
    "require": {
        "php": ">=8.5",
        "ext-ffi": "*",
        "ext-json": "*",
        "ext-hash": "*",
        "ext-fileinfo": "*",
        "ferry-ai/inference-core": "^1.0",
        "ferry-ai/inference-tensor": "^1.0",
        "ferry-ai/inference-onnx-backend": "^1.0",
        "ferry-ai/inference-llama-backend": "^1.0",
        "ferry-ai/inference-cpu-backend": "^1.0",
        "ferry-ai/inference-tokenizer": "^1.0",
        "ferry-ai/inference-embedding": "^1.0",
        "ferry-ai/inference-pipeline": "^1.0",
        "ferry-ai/inference-model-hub": "^1.0",
        "ferry-ai/inference-vector": "^1.0",
        "ferry-ai/inference-ai": "^1.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^2.0",
        "phpstan/phpstan-strict-rules": "^2.0",
        "phpstan/phpstan-deprecation-rules": "^2.0",
        "vimeo/psalm": "^6.0",
        "squizlabs/php_codesniffer": "^3.10",
        "php-cs-fixer/shim": "^3.0",
        "infection/infection": "^0.29",
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-parallel": "^3.0",
        "symplify/monorepo-builder": "^11.0",
        "roave/security-advisories": "dev-latest",
        "ergebnis/composer-normalize": "^2.0",
        "captainhook/captainhook": "^5.0"
    },
    "replace": {
        "ferry-ai/inference-core": "self.version",
        "ferry-ai/inference-tensor": "self.version",
        "ferry-ai/inference-onnx-backend": "self.version",
        "ferry-ai/inference-llama-backend": "self.version",
        "ferry-ai/inference-cpu-backend": "self.version",
        "ferry-ai/inference-tokenizer": "self.version",
        "ferry-ai/inference-embedding": "self.version",
        "ferry-ai/inference-pipeline": "self.version",
        "ferry-ai/inference-model-hub": "self.version",
        "ferry-ai/inference-vector": "self.version",
        "ferry-ai/inference-dataframe": "self.version",
        "ferry-ai/inference-ai": "self.version",
        "ferry-ai/inference-laravel": "self.version",
        "ferry-ai/inference-symfony": "self.version"
    },
    "autoload": {
        "psr-4": {
            "FerryAI\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "FerryAI\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": [
            "@putenv FERRY_AI_TESTING=1",
            "@php vendor/bin/phpunit"
        ],
        "test-parallel": [
            "@putenv FERRY_AI_TESTING=1",
            "@php vendor/bin/pest --parallel"
        ],
        "test-integration": [
            "@putenv FERRY_AI_TESTING=1",
            "@putenv FERRY_AI_INTEGRATION=1",
            "@php vendor/bin/phpunit --testsuite integration"
        ],
        "verify": [
            "@putenv FERRY_AI_TESTING=1",
            "@php vendor/bin/phpunit --testsuite verification"
        ],
        "stan": "phpstan analyse --memory-limit=512M",
        "psalm": "psalm --no-cache",
        "analyse": [
            "@stan",
            "@psalm"
        ],
        "cs-check": "@php vendor/bin/php-cs-fixer check --diff",
        "cs-fix": "@php vendor/bin/php-cs-fixer fix",
        "lint": [
            "@cs-check",
            "@analyse"
        ],
        "mutation": "infection --threads=max",
        "benchmark-embed": "@php benchmarks/embed.php",
        "benchmark-chat": "@php benchmarks/chat.php",
        "benchmark-vector": "@php benchmarks/vector.php",
        "benchmark": [
            "@benchmark-embed",
            "@benchmark-chat",
            "@benchmark-vector"
        ],
        "check": [
            "@lint",
            "@test"
        ],
        "coverage": [
            "@putenv XDEBUG_MODE=coverage",
            "@php vendor/bin/phpunit --coverage-html build/coverage --coverage-text"
        ],
        "normalize": "@php vendor/bin/composer-normalize",
        "monorepo-release": "@php vendor/bin/monorepo-builder release"
    },
    "scripts-descriptions": {
        "test": "Run all unit tests",
        "test-parallel": "Run tests in parallel (Pest)",
        "test-integration": "Run integration tests (requires ONNX Runtime / llama.cpp)",
        "verify": "Run runtime bug/audit verification tests (tests/Verification, @coversNothing)",
        "stan": "PHPStan static analysis (level 8)",
        "psalm": "Psalm static analysis (level 3)",
        "analyse": "Run both PHPStan and Psalm",
        "cs-check": "Check code style (PER-CS 2.0)",
        "cs-fix": "Fix code style automatically",
        "lint": "Run all linters",
        "mutation": "Mutation testing (Infection)",
        "benchmark": "Run all benchmarks",
        "check": "Lint + test (pre-commit)",
        "coverage": "Generate HTML coverage report"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "infection/extension-installer": true,
            "ergebnis/composer-normalize": true,
            "pestphp/pest-plugin": true,
            "captainhook/plugin-composer": true,
            "phpstan/extension-installer": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

### 1.2. Пакетный `composer.json` (шаблон для всех подпакетов)

```json
{
    "name": "ferry-ai/inference-<package>",
    "description": "<Package description>",
    "type": "library",
    "license": "MIT",
    "homepage": "https://github.com/MADEVAL/FerryAI",
    "authors": [
        {
            "name": "Yevhen Leonidov",
            "homepage": "https://github.com/MADEVAL"
        }
    ],
    "require": {
        "php": ">=8.5"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^2.0",
        "vimeo/psalm": "^6.0"
    },
    "autoload": {
        "psr-4": {
            "FerryAI\\<Namespace>\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "FerryAI\\<Namespace>\\Tests\\": "tests/"
        }
    },
    "config": {
        "sort-packages": true
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

**Особенности для каждого пакета:**

| Пакет | Дополнительные `require` | `provide` / `suggest` |
|---|---|---|
| `core` | (нет) | — |
| `tensor` | `ferry-ai/inference-core: ^1.0` | `ext-random: *` (для random-заполнения) |
| `onnx-backend` | `ferry-ai/inference-core: ^1.0`, `phpmlkit/onnxruntime: ^1.0` | `ankane/onnxruntime-php: ^1.0` (резервный) |
| `llama-backend` | `ferry-ai/inference-core: ^1.0` | — |
| `cpu-backend` | `ferry-ai/inference-core: ^1.0` | `rubix/ml: ^2.0`, `rubix/tensor: ^2.0` |
| `tokenizer` | `ferry-ai/inference-core: ^1.0` | — |
| `embedding` | `ferry-ai/inference-core: ^1.0`, `ferry-ai/inference-onnx-backend: ^1.0`, `ferry-ai/inference-tokenizer: ^1.0` | — |
| `pipeline` | `ferry-ai/inference-core: ^1.0` | — |
| `model-hub` | `ferry-ai/inference-core: ^1.0`, `codewithkyrian/huggingface-php: ^1.0` | `ext-zip: *`, `ext-sodium: *` |
| `vector` | `ferry-ai/inference-core: ^1.0`, `ferry-ai/inference-embedding: ^1.0` | `ext-pdo_sqlite: *` или `ext-sqlite3: *` |
| `dataframe` | `ferry-ai/inference-core: ^1.0`, `ferry-ai/inference-tensor: ^1.0` | — |
| `ai` | `ferry-ai/inference-core: ^1.0`, `ferry-ai/inference-onnx-backend: ^1.0`, `ferry-ai/inference-llama-backend: ^1.0`, `ferry-ai/inference-cpu-backend: ^1.0`, `ferry-ai/inference-tokenizer: ^1.0`, `ferry-ai/inference-embedding: ^1.0`, `ferry-ai/inference-pipeline: ^1.0`, `ferry-ai/inference-model-hub: ^1.0`, `ferry-ai/inference-vector: ^1.0` | `psr/http-message: ^2.0` (для StreamResponse) |
| `laravel` | `ferry-ai/inference-ai: ^1.0`, `illuminate/support: ^11.0` | — |
| `symfony` | `ferry-ai/inference-ai: ^1.0`, `symfony/http-kernel: ^7.0`, `symfony/config: ^7.0`, `symfony/dependency-injection: ^7.0` | — |

---

## 2. УПРАВЛЕНИЕ МОНОРЕПО

### 2.1. Инструмент: `symplify/monorepo-builder`

**Файл:** `monorepo-builder.php`

```php
<?php

declare(strict_types=1);

use Symplify\MonorepoBuilder\Config\MBConfig;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\PushTagReleaseWorker;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\SetCurrentMutualDependenciesReleaseWorker;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\SetNextMutualDependenciesReleaseWorker;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\UpdateBranchAliasReleaseWorker;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\UpdateReplaceReleaseWorker;

return static function (MBConfig $config): void {
    $config->packageDirectories([
        __DIR__ . '/packages',
    ]);

    $config->packageAliasFormat('<major>.<minor>.x-dev');

    $config->workers([
        SetCurrentMutualDependenciesReleaseWorker::class,
        UpdateReplaceReleaseWorker::class,
    ]);
};
```

**Команды:**
```bash
# Валидация структуры монорепо
vendor/bin/monorepo-builder validate

# Обновление взаимных зависимостей пакетов (при разработке)
vendor/bin/monorepo-builder merge

# Просмотр объединённого composer.json (для отладки)
vendor/bin/monorepo-builder merge --dry-run

# Релиз
vendor/bin/monorepo-builder release v1.0.0
```

### 2.2. Релизный процесс одного пакета

Каждый подпакет публикуется на Packagist **отдельно** через GitHub-репозиторий-поддерево (subtree split) или отдельные репозитории-зеркала.

**Стратегия split (варианты):**

**Вариант A: GitHub Actions + subtree split (рекомендуется)**
- Каждый подпакет автоматически зеркалируется в свой репозиторий
- Packagist подписан на каждый репозиторий отдельно

**Вариант B: Monorepo Builder раздельный релиз**
- `monorepo-builder release` может публиковать каждый пакет отдельно

**Финальное решение:** Вариант A. Надёжнее для Packagist.

---

## 3. ТЕСТИРОВАНИЕ

### 3.1. PHPUnit (основной фреймворк)

**Файл:** `phpunit.xml.dist`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.0/phpunit.xsd"
    bootstrap="vendor/autoload.php"
    colors="true"
    cacheDirectory="build/phpunit"
    beStrictAboutOutputDuringTests="true"
    beStrictAboutChangesToGlobalState="true"
    failOnRisky="true"
    failOnWarning="true"
    timeoutForSmallTests="2"
    timeoutForMediumTests="10"
    timeoutForLargeTests="60"
>
    <testsuites>
        <testsuite name="unit">
            <directory>packages/*/tests/Unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="verification">
            <directory>tests/Verification</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>packages/*/src</directory>
        </include>
    </source>

    <php>
        <env name="FERRY_AI_TESTING" value="1" force="true"/>
        <!-- Не требуем нативные библиотеки для unit-тестов -->
        <env name="FERRY_AI_SKIP_NATIVE" value="1" force="false"/>
    </php>
</phpunit>
```

**Расположение тестов для каждого пакета:**

```
packages/core/
├── src/
└── tests/
    ├── Unit/
    │   ├── Enums/
    │   │   ├── DeviceTest.php
    │   │   ├── DTypeTest.php
    │   │   └── BackendTypeTest.php
    │   ├── ValueObjects/
    │   │   ├── ShapeTest.php
    │   │   ├── ChatMessageTest.php
    │   │   └── ...
    │   ├── Exception/
    │   │   └── ...
    │   └── Contracts/             # Тесты контрактов (abstract test cases)
    │       ├── BackendContractTest.php
    │       ├── ModelContractTest.php
    │       └── TensorContractTest.php
    └── Integration/               # Если нужны (обычно в корневом tests/)
```

### 3.2. Pest PHP (альтернативный/дополнительный фреймворк)

Pest установлен как dev-зависимость в корневом composer.json. Используется опционально — разработчик выбирает PHPUnit или Pest.

```bash
# Запуск тестов через Pest (все)
vendor/bin/pest

# Параллельный запуск
vendor/bin/pest --parallel

# Конкретный файл
vendor/bin/pest packages/core/tests/Unit/Enums/DeviceTest.php

# С покрытием
vendor/bin/pest --coverage
```

### 3.3. Mock-объекты и FFI

**Проблема:** FFI нельзя замокать стандартными средствами, потому что это расширение C.  
**Решение:** Абстракция через интерфейсы.

Для каждого нативного вызова создаётся тонкая обёртка-интерфейс:

```php
// Пример: интерфейс для LlamaCpp
interface LlamaCppInterface
{
    public function modelLoadFromFile(string $path): object;
    public function contextInitFromModel(object $model): object;
    // ...
}

// Реальная реализация
class NativeLlamaCpp implements LlamaCppInterface { /* FFI-вызовы */ }

// Mock-реализация для тестов
class MockLlamaCpp implements LlamaCppInterface { /* возвращает предсказуемые данные */ }
```

Внедрение через конструктор (Dependency Injection):
```php
class LlamaBackend
{
    public function __construct(
        private LlamaCppInterface $llamaCpp = new NativeLlamaCpp(),
    ) {}
}
```

Тест:
```php
$mock = new MockLlamaCpp();
$backend = new LlamaBackend($mock);
// тестируем без реального llama.cpp
```

### 3.4. Абстрактные тестовые классы для контрактов

Для каждого интерфейса в `core/Contracts` создаётся абстрактный тестовый класс, который проверяет контракт. Каждая конкретная реализация наследует этот тест:

```php
// packages/core/tests/Unit/Contracts/BackendContractTest.php
abstract class BackendContractTest extends TestCase
{
    abstract protected function createBackend(): Backend;

    /** @test */
    public function it_implements_available_devices(): void
    {
        $devices = $this->createBackend()->availableDevices();
        $this->assertNotEmpty($devices);
        $this->assertContainsOnlyInstancesOf(Device::class, $devices);
    }

    /** @test */
    public function it_implements_is_available(): void
    {
        $this->assertIsBool($this->createBackend()->isAvailable());
    }

    /** @test */
    public function it_throws_model_not_found_for_missing_file(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->createBackend()->load('/nonexistent/model.onnx');
    }
}

// packages/onnx-backend/tests/Unit/OnnxBackendTest.php
class OnnxBackendTest extends BackendContractTest
{
    protected function createBackend(): Backend
    {
        return new OnnxBackend();
    }
}
```

### 3.5. Интеграционные тесты

**Файл:** `tests/Integration/.env`

```env
# Пути к тестовым моделям (скачиваются CI-скриптом)
FERRY_AI_TEST_ONNX_MODEL=./tests/fixtures/test_model.onnx
FERRY_AI_TEST_GGUF_MODEL=./tests/fixtures/test_model.gguf
FERRY_AI_TEST_TOKENIZER=./tests/fixtures/tokenizer.json

# Пропускать интеграционные тесты, если модели отсутствуют
FERRY_AI_SKIP_MISSING_MODELS=1
```

**Структура интеграционных тестов:**

```
tests/
├── Integration/
│   ├── Onnx/
│   │   ├── OnnxInferenceTest.php       # Реальный ONNX-инференс
│   │   ├── OnnxProvidersTest.php       # Провайдеры (CPU/CUDA)
│   │   └── OnnxTensorTest.php          # Тензорные операции
│   ├── Llama/
│   │   ├── LlamaChatTest.php           # Чат с LLM
│   │   ├── LlamaStreamingTest.php      # Стриминг
│   │   └── LlamaSamplingTest.php       # Сэмплеры
│   ├── Embedding/
│   │   ├── EmbeddingTest.php           # Эмбеддинги
│   │   └── BatchEmbeddingTest.php      # Пакетные
│   ├── Vector/
│   │   ├── VectorStoreTest.php         # CRUD + поиск
│   │   └── FilterTest.php              # Фильтрация метаданных
│   ├── Pipeline/
│   │   └── PipelineIntegrationTest.php # Полный пайплайн
│   └── RAG/
│       └── RagFlowTest.php             # End-to-end: chunk → embed → store → search
├── fixtures/
│   └── .gitkeep                        # Тестовые модели (в .gitignore, скачиваются CI)
└── bootstrap.php                       # Общий bootstrap для интеграционных тестов
```

---

## 4. СТАТИЧЕСКИЙ АНАЛИЗ

### 4.1. PHPStan

**Файл:** `phpstan.neon`

```neon
parameters:
    level: 8
    paths:
        - packages/*/src
    excludePaths:
        - packages/*/tests/*
        - packages/*/vendor/*
    checkGenericClassInNonGenericObjectType: true
    checkMissingIterableValueType: true
    checkMissingCallableSignature: true
    treatPhpDocTypesAsCertain: false
    reportUnmatchedIgnoredErrors: false
    parallel:
        maximumNumberOfProcesses: 4
    ignoreErrors:
        # FFI-вызовы не могут быть полностью типизированы
        - '#Cannot access property \$cdata on mixed#'
        - '#Call to an undefined method .*\\FFI::new\(\)#'
        - '#Parameter \#1 \$ptr of function .* expects .*FFI\\CData#'
    typeAliases:
        TokenIds: list<int>
        Vector: list<float>
```

**Запуск:**
```bash
vendor/bin/phpstan analyse --memory-limit=512M
```

### 4.2. Psalm

**Файл:** `psalm.xml`

```xml
<?xml version="1.0"?>
<psalm
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns="https://getpsalm.org/schema/config"
    xsi:schemaLocation="https://getpsalm.org/schema/config vendor/vimeo/psalm/config.xsd"
    level="3"
    findUnusedBaselineEntry="true"
    findUnusedCode="true"
    cacheDirectory="build/psalm"
>
    <projectFiles>
        <directory name="packages/*/src"/>
        <ignoreFiles>
            <directory name="packages/*/tests"/>
            <directory name="packages/*/vendor"/>
        </ignoreFiles>
    </projectFiles>

    <issueHandlers>
        <!-- FFI: неизбежные несоответствия типов -->
        <MixedPropertyFetch errorLevel="suppress"/>
        <MixedMethodCall errorLevel="suppress"/>
        <MixedArgument errorLevel="suppress"/>
        <MixedAssignment errorLevel="suppress"/>
        <MixedReturnStatement errorLevel="suppress"/>
        <MixedReturnTypeCoercion errorLevel="suppress"/>
        <UndefinedMagicMethod>
            <errorLevel type="suppress">
                <referencedMethod name="FFI::new"/>
                <referencedMethod name="FFI::cast"/>
            </errorLevel>
        </UndefinedMagicMethod>
    </issueHandlers>
</psalm>
```

---

## 5. КАЧЕСТВО КОДА

### 5.1. PHP CS Fixer

**Файл:** `.php-cs-fixer.dist.php`

```php
<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/packages')
    ->exclude('vendor')
    ->exclude('tests/fixtures')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'single_quote' => true,
        'no_extra_blank_lines' => true,
        'phpdoc_align' => true,
        'phpdoc_order' => true,
        'phpdoc_trim' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try', 'if', 'for', 'foreach', 'while', 'do', 'switch'],
        ],
        // Строгость
        'declare_equal_normalize' => ['space' => 'none'],
        'dir_constant' => true,
        'is_null' => true,
        'modernize_strpos' => true,
        'no_alias_functions' => true,
        'no_trailing_comma_in_singleline' => true,
    ])
    ->setFinder($finder);
```

### 5.2. EditorConfig

**Файл:** `.editorconfig`

```ini
root = true

[*]
indent_style = space
indent_size = 4
end_of_line = lf
charset = utf-8
trim_trailing_whitespace = true
insert_final_newline = true

[*.md]
trim_trailing_whitespace = false

[*.{yml,yaml,neon,xml,json,jsonc}]
indent_size = 2

[Makefile]
indent_style = tab
```

---

## 6. GIT

### 6.1. `.gitignore`

```gitignore
# Composer
/vendor/
packages/*/vendor/
composer.lock

# Build
/build/
/native-binaries/

# IDE
.idea/
.vscode/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db
Desktop.ini

# Test
.phpunit.result.cache
.phpunit.cache/
phpunit.xml
.psalm-cache/
build/phpunit/
build/psalm/
build/coverage/
tests/fixtures/*.onnx
tests/fixtures/*.gguf
tests/fixtures/*.json
!tests/fixtures/.gitkeep

# Env
.env
.env.*

# Temp
/tmp/
/TEMP/
*.tmp
*.bak

# Infection
infection.log
build/infection/

# Benchmarks
benchmarks/models/

# Docker
docker-compose.override.yml

# Archive formats
*.ai
!tests/fixtures/*.ai
```

### 6.2. `.gitattributes`

```gitattributes
# Export-ignore: не включать в архив при скачивании релиза
/.github            export-ignore
/tests              export-ignore
/benchmarks         export-ignore
/docs               export-ignore
/native-binaries    export-ignore
/tmp                export-ignore

# Normalize line endings
*                   text=auto

# Specific files
*.php               text eol=lf diff=php
*.md                text eol=lf
*.json              text eol=lf
*.xml               text eol=lf
*.neon              text eol=lf
*.yml               text eol=lf
*.yaml              text eol=lf
*.dist              text eol=lf
*.sh                text eol=lf
*.bat               text eol=crlf
```

### 6.3. Git Hooks (CaptainHook)

**Файл:** `captainhook.json` (генерируется `vendor/bin/captainhook configure`)

```json
{
    "commit-msg": {
        "enabled": true,
        "actions": [
            {
                "action": "\\CaptainHook\\App\\Hook\\Message\\Rule\\RegexMatching",
                "options": {
                    "regex": "/^(feat|fix|docs|style|refactor|perf|test|chore|ci|build|revert)(\([a-z-]+\))?: .{1,72}/"
                }
            }
        ]
    },
    "pre-commit": {
        "enabled": true,
        "actions": [
            {
                "action": "composer lint -- --diff"
            },
            {
                "action": "composer test"
            }
        ]
    },
    "pre-push": {
        "enabled": true,
        "actions": [
            {
                "action": "composer check"
            }
        ]
    }
}
```

**Установка хуков:**
```bash
vendor/bin/captainhook install --force
```

---

## 7. CI/CD (GitHub Actions)

### 7.1. Основной CI: `.github/workflows/ci.yml`

```yaml
name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

jobs:
  # ============================================================
  # Статический анализ (быстрый, без матрицы ОС)
  # ============================================================
  static-analysis:
    name: "Static Analysis"
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: ffi, json, hash, fileinfo, pdo_sqlite, sodium, zip, opcache
          coverage: none

      - run: composer install --no-progress --no-interaction --prefer-dist

      - name: PHP CS Fixer
        run: vendor/bin/php-cs-fixer check --diff --no-interaction

      - name: PHPStan
        run: vendor/bin/phpstan analyse --no-progress --error-format=github

      - name: Psalm
        run: vendor/bin/psalm --no-progress --output-format=github --shepherd

  # ============================================================
  # Тесты (матрица: PHP × ОС)
  # ============================================================
  tests:
    name: "PHP ${{ matrix.php }} on ${{ matrix.os }}"
    runs-on: ${{ matrix.os }}
    needs: static-analysis
    strategy:
      fail-fast: false
      matrix:
        os: [ubuntu-latest, macos-latest, windows-latest]
        php: ['8.5', '8.6']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: ffi, json, hash, fileinfo, pdo_sqlite, sodium, zip, opcache
          coverage: xdebug
          ini-values: ffi.enable=true, memory_limit=512M

      - name: Validate composer.json
        run: composer validate --strict --no-check-lock

      - name: Install dependencies
        run: composer install --no-progress --no-interaction --prefer-dist

      - name: Run unit tests (Windows PowerShell)
        if: runner.os == 'Windows'
        run: |
          $env:FERRY_AI_SKIP_NATIVE = '1'
          vendor/bin/phpunit --testsuite unit --no-coverage

      - name: Run unit tests (Linux / macOS)
        if: runner.os != 'Windows'
        run: |
          FERRY_AI_SKIP_NATIVE=1 vendor/bin/phpunit --testsuite unit --no-coverage

      - name: Run integration tests (Linux only)
        if: runner.os == 'Linux'
        run: |
          bash .github/scripts/download-test-models.sh
          vendor/bin/phpunit --testsuite integration --no-coverage

      - name: Upload coverage to Codecov
        if: matrix.os == 'ubuntu-latest' && matrix.php == '8.5'
        uses: codecov/codecov-action@v4
        with:
          files: build/coverage/clover.xml
          token: ${{ secrets.CODECOV_TOKEN }}

  # ============================================================
  # Мутационное тестирование (только Linux, только 8.5)
  # ============================================================
  mutation:
    name: "Mutation Testing"
    runs-on: ubuntu-latest
    needs: tests
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: ffi, json, hash
          coverage: xdebug

      - run: composer install --no-progress --no-interaction --prefer-dist
      - run: vendor/bin/infection --threads=4 --min-msi=70 --min-covered-msi=80 --logger-github

  # ============================================================
  # Безопасность
  # ============================================================
  security:
    name: "Security Audit"
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none

      - run: composer install --no-progress --no-interaction --prefer-dist
      - run: composer audit --no-interaction
```

### 7.2. Скрипт загрузки тестовых моделей

**Файл:** `.github/scripts/download-test-models.sh`

```bash
#!/usr/bin/env bash
set -euo pipefail

FIXTURES_DIR="tests/fixtures"
mkdir -p "$FIXTURES_DIR"

# Минимальная ONNX-модель для тестов (сложения векторов)
if [ ! -f "$FIXTURES_DIR/test_model.onnx" ]; then
    echo "Downloading test ONNX model..."
    # Генерируем простую модель: y = x + 1
    python3 -c "
import onnx
from onnx import helper, TensorProto
import numpy as np

X = helper.make_tensor_value_info('x', TensorProto.FLOAT, [1, 3])
Y = helper.make_tensor_value_info('y', TensorProto.FLOAT, [1, 3])
bias = helper.make_tensor('bias', TensorProto.FLOAT, [1, 3], np.array([1.0, 1.0, 1.0]))
node = helper.make_node('Add', ['x', 'bias'], ['y'])
graph = helper.make_graph([node], 'test_add', [X], [Y], [bias])
model = helper.make_model(graph, opset_imports=[helper.make_opsetid('', 17)])
onnx.save(model, '$FIXTURES_DIR/test_model.onnx')
"
fi

# Текстовый фикстурный токенизатор (минимальный)
if [ ! -f "$FIXTURES_DIR/tokenizer.json" ]; then
    echo "Creating minimal tokenizer.json..."
    cat > "$FIXTURES_DIR/tokenizer.json" << 'EOF'
{
  "version": "1.0",
  "model": {
    "type": "BPE",
    "vocab": {"<s>": 0, "</s>": 1, "<unk>": 2, "<pad>": 3, "hello": 4, "world": 5},
    "merges": []
  }
}
EOF
fi

echo "Test fixtures ready."
```

### 7.3. Релизный workflow: `.github/workflows/release.yml`

```yaml
name: Release

on:
  push:
    tags:
      - 'v*.*.*'

jobs:
  # ============================================================
  # Публикация на Packagist (через subtree split)
  # ============================================================
  packagist:
    name: "Publish to Packagist"
    runs-on: ubuntu-latest
    strategy:
      matrix:
        package:
          - core
          - tensor
          - onnx-backend
          - llama-backend
          - cpu-backend
          - tokenizer
          - embedding
          - pipeline
          - model-hub
          - vector
          - dataframe
          - ai
          - laravel
          - symfony

    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Split ${{ matrix.package }} subtree
        run: |
          BRANCH="split/${{ matrix.package }}"
          git subtree split \
            --prefix=packages/${{ matrix.package }} \
            --branch=$BRANCH

      - name: Push ${{ matrix.package }} to its repo
        run: |
          git push \
            https://x-access-token:${{ secrets.PACKAGIST_TOKEN }}@github.com/MADEVAL/${{ matrix.package }}.git \
            split/${{ matrix.package }}:main --force

      - name: Notify Packagist
        run: |
          curl -X POST \
            -H 'Content-Type: application/json' \
            -d '{"repository":{"url":"https://github.com/MADEVAL/${{ matrix.package }}"}}' \
            https://packagist.org/api/update-package?username=php-ai&apiToken=${{ secrets.PACKAGIST_API_TOKEN }}   # TODO: заменить php-ai на реальный Packagist-аккаунт

  # ============================================================
  # Сборка нативных бинарников
  # ============================================================
  build-binaries:
    name: "Build native binaries"
    runs-on: ${{ matrix.os }}
    strategy:
      matrix:
        include:
          - os: ubuntu-latest
            target: linux-x86_64
            lib-ext: so
          - os: macos-latest
            target: macos-arm64
            lib-ext: dylib
          - os: macos-13
            target: macos-x86_64
            lib-ext: dylib
          - os: windows-latest
            target: windows-x86_64
            lib-ext: dll

    steps:
      - uses: actions/checkout@v4

      - name: Build ONNX Runtime (Linux)
        if: runner.os == 'Linux'
        run: |
          # Используем precompiled ONNX Runtime
          wget https://github.com/microsoft/onnxruntime/releases/download/v1.18.0/onnxruntime-linux-x64-1.18.0.tgz
          tar xzf onnxruntime-linux-x64-1.18.0.tgz
          cp onnxruntime-linux-x64-1.18.0/lib/libonnxruntime.so native-binaries/${{ matrix.target }}/libonnxruntime.${{ matrix.lib-ext }}

      - name: Build llama.cpp (Linux)
        if: runner.os == 'Linux'
        run: |
          git clone --depth 1 https://github.com/ggerganov/llama.cpp
          cd llama.cpp && cmake -B build && cmake --build build --config Release -j
          cp build/libllama.${{ matrix.lib-ext }} ../native-binaries/${{ matrix.target }}/libllama.${{ matrix.lib-ext }}

      # ... аналогичные шаги для macOS и Windows

      - name: Upload binaries
        uses: actions/upload-artifact@v4
        with:
          name: native-binaries-${{ matrix.target }}
          path: native-binaries/${{ matrix.target }}/

  # ============================================================
  # GitHub Release
  # ============================================================
  github-release:
    name: "Create GitHub Release"
    runs-on: ubuntu-latest
    needs: [packagist, build-binaries]
    steps:
      - uses: actions/checkout@v4

      - name: Download all binary artifacts
        uses: actions/download-artifact@v4
        with:
          path: native-binaries/

      - name: Create Release
        uses: softprops/action-gh-release@v2
        with:
          name: "PHP AI Platform ${{ github.ref_name }}"
          body_path: CHANGELOG.md
          files: |
            native-binaries/**/*
            README.md
            LICENSE.md
          token: ${{ secrets.GITHUB_TOKEN }}
          draft: false
          prerelease: ${{ contains(github.ref_name, 'alpha') || contains(github.ref_name, 'beta') || contains(github.ref_name, 'rc') }}
```

---

## 8. ИНСТРУМЕНТЫ РАЗРАБОТКИ

### 8.1. Локальная разработка (Windows)

**Установка PHP на Windows:**
```powershell
# Вариант 1: Установщик с php.net
# Скачать https://windows.php.net/downloads/releases/php-8.5.x-nts-Win32-vs17-x64.zip
# Распаковать в C:\php
# Добавить C:\php в PATH

# Вариант 2: Chocolatey
choco install php --version=8.5.0

# Включить расширения в php.ini:
# extension=ffi
# extension=pdo_sqlite
# extension=sodium
# extension=zip
# extension=opcache
# extension=fileinfo
# ffi.enable=true
```

**Установка Composer:**
```powershell
# Скачать и запустить https://getcomposer.org/Composer-Setup.exe
# Или через PowerShell:
Invoke-WebRequest -Uri https://getcomposer.org/installer -OutFile composer-setup.php
php composer-setup.php --install-dir=C:\bin --filename=composer
```

**Клонирование и настройка:**
```powershell
git clone https://github.com/MADEVAL/FerryAI.git php-inference
cd php-inference
composer install

# Проверка окружения
php -r "echo 'PHP ' . PHP_VERSION . PHP_EOL; echo 'FFI: ' . (extension_loaded('ffi') ? 'enabled' : 'disabled') . PHP_EOL;"

# Запуск тестов
composer test

# Запуск линтеров
composer lint
```

### 8.2. Скрипт быстрой проверки окружения

**Файл:** `bin/ferry-ai` (CLI-инструмент)

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

// Минимальный CLI для проверки и диагностики
$command = $argv[1] ?? 'help';

match ($command) {
    'check' => checkEnvironment(),
    'version' => echoVersion(),
    'help' => showHelp(),
    default => print("Unknown command: $command\nUse 'ferry-ai help'\n"),
};

function checkEnvironment(): void
{
    $checks = [
        'PHP Version' => PHP_VERSION,
        'PHP >= 8.5' => PHP_VERSION_ID >= 80500 ? 'OK' : 'FAIL',
        'FFI' => extension_loaded('ffi') ? 'enabled' : 'disabled',
        'PDO SQLite' => extension_loaded('pdo_sqlite') ? 'enabled' : 'disabled',
        'Sodium' => extension_loaded('sodium') ? 'enabled' : 'disabled',
        'Zip' => extension_loaded('zip') ? 'enabled' : 'disabled',
        'OPcache' => extension_loaded('Zend OPcache') ? 'enabled' : 'disabled',
        'Hash' => extension_loaded('hash') ? 'enabled' : 'disabled',
        'JSON' => extension_loaded('json') ? 'enabled' : 'disabled',
        'Fileinfo' => extension_loaded('fileinfo') ? 'enabled' : 'disabled',
        'OS' => PHP_OS_FAMILY,
        'Arch' => php_uname('m'),
    ];

    foreach ($checks as $label => $status) {
        printf("  %-20s %s\n", $label . ':', $status);
    }

    // Проверка доступности нативных библиотек
    echo "\nNative libraries:\n";
    $libs = [
        'ONNX Runtime' => PHP_OS_FAMILY === 'Windows' ? 'onnxruntime.dll' : 'libonnxruntime.' . (PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so'),
        'llama.cpp' => PHP_OS_FAMILY === 'Windows' ? 'llama.dll' : 'libllama.' . (PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so'),
    ];
    foreach ($libs as $name => $file) {
        $found = shell_exec(PHP_OS_FAMILY === 'Windows'
            ? "where $file 2>nul"
            : "which $file 2>/dev/null || ldconfig -p | grep -q $file && echo found");
        printf("  %-20s %s\n", $name . ':', $found ? 'found' : 'not found');
    }
}

function echoVersion(): void
{
    echo "PHP AI Platform v1.0.0\n";
}

function showHelp(): void
{
    echo <<<HELP
PHP AI Platform CLI

Usage:
  ferry-ai check     Check environment and dependencies
  ferry-ai version   Show version
  ferry-ai help      Show this help

HELP;
}
```

### 8.3. Makefile (для Linux/macOS)

**Файл:** `Makefile`

```makefile
.PHONY: install test lint check clean coverage benchmark

install:
	composer install

test:
	composer test

test-integration:
	composer test-integration

lint:
	composer lint

check:
	composer check

coverage:
	composer coverage

benchmark:
	composer benchmark

mutation:
	composer mutation

clean:
	rm -rf build/
	rm -rf packages/*/vendor/
	rm -rf vendor/
	rm -f composer.lock

# Для Windows аналог в PowerShell
.PHONY: docs
docs:
	# Генерация документации (например, через phpDocumentor)
	echo "Documentation generation not configured yet"
```

**PowerShell-эквивалент для Windows** (`Makefile.ps1`):

```powershell
param([string]$target = "help")

switch ($target) {
    "install" { composer install }
    "test" { composer test }
    "test-integration" { composer test-integration }
    "lint" { composer lint }
    "check" { composer check }
    "coverage" { composer coverage }
    "clean" {
        Remove-Item -Recurse -Force build/ -ErrorAction SilentlyContinue
        Get-ChildItem packages -Directory | ForEach-Object {
            Remove-Item -Recurse -Force "$($_.FullName)/vendor/" -ErrorAction SilentlyContinue
        }
        Remove-Item composer.lock -ErrorAction SilentlyContinue
        Remove-Item -Recurse -Force vendor/ -ErrorAction SilentlyContinue
    }
    default {
        Write-Host @"
PHP AI Platform Makefile.ps1

Usage: .\Makefile.ps1 <target>

Targets:
  install        Install dependencies
  test           Run unit tests
  test-integration  Run integration tests
  lint           Run linters
  check          Lint + test
  coverage       Generate coverage report
  clean          Remove build artifacts
"@
    }
}
```

---

## 9. ПУБЛИКАЦИЯ

### 9.1. Packagist

Каждый подпакет публикуется отдельно на Packagist:

| Пакет | Packagist URL |
|---|---|
| `ferry-ai/inference-core` | https://packagist.org/packages/ferry-ai/inference-core |
| `ferry-ai/inference-tensor` | https://packagist.org/packages/ferry-ai/inference-tensor |
| ... | ... |
| `ferry-ai/php-inference` | https://packagist.org/packages/ferry-ai/php-inference |

**Требования Packagist:**
- Каждый пакет в отдельном git-репозитории (через subtree split)
- Валидный `composer.json` в корне репозитория
- PSR-4 автозагрузка
- Git-теги в формате `vX.Y.Z`

**Автоматизация:** GitHub Actions workflow `release.yml` выполняет subtree split и пуш в отдельные репозитории при создании тега.

### 9.2. Нативные бинарники

Нативные бинарники (ONNX Runtime, llama.cpp) публикуются как GitHub Release assets.

**Структура GitHub Release:**
```
v1.0.0
├── ferry-ai-native-linux-x86_64.tar.gz
│   ├── libonnxruntime.so
│   ├── libllama.so
│   └── libsqlitevec.so
├── ferry-ai-native-linux-aarch64.tar.gz
├── ferry-ai-native-macos-arm64.tar.gz
├── ferry-ai-native-macos-x86_64.tar.gz
├── ferry-ai-native-windows-x86_64.zip
│   ├── onnxruntime.dll
│   ├── llama.dll
│   └── sqlitevec.dll
└── checksums.sha256
```

**Автоскачивание в рантайме:**
Пакет `ai` содержит `NativeBinaryManager`, который:
1. Определяет платформу
2. Ищет библиотеку в системе
3. Если нет — скачивает с GitHub Releases
4. Проверяет SHA-256
5. Кэширует локально

### 9.3. Документация

Публикуется через GitHub Pages.  
Репозиторий: `MADEVAL/ferry-ai-docs` (или `gh-pages` ветка основного репо).

---

## 10. БЕЗОПАСНОСТЬ РЕПОЗИТОРИЯ

### 10.1. `SECURITY.md`

```markdown
# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.x     | :white_check_mark: |
| < 1.0   | :x:               |

## Reporting a Vulnerability

Please report security vulnerabilities to **security@php-ai.dev**. <!-- TODO: заменить на реальный домен/email -->

Do NOT create public GitHub issues for security vulnerabilities.

We will respond within 48 hours and publish a fix as soon as possible.

## Security Model

- Models are verified via SHA-256 and Ed25519 signatures
- Native code is isolated via FFI sandboxing
- All network requests use HTTPS
- File system access is restricted to `model_cache` directory
```

### 10.2. `CODE_OF_CONDUCT.md`

Стандартный Contributor Covenant.

### 10.3. `CONTRIBUTING.md`

```markdown
# Contributing to PHP AI Platform

## Development Setup

1. Clone the repository
2. Run `composer install`
3. Run `composer check` to verify everything works

## Code Style

We follow PER-CS 2.0. Run `composer cs-fix` before committing.

## Testing

- Unit tests: `composer test`
- Integration tests: `composer test-integration` (requires native libraries)
- All checks: `composer check`

## Commit Messages

Format: `type(package): description`

Types: feat, fix, docs, style, refactor, perf, test, chore, ci, build, revert

Example: `feat(core): add Shape value object`

## Pull Requests

1. Create a feature branch from `develop`
2. Make your changes
3. Run `composer check` — must pass
4. Create a PR to `develop`
5. CI must pass on all platforms
```

### 10.4. `LICENSE.md`

MIT License.

---

## 11. DOCKER ДЛЯ ТЕСТИРОВАНИЯ

### 11.1. `Dockerfile` (dev-образ)

```dockerfile
FROM php:8.5-cli

# Системные зависимости
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libsodium-dev \
    libsqlite3-dev \
    wget \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP-расширения
RUN docker-php-ext-install \
    ffi \
    zip \
    sodium \
    pdo_sqlite \
    opcache \
    fileinfo

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Нативные библиотеки (копируем из prebuilt)
COPY native-binaries/linux-x86_64/*.so /usr/local/lib/
RUN ldconfig

# FFI
RUN echo 'ffi.enable=true' >> /usr/local/etc/php/conf.d/ffi.ini

WORKDIR /app
COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

CMD ["php", "-a"]
```

### 11.2. `docker-compose.yml` (dev-окружение)

```yaml
services:
  php:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - .:/app
      - composer-cache:/root/.composer
      - model-cache:/var/cache/ferry-ai-models
    environment:
      FERRY_AI_BACKEND: auto
      FERRY_AI_DEVICE: auto
      FERRY_AI_MODEL_CACHE: /var/cache/ferry-ai-models
      FERRY_AI_TESTING: 1
    command: tail -f /dev/null

volumes:
  composer-cache:
  model-cache:
```

---

## 12. ПОЛНЫЙ СПИСОК DEV-ЗАВИСИМОСТЕЙ

| Пакет | Назначение | Обязателен |
|---|---|---|
| `phpunit/phpunit` | Unit-тесты | Да |
| `pestphp/pest` | Альтернативный тест-фреймворк | Нет |
| `pestphp/pest-plugin-parallel` | Параллельные тесты | Нет |
| `phpstan/phpstan` | Статический анализ (level 8) | Да |
| `phpstan/phpstan-strict-rules` | Строгие правила PHPStan | Да |
| `phpstan/phpstan-deprecation-rules` | Детект устаревшего кода | Да |
| `vimeo/psalm` | Статический анализ (level 3) | Да |
| `squizlabs/php_codesniffer` | Альтернативный линтер | Нет |
| `php-cs-fixer/shim` | Автофикс код-стиля | Да |
| `infection/infection` | Мутационное тестирование | Рекомендован |
| `symplify/monorepo-builder` | Управление монорепо | Да |
| `roave/security-advisories` | Блокировка уязвимых пакетов | Да |
| `ergebnis/composer-normalize` | Нормализация composer.json | Рекомендован |
| `captainhook/captainhook` | Git-хуки | Рекомендован |

---

## 13. ЧЕКЛИСТ: НОВЫЙ РАЗРАБОТЧИК

После клонирования репозитория новый разработчик выполняет:

```bash
# 1. Клонирование
git clone https://github.com/MADEVAL/FerryAI.git php-inference
cd php-inference

# 2. Установка зависимостей
composer install

# 3. Проверка окружения
php bin/ferry-ai check

# 4. Установка git-хуков (опционально)
vendor/bin/captainhook install --force

# 5. Запуск тестов
composer test

# 6. Запуск линтеров
composer lint

# 7. Полная проверка
composer check
```

**Если все шаги зелёные — можно начинать разработку.**

---

> **Документ является неотъемлемой частью технического задания. Репозиторий должен быть создан в точном соответствии с данной спецификацией.**
