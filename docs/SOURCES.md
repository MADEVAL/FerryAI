# FerryAI — Источники (проверка актуальности стека)

> Назначение: канонический список внешних источников для верификации версий, API и статусов,
> на которые опирается документация FerryAI. При сомнениях в актуальности — сверяться отсюда.
> Дата последней сверки: 2026-07-04 (частичная — см. «Журнал сверки» ниже).

## Журнал сверки (2026-07-04)

Подтверждено:
- **PHP 8.5** — актуальный патч 8.5.8; 8.6 ещё не вышел. Фичи 8.5 в доках верны (Pipe `|>`, Clone как функция с `$withProperties`, `#[\NoDiscard]`, Closures in const, `#[\DelayedTargetValidation]`, `#[\Override]` для свойств, Static Asymmetric Visibility, Final Property Promotion, Backtraces). Property Hooks и обычная Asymmetric Visibility — 8.4. Исправлено: «`#[\Deprecated]` на traits» → «атрибуты/`#[\Deprecated]` на константах».
- **ONNX Runtime** — latest v1.27.0 (ORT_API_VERSION 25); CUDA 13 актуальна, CUDA 12 deprecated. Минимум ≥1.18 в доках валиден.
- **phpmlkit/onnxruntime** — существует (GitHub ⭐8, MIT, FFI-first, zero-copy OrtValue, sequences/maps, NDArray, runtimes cpu/cuda12/cuda13). Требует **PHP 8.1+**. Провайдеры: **CPU, CUDA, CoreML, TensorRT** (НЕ DirectML/OpenVINO/ROCm). **Стабильных релизов пока нет** → `^1.0` рискован. Ставит `codewithkyrian/huggingface`.
- **ankane/onnxruntime** (composer-имя без `-php`) — v0.3.4 (2026-06), PHP ≥8.2, ⭐148. Исправлено имя+версия.
- **rubix/tensor** — 3.0.5 (2024), PHP ≥7.4, ⭐279. Исправлено `^2.0` → `^3.0`.
- **codewithkyrian/huggingface** — composer-имя (репо `huggingface-php`); версию сверить. Исправлено имя в require.
- **Laravel** — latest v13.18.1 (мажор 13; 12.x активна), PHP ^8.3. Исправлено `^11.0` → `^12.0 || ^13.0`.
- **Symfony** — latest v8.1.1 (мажор 8; 7.4 LTS), PHP ≥8.4. Исправлено `^7.0` → `^7.4 || ^8.0`.
- **PHPStan** — 2.2.4, `^2.0` ✓ верно (level 8 актуален).
- **Psalm** — стабильная 6.16.1 (7.0 в бете), `^6.0` ✓ верно; поддерживает PHP 8.5.
- **Dev-инструменты (сверено):** phpunit 13.2 → bump `^11`→`^13` (+ schema 13.0); pest 4.7 → `^3`→`^4` (+ parallel `^4`); infection 0.34 → `^0.29`→`^0.34`; php_codesniffer 4.0 → `^3.10`→`^4.0`; monorepo-builder 12.7 → `^11`→`^12`. Верны без правок: php-cs-fixer/shim `^3`, phpstan-strict/deprecation `^2`, composer-normalize `^2`, captainhook `^5`, roave dev-latest.
- **rubix/ml** — 2.5.3, `^2.0` ✓ верно.
- **codewithkyrian/huggingface** — существует, 1.0.0, PHP ^8.2, `^1.0` ✓ (composer-имя без `-php`).
- **HF-модели** — размерности подтверждены: MiniLM-L6-v2=384, mpnet-base-v2=768, multilingual-e5-small=384, bge-small-en-v1.5=384. ✓
- **sqlite-vec** — актуальный тег v0.1.10-alpha (pre-1.0); «≥0.1» ✓. Верифицированный бинарник на Windows: vec0.dll v0.1.10-alpha. На Linux: sqlite-vec-0.1.9-loadable-linux-x86_64 (vec0.so v0.1.9).
- **llama.cpp C API** — приведено к новым именам: `llama_model_load_from_file`, `llama_init_from_model`, `llama_model_free`, `llama_free`, `llama_vocab_n_tokens`, `llama_model_n_embd`. Старые (`llama_new_context_with_model`, `llama_n_vocab`, `llama_n_embd`, `llama_load_model_from_file`, `llama_free_model`) — DEPRECATED, заменены в PHASE_2.

Сверка стека завершена. Открытый архитектурный вопрос: DirectML/OpenVINO/ROCm-провайдеры помечены «планируемыми» (phpmlkit их не отдаёт). phpmlkit пока без стабильных релизов — `^1.0` держать под контролем.

---

## 1. PHP язык и рантайм

- PHP 8.5 (релиз): https://www.php.net/releases/8.5/
- PHP 8.5 (миграция): https://www.php.net/manual/en/migration85.php
- PHP 8.4 (миграция, Property Hooks и пр.): https://www.php.net/manual/en/migration84.php
- PHP RFC Index: https://wiki.php.net/rfc
- PHP Internals (обсуждения, 8.6): https://externals.io/

### Конкретные RFC
- Pipe Operator: https://wiki.php.net/rfc/pipe-operator
- Clone With: https://wiki.php.net/rfc/clone_with
- NoDiscard Attribute: https://wiki.php.net/rfc/nodiscard_attribute
- Asymmetric Visibility: https://wiki.php.net/rfc/asymmetric-visibility
- Asymmetric Visibility v2: https://wiki.php.net/rfc/asymmetric-visibility-v2
- Final Property Promotion: https://wiki.php.net/rfc/final_promotion
- Override Attribute: https://wiki.php.net/rfc/marking_overriden_methods
- Delayed Target Validation: https://wiki.php.net/rfc/delayedtargetvalidation
- Deprecated Attribute for Traits: https://wiki.php.net/rfc/deprecated_attr
- Closures in Constant Expressions: https://wiki.php.net/rfc/closures_in_const_expr
- Property Hooks: https://wiki.php.net/rfc/property-hooks

---

## 2. Нативные движки

### llama.cpp
- GitHub: https://github.com/ggml-org/llama.cpp
- Releases: https://github.com/ggml-org/llama.cpp/releases
- GGUF: https://github.com/ggml-org/llama.cpp/blob/master/docs/gguf.md
- Build: https://github.com/ggml-org/llama.cpp/blob/master/docs/build.md
- API Header (llama.h): https://github.com/ggml-org/llama.cpp/blob/master/include/llama.h

### ONNX Runtime
- Сайт: https://onnxruntime.ai/
- Документация: https://onnxruntime.ai/docs/
- GitHub: https://github.com/microsoft/onnxruntime
- Releases: https://github.com/microsoft/onnxruntime/releases
- C API: https://onnxruntime.ai/docs/api/c/
- Execution Providers: https://onnxruntime.ai/docs/execution-providers/
  - CUDA: https://onnxruntime.ai/docs/execution-providers/CUDA-ExecutionProvider.html
  - TensorRT: https://onnxruntime.ai/docs/execution-providers/TensorRT-ExecutionProvider.html
  - CoreML: https://onnxruntime.ai/docs/execution-providers/CoreML-ExecutionProvider.html
  - DirectML: https://onnxruntime.ai/docs/execution-providers/DirectML-ExecutionProvider.html
  - OpenVINO: https://onnxruntime.ai/docs/execution-providers/OpenVINO-ExecutionProvider.html
  - ROCm: https://onnxruntime.ai/docs/execution-providers/ROCm-ExecutionProvider.html

### sqlite-vec
- GitHub: https://github.com/asg017/sqlite-vec
- Releases (vec0 loadable extension binaries): https://github.com/asg017/sqlite-vec/releases
- Docs: https://github.com/asg017/sqlite-vec/tree/main/docs

### PostgreSQL + pgvector
- PostgreSQL download: https://www.postgresql.org/download/
- pgvector (extension): https://github.com/pgvector/pgvector
- pgvector releases: https://github.com/pgvector/pgvector/releases

### NVIDIA GPU stack (for CUDA/TensorRT execution)
- CUDA Toolkit: https://developer.nvidia.com/cuda-downloads
- cuDNN: https://developer.nvidia.com/cudnn
- TensorRT: https://developer.nvidia.com/tensorrt

### tokenizers-cpp
- GitHub: https://github.com/mlc-ai/tokenizers-cpp

### Прочее
- SQLite: https://sqlite.org/ · https://sqlite.org/changes.html
- OpenBLAS: https://github.com/OpenMathLib/OpenBLAS
- LAPACK: https://github.com/Reference-LAPACK/lapack

---

## 3. PHP-зависимости

- phpmlkit/onnxruntime: https://packagist.org/packages/phpmlkit/onnxruntime · https://github.com/phpmlkit/onnxruntime
- ankane/onnxruntime: https://packagist.org/packages/ankane/onnxruntime · https://github.com/ankane/onnxruntime-php
- rubix/ml: https://packagist.org/packages/rubix/ml · https://github.com/RubixML/ML
- rubix/tensor: https://packagist.org/packages/rubix/tensor · https://github.com/RubixML/Tensor
- codewithkyrian/huggingface: https://packagist.org/packages/codewithkyrian/huggingface · https://github.com/codewithkyrian/huggingface-php
- dstogov/php-tensorflow (справочно): https://github.com/dstogov/php-tensorflow
- php-opencv (справочно): https://github.com/php-opencv/php-opencv
- llama.php (справочно): https://github.com/CodeWithKyrian/llama.php

---

## 4. Dev-инструменты

- PHPUnit: https://packagist.org/packages/phpunit/phpunit · https://github.com/sebastianbergmann/phpunit
- PHPStan: https://packagist.org/packages/phpstan/phpstan · https://github.com/phpstan/phpstan
- phpstan-strict-rules: https://packagist.org/packages/phpstan/phpstan-strict-rules · https://github.com/phpstan/phpstan-strict-rules
- phpstan-deprecation-rules: https://packagist.org/packages/phpstan/phpstan-deprecation-rules · https://github.com/phpstan/phpstan-deprecation-rules
- Psalm: https://packagist.org/packages/vimeo/psalm · https://github.com/vimeo/psalm
- PHP CS Fixer: https://packagist.org/packages/php-cs-fixer/shim · https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
- PHP_CodeSniffer: https://packagist.org/packages/squizlabs/php_codesniffer · https://github.com/PHPCSStandards/PHP_CodeSniffer
- Infection: https://packagist.org/packages/infection/infection · https://github.com/infection/infection
- Pest: https://packagist.org/packages/pestphp/pest · https://github.com/pestphp/pest
  - Parallel Plugin: https://github.com/pestphp/pest-plugin-parallel
- Monorepo Builder: https://packagist.org/packages/symplify/monorepo-builder · https://github.com/symplify/monorepo-builder
- Roave Security Advisories: https://packagist.org/packages/roave/security-advisories · https://github.com/Roave/SecurityAdvisories
- Composer Normalize: https://packagist.org/packages/ergebnis/composer-normalize · https://github.com/ergebnis/composer-normalize
- CaptainHook: https://packagist.org/packages/captainhook/captainhook · https://github.com/captainhookphp/captainhook
- PER Coding Style: https://www.php-fig.org/per/coding-style/

---

## 5. Фреймворки

- Laravel: https://packagist.org/packages/illuminate/support · https://github.com/laravel/framework · https://github.com/laravel/framework/releases
- Symfony:
  - http-kernel: https://packagist.org/packages/symfony/http-kernel
  - config: https://packagist.org/packages/symfony/config
  - dependency-injection: https://packagist.org/packages/symfony/dependency-injection
  - GitHub: https://github.com/symfony/symfony

---

## 6. Hugging Face модели (эмбеддинги)

- all-MiniLM-L6-v2 (384): https://huggingface.co/sentence-transformers/all-MiniLM-L6-v2
- all-mpnet-base-v2 (768): https://huggingface.co/sentence-transformers/all-mpnet-base-v2
- multilingual-e5-small (384): https://huggingface.co/intfloat/multilingual-e5-small
- bge-small-en-v1.5 (384): https://huggingface.co/BAAI/bge-small-en-v1.5
