# FerryAI — Repository Infrastructure

> Version: 1.0  
> Purpose: complete description of the monorepo infrastructure — composer, testing, CI/CD, publication, tools  
> Target OS: Windows (primary development), Linux, macOS  
> Principle: single configuration for all platforms, no developer should guess how to run

---

## 0. ROOT REPOSITORY STRUCTURE

```
php-inference/
├── .github/
│   ├── workflows/
│   │   ├── ci.yml                    # Main CI: tests + static analysis
│   │   └── release.yml               # Native binary build + GitHub Release
│   ├── dependabot.yml               # Auto-update dependencies
│   ├── CODE_OF_CONDUCT.md
│   ├── CONTRIBUTING.md
│   └── SECURITY.md
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
│   └── ferry-ai                      # CLI tool
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
│   └── specs/                         # Validated design specs (YYYY-MM-DD-<topic>-design.md)
├── tests/
│   ├── Integration/                   # Integration tests (cross-package, @group integration)
│   ├── Verification/                  # Runtime bug/audit verification tests (@coversNothing)
│   └── .env                          # Environment variables for tests
├── .gitattributes
├── .gitignore
├── .editorconfig
├── .php-cs-fixer.dist.php
├── phpstan.neon
├── psalm.xml
├── phpunit.xml.dist
├── infection.json5                   # Mutation testing
├── composer.json                     # Root: meta-package + dev dependencies
├── monorepo-builder.php              # Monorepo tool (symplify)
├── CHANGELOG.md
├── LICENSE.md
├── README.md
└── ...
```

---

## 1. COMPOSER — PACKAGE MANAGEMENT

### 1.1. Root `composer.json`

```json
{
    "name": "ferry-ai/php-inference",
    "description": "FerryAI — unified inference API for PHP applications",
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
        "phpunit/phpunit": "^13.0",
        "phpstan/phpstan": "^2.0",
        "phpstan/phpstan-strict-rules": "^2.0",
        "phpstan/phpstan-deprecation-rules": "^2.0",
        "vimeo/psalm": "^6.0",
        "squizlabs/php_codesniffer": "^4.0",
        "php-cs-fixer/shim": "^3.0",
        "infection/infection": "^0.34",
        "pestphp/pest": "^4.0",
        "pestphp/pest-plugin-parallel": "^4.0",
        "symplify/monorepo-builder": "^12.0",
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

### 1.2. Package `composer.json` (template for all subpackages)

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
        "phpunit/phpunit": "^13.0",
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

**Features per package:**

| Package | Additional `require` | `provide` / `suggest` |
|---|---|---|
| `core` | (none) | — |
| `tensor` | `ferry-ai/inference-core: ^0.1.0` | `ext-random: *` (for random-fill) |
| `onnx-backend` | `ferry-ai/inference-core: ^0.1.0`, `phpmlkit/onnxruntime: ^1.0` | `ankane/onnxruntime: ^0.3` (fallback) |
| `llama-backend` | `ferry-ai/inference-core: ^0.1.0` | — |
| `cpu-backend` | `ferry-ai/inference-core: ^0.1.0` | `rubix/ml: ^2.0`, `rubix/tensor: ^3.0` |
| `tokenizer` | `ferry-ai/inference-core: ^0.1.0` | — |
| `embedding` | `ferry-ai/inference-core: ^0.1.0`, `ferry-ai/inference-onnx-backend: ^0.1.0`, `ferry-ai/inference-tokenizer: ^0.1.0` | — |
| `pipeline` | `ferry-ai/inference-core: ^0.1.0` | — |
| `model-hub` | `ferry-ai/inference-core: ^0.1.0`, `codewithkyrian/huggingface: ^1.0` | `ext-zip: *`, `ext-sodium: *` |
| `vector` | `ferry-ai/inference-core: ^0.1.0`, `ferry-ai/inference-embedding: ^0.1.0` | `ext-pdo_sqlite: *` or `ext-sqlite3: *` |
| `dataframe` | `ferry-ai/inference-core: ^0.1.0`, `ferry-ai/inference-tensor: ^0.1.0` | — |
| `ai` | `ferry-ai/inference-core: ^0.1.0`, `ferry-ai/inference-onnx-backend: ^0.1.0`, `ferry-ai/inference-llama-backend: ^0.1.0`, `ferry-ai/inference-cpu-backend: ^0.1.0`, `ferry-ai/inference-tokenizer: ^0.1.0`, `ferry-ai/inference-embedding: ^0.1.0`, `ferry-ai/inference-pipeline: ^0.1.0`, `ferry-ai/inference-model-hub: ^0.1.0`, `ferry-ai/inference-vector: ^0.1.0` | `psr/http-message: ^2.0` (for StreamResponse) |
| `laravel` | `ferry-ai/inference-ai: ^0.1.0`, `illuminate/support: ^12.0 \|\| ^13.0` | — |
| `symfony` | `ferry-ai/inference-ai: ^0.1.0`, `symfony/http-kernel: ^7.4 \|\| ^8.0`, `symfony/config: ^7.4 \|\| ^8.0`, `symfony/dependency-injection: ^7.4 \|\| ^8.0` | — |

---

## 2. MONOREPO MANAGEMENT

### 2.1. Tool: `symplify/monorepo-builder`

**File:** `monorepo-builder.php`

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

**Commands:**
```bash
# Validate monorepo structure
vendor/bin/monorepo-builder validate

# Update mutual package dependencies (during development)
vendor/bin/monorepo-builder merge

# View merged composer.json (for debugging)
vendor/bin/monorepo-builder merge --dry-run

# Release
vendor/bin/monorepo-builder release v1.0.0
```

### 2.2. Publishing packages to Packagist (manual)

Packagist publishing is **not automated in CI** — it is performed manually by the maintainer.
CI (`release.yml`) only builds native binaries and creates the GitHub Release. Each subpackage is
published to Packagist from its own repository, produced by a git subtree split of `packages/<name>`.

**One-time setup (per package):**
1. Create a mirror repository on GitHub, e.g. `MADEVAL/inference-core`.
2. Register it on Packagist (Submit → repository URL) under the `ferry-ai/*` vendor.
3. Optionally enable the Packagist GitHub webhook/integration so future pushes auto-update.

**Release procedure:**

```bash
# 1. Sync mutual dependency constraints to the release version and tag the monorepo.
#    monorepo-builder rewrites `ferry-ai/inference-*` requirements across all packages.
vendor/bin/monorepo-builder validate
vendor/bin/monorepo-builder release v0.1.0

# 2. Split each package into its own branch and push it to the mirror repository.
#    Repeat for every package under packages/ (core, tensor, onnx-backend, ... , symfony).
git subtree split --prefix=packages/core --branch=split/core
git push https://github.com/MADEVAL/inference-core.git split/core:main --force

# 3. Tag the same version in each mirror repository (Packagist reads tags from there).
#    Either push the tag to the mirror, or let the Packagist webhook pick up the push.
```

If a mirror is not connected via webhook, trigger a manual update from the package page on
Packagist (the "Update" button) after pushing.

> Note: `monorepo-builder merge` / `merge --dry-run` are for local development (viewing the merged
> root `composer.json`); they do not publish anything.

---

## 3. TESTING

### 3.1. PHPUnit (main framework)

**File:** `phpunit.xml.dist`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/13.0/phpunit.xsd"
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
        <!-- Do not require native libraries for unit tests -->
        <env name="FERRY_AI_SKIP_NATIVE" value="1" force="false"/>
    </php>
</phpunit>
```

**Test layout for each package:**

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
    │   └── Contracts/             # Contract tests (abstract test cases)
    │       ├── BackendContractTest.php
    │       ├── ModelContractTest.php
    │       └── TensorContractTest.php
    └── Integration/               # If needed (usually in root tests/)
```

### 3.2. Pest PHP (alternative/additional framework)

Pest is installed as a dev dependency in the root composer.json. Used optionally — the developer chooses PHPUnit or Pest.

```bash
# Run tests via Pest (all)
vendor/bin/pest

# Parallel run
vendor/bin/pest --parallel

# Specific file
vendor/bin/pest packages/core/tests/Unit/Enums/DeviceTest.php

# With coverage
vendor/bin/pest --coverage
```

### 3.3. Mock objects and FFI

**Problem:** FFI cannot be mocked with standard tools because it is a C extension.  
**Solution:** Abstraction via interfaces.

For each native call, a thin wrapper interface is created:

```php
// Example: interface for LlamaCpp
interface LlamaCppInterface
{
    public function modelLoadFromFile(string $path): object;
    public function contextInitFromModel(object $model): object;
    // ...
}

// Real implementation
class NativeLlamaCpp implements LlamaCppInterface { /* FFI calls */ }

// Mock implementation for tests
class MockLlamaCpp implements LlamaCppInterface { /* returns predictable data */ }
```

Constructor injection (Dependency Injection):
```php
class LlamaBackend
{
    public function __construct(
        private LlamaCppInterface $llamaCpp = new NativeLlamaCpp(),
    ) {}
}
```

Test:
```php
$mock = new MockLlamaCpp();
$backend = new LlamaBackend($mock);
// test without real llama.cpp
```

### 3.4. Abstract test classes for contracts

For each interface in `core/Contracts`, an abstract test class is created that verifies the contract. Each concrete implementation extends this test:

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

### 3.5. Integration tests

**File:** `tests/Integration/.env`

```env
# Paths to test models (downloaded by CI script)
FERRY_AI_TEST_ONNX_MODEL=./tests/fixtures/test_model.onnx
FERRY_AI_TEST_GGUF_MODEL=./tests/fixtures/test_model.gguf
FERRY_AI_TEST_TOKENIZER=./tests/fixtures/tokenizer.json

# Skip integration tests if models are missing
FERRY_AI_SKIP_MISSING_MODELS=1
```

**Integration test structure:**

```
tests/
├── Integration/
│   ├── Onnx/
│   │   ├── OnnxInferenceTest.php       # Real ONNX inference
│   │   ├── OnnxProvidersTest.php       # Providers (CPU/CUDA)
│   │   └── OnnxTensorTest.php          # Tensor operations
│   ├── Llama/
│   │   ├── LlamaChatTest.php           # Chat with LLM
│   │   ├── LlamaStreamingTest.php      # Streaming
│   │   └── LlamaSamplingTest.php       # Samplers
│   ├── Embedding/
│   │   ├── EmbeddingTest.php           # Embeddings
│   │   └── BatchEmbeddingTest.php      # Batch embeddings
│   ├── Vector/
│   │   ├── VectorStoreTest.php         # CRUD + search
│   │   └── FilterTest.php              # Metadata filtering
│   ├── Pipeline/
│   │   └── PipelineIntegrationTest.php # Full pipeline
│   └── RAG/
│       └── RagFlowTest.php             # End-to-end: chunk → embed → store → search
├── fixtures/
│   └── .gitkeep                        # Test models (in .gitignore, downloaded by CI)
└── bootstrap.php                       # Common bootstrap for integration tests
```

---

## 4. STATIC ANALYSIS

### 4.1. PHPStan

**File:** `phpstan.neon`

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
        # FFI calls cannot be fully typed
        - '#Cannot access property \$cdata on mixed#'
        - '#Call to an undefined method .*\\FFI::new\(\)#'
        - '#Parameter \#1 \$ptr of function .* expects .*FFI\\CData#'
    typeAliases:
        TokenIds: list<int>
        Vector: list<float>
```

**Run:**
```bash
vendor/bin/phpstan analyse --memory-limit=512M
```

### 4.2. Psalm

**File:** `psalm.xml`

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
        <!-- FFI: unavoidable type mismatches -->
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

## 5. CODE QUALITY

### 5.1. PHP CS Fixer

**File:** `.php-cs-fixer.dist.php`

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
        // Strictness
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

**File:** `.editorconfig`

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

# Archive formats
*.ai
!tests/fixtures/*.ai
```

### 6.2. `.gitattributes`

```gitattributes
# Export-ignore: exclude from archive when downloading release
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

**File:** `captainhook.json` (generated by `vendor/bin/captainhook configure`)

```json
{
    "commit-msg": {
        "enabled": true,
        "actions": [
            {
                "action": "\\CaptainHook\\App\\Hook\\Message\\Rule\\RegexMatching",
                "options": {
                    "regex": "/^(feat|fix|docs|style|refactor|perf|test|chore|ci|build|revert)(\\([a-z-]+\\))?: .{1,72}/"
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

**Install hooks:**
```bash
vendor/bin/captainhook install --force
```

---

## 7. CI/CD (GitHub Actions)

### 7.1. Main CI: `.github/workflows/ci.yml`

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
  # Static analysis (fast, no OS matrix)
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
  # Tests (matrix: PHP × OS)
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
  # Mutation testing (Linux only, 8.5 only)
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
  # Security
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

### 7.2. Test model download script

**File:** `.github/scripts/download-test-models.sh`

```bash
#!/usr/bin/env bash
set -euo pipefail

FIXTURES_DIR="tests/fixtures"
mkdir -p "$FIXTURES_DIR"

# Minimal ONNX model for tests (vector addition)
if [ ! -f "$FIXTURES_DIR/test_model.onnx" ]; then
    echo "Downloading test ONNX model..."
    # Generate a simple model: y = x + 1
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

# Text fixture tokenizer (minimal)
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

**Parquet fixture generator** — `scripts/generate_parquet_fixture.py` writes the DataFrame
Parquet test fixture at `packages/dataframe/tests/Unit/IO/fixtures/simple.parquet`. It is a
**dev-only** helper (the runtime never calls Python — see `AGENTS.md`); the generated
`.parquet` is committed, so `pyarrow` is not required to run the test suite. Re-run it only
when the fixture is missing or its schema changes:

```bash
pip install pyarrow
python scripts/generate_parquet_fixture.py
```

### 7.3. Release workflow: `.github/workflows/release.yml`

```yaml
name: Release

on:
  push:
    tags:
      - 'v*.*.*'

jobs:
  # ============================================================
  # Build native binaries
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
          # Use precompiled ONNX Runtime
          wget https://github.com/microsoft/onnxruntime/releases/download/v1.18.0/onnxruntime-linux-x64-1.18.0.tgz
          tar xzf onnxruntime-linux-x64-1.18.0.tgz
          cp onnxruntime-linux-x64-1.18.0/lib/libonnxruntime.so native-binaries/${{ matrix.target }}/libonnxruntime.${{ matrix.lib-ext }}

      - name: Build llama.cpp (Linux)
        if: runner.os == 'Linux'
        run: |
          git clone --depth 1 https://github.com/ggerganov/llama.cpp
          cd llama.cpp && cmake -B build && cmake --build build --config Release -j
          cp build/libllama.${{ matrix.lib-ext }} ../native-binaries/${{ matrix.target }}/libllama.${{ matrix.lib-ext }}

      # ... similar steps for macOS and Windows

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
    needs: [build-binaries]
    steps:
      - uses: actions/checkout@v4

      - name: Download all binary artifacts
        uses: actions/download-artifact@v4
        with:
          path: native-binaries/

      - name: Create Release
        uses: softprops/action-gh-release@v2
        with:
          name: "FerryAI ${{ github.ref_name }}"
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

## 8. DEVELOPMENT TOOLS

### 8.1. Local development (Windows)

**Installing PHP on Windows:**
```powershell
# Option 1: Installer from php.net
# Download https://windows.php.net/downloads/releases/php-8.5.x-nts-Win32-vs17-x64.zip
# Extract to C:\php
# Add C:\php to PATH

# Option 2: Chocolatey
choco install php --version=8.5.0

# Enable extensions in php.ini:
# extension=ffi
# extension=pdo_sqlite
# extension=sodium
# extension=zip
# extension=opcache
# extension=fileinfo
# ffi.enable=true
```

**Installing Composer:**
```powershell
# Download and run https://getcomposer.org/Composer-Setup.exe
# Or via PowerShell:
Invoke-WebRequest -Uri https://getcomposer.org/installer -OutFile composer-setup.php
php composer-setup.php --install-dir=C:\bin --filename=composer
```

**Cloning and setup:**
```powershell
git clone https://github.com/MADEVAL/FerryAI.git php-inference
cd php-inference
composer install

# Environment check
php -r "echo 'PHP ' . PHP_VERSION . PHP_EOL; echo 'FFI: ' . (extension_loaded('ffi') ? 'enabled' : 'disabled') . PHP_EOL;"

# Run tests
composer test

# Run linters
composer lint
```

### 8.2. Quick environment check script

**File:** `bin/ferry-ai` (CLI tool, registered under `composer.json` `bin`)

Commands:

| Command | Purpose |
|---------|---------|
| `check [--json]` (default) | PHP/OS, required + optional extensions, backend availability, model-cache path/size. `--json` for CI; exit code `1` if a required extension is missing or no backend is available |
| `models:list` | List cached models (`AI::hub()->list()`) |
| `models:download <id> [ver]` | Download a model from HuggingFace with progress (`AI::hub()->downloadWithProgress()`) |
| `models:prune` | Clear the model cache (`AI::hub()->prune()`) |
| `tokenize <text>` | Tokenize input text (uses `FERRY_AI_MODEL_DIR/tokenizer.json`) |
| `chat <message> [--stream] [--max=N]` | Run a single chat turn (needs `ferry_llama` + a GGUF model; see `FERRY_AI_LLAMA_DIR`/`FERRY_AI_LLAMA_MODEL`) |
| `version` | Installed package version (`Composer\InstalledVersions`) |
| `help` | Show usage |

All commands return exit code `0` on success and `1` on failure (missing argument,
error, or a failed `check`), so the CLI can be used as a CI gate.

```bash
php bin/ferry-ai check
# FerryAI Environment Check
# ==============================
# PHP:       8.5.4
# OS:        Windows (AMD64)
#
# Extensions (required):
#   ffi          OK
#   json         OK
#   hash         OK
#   fileinfo     OK
# Extensions (optional):
#   zip          OK            (.ai archives)
#   sodium       OK            (Ed25519 signatures)
#   pdo_sqlite   OK            (SQLite vector store)
#   pdo_pgsql    OK            (PostgreSQL vector store)
#   curl         OK            (faster downloads)
#   shmop        OK            (model pool shared memory)
#
# Backends:
#   onnx         available (1.27.0)
#   llama        unavailable
#   cpu          available
#
# Model cache:
#   path         /tmp/ferry-ai-models
#   size         0.00 B
#
# Status: OK

php bin/ferry-ai check --json          # machine-readable, exit 1 if not OK
php bin/ferry-ai models:list
php bin/ferry-ai models:download sentence-transformers/all-MiniLM-L6-v2
php bin/ferry-ai tokenize "Hello world"
php bin/ferry-ai chat "What is PHP?" --stream
```


### 8.3. Makefile (for Linux/macOS)

**File:** `Makefile`

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
```

**PowerShell equivalent for Windows** (`Makefile.ps1`):

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
FerryAI Makefile.ps1

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

## 9. PUBLICATION

### 9.1. Packagist

Each subpackage is published separately to Packagist:

| Package | Packagist URL |
|---|---|
| `ferry-ai/inference-core` | https://packagist.org/packages/ferry-ai/inference-core |
| `ferry-ai/inference-tensor` | https://packagist.org/packages/ferry-ai/inference-tensor |
| ... | ... |
| `ferry-ai/php-inference` | https://packagist.org/packages/ferry-ai/php-inference |

**Packagist requirements:**
- Each package in a separate git repository (via subtree split)
- Valid `composer.json` in the repository root
- PSR-4 autoloading
- Git tags in format `vX.Y.Z`

**Publishing is manual** (see §2.2): `release.yml` builds native binaries and the GitHub Release
only. Subtree split, push to the mirror repositories and the Packagist update are done by the
maintainer.

### 9.2. Native binaries

Native binaries (ONNX Runtime, llama.cpp) are published as GitHub Release assets.

**GitHub Release structure:**
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

**Auto-download at runtime:**
The `ai` package contains `NativeBinaryManager`, which:
1. Determines the platform
2. Looks for the library in the system
3. If not found — downloads from GitHub Releases
4. Verifies SHA-256
5. Caches locally

### 9.3. Documentation

Published via GitHub Pages.  
Repository: `MADEVAL/ferry-ai-docs` (or `gh-pages` branch of the main repo).

---

## 10. REPOSITORY SECURITY

### 10.1. `.github/SECURITY.md`

```markdown
# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.x     | :white_check_mark: |
| < 1.0   | :x:               |

## Reporting a Vulnerability

Please report security vulnerabilities to **security@php-ai.dev**.

Do NOT create public GitHub issues for security vulnerabilities.

We will respond within 48 hours and publish a fix as soon as possible.

## Security Model

- Models are verified via SHA-256 and Ed25519 signatures
- Native code is isolated via FFI sandboxing
- All network requests use HTTPS
- File system access is restricted to `model_cache` directory
```

### 10.2. `.github/CODE_OF_CONDUCT.md`

Standard Contributor Covenant.

### 10.3. `.github/CONTRIBUTING.md`

```markdown
# Contributing to FerryAI

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

## 11. FULL LIST OF DEV DEPENDENCIES

| Package | Purpose | Required |
|---|---|---|
| `phpunit/phpunit` | Unit tests | Yes |
| `pestphp/pest` | Alternative test framework | No |
| `pestphp/pest-plugin-parallel` | Parallel tests | No |
| `phpstan/phpstan` | Static analysis (level 8) | Yes |
| `phpstan/phpstan-strict-rules` | PHPStan strict rules | Yes |
| `phpstan/phpstan-deprecation-rules` | Deprecation detection | Yes |
| `vimeo/psalm` | Static analysis (level 3) | Yes |
| `squizlabs/php_codesniffer` | Alternative linter | No |
| `php-cs-fixer/shim` | Auto-fix code style | Yes |
| `infection/infection` | Mutation testing | Recommended |
| `symplify/monorepo-builder` | Monorepo management | Yes |
| `roave/security-advisories` | Block vulnerable packages | Yes |
| `ergebnis/composer-normalize` | Normalize composer.json | Recommended |
| `captainhook/captainhook` | Git hooks | Recommended |

---

## 12. CHECKLIST: NEW DEVELOPER

After cloning the repository, a new developer performs:

```bash
# 1. Clone
git clone https://github.com/MADEVAL/FerryAI.git php-inference
cd php-inference

# 2. Install dependencies
composer install

# 3. Environment check
php bin/ferry-ai check

# 4. Install git hooks (optional)
vendor/bin/captainhook install --force

# 5. Run tests
composer test

# 6. Run linters
composer lint

# 7. Full check
composer check
```

**If all steps are green — development can begin.**

---

> **This document is an integral part of the technical specification. The repository must be created in exact accordance with this specification.**
