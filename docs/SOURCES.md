# FerryAI — Sources

> Purpose: canonical list of external sources for verifying versions, APIs and statuses,
> which the FerryAI documentation relies on.

---

## 1. PHP Language and Runtime

- PHP 8.3 (release): https://www.php.net/releases/8.3/
- PHP 8.3 (migration): https://www.php.net/manual/en/migration83.php
- PHP 8.4 (migration): https://www.php.net/manual/en/migration84.php
- PHP 8.5 (release): https://www.php.net/releases/8.5/
- PHP RFC Index: https://wiki.php.net/rfc
- PHP Internals (discussions, 8.6): https://externals.io/

### Specific RFCs
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

## 2. Native Engines

### llama.cpp
- GitHub: https://github.com/ggml-org/llama.cpp
- Releases: https://github.com/ggml-org/llama.cpp/releases
- GGUF: https://github.com/ggml-org/llama.cpp/blob/master/docs/gguf.md
- Build: https://github.com/ggml-org/llama.cpp/blob/master/docs/build.md
- API Header (llama.h): https://github.com/ggml-org/llama.cpp/blob/master/include/llama.h

### ONNX Runtime
- Website: https://onnxruntime.ai/
- Documentation: https://onnxruntime.ai/docs/
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

### Other
- SQLite: https://sqlite.org/ · https://sqlite.org/changes.html
- OpenBLAS: https://github.com/OpenMathLib/OpenBLAS
- LAPACK: https://github.com/Reference-LAPACK/lapack

---

## 3. PHP Dependencies

- ankane/onnxruntime: https://packagist.org/packages/ankane/onnxruntime · https://github.com/ankane/onnxruntime-php
- rubix/ml: https://packagist.org/packages/rubix/ml · https://github.com/RubixML/ML
- rubix/tensor: https://packagist.org/packages/rubix/tensor · https://github.com/RubixML/Tensor
- dstogov/php-tensorflow (reference): https://github.com/dstogov/php-tensorflow
- php-opencv (reference): https://github.com/php-opencv/php-opencv
- llama.php (reference): https://github.com/CodeWithKyrian/llama.php
- codewithkyrian/huggingface-php (reference): https://github.com/codewithkyrian/huggingface-php

---

## 4. Dev Tools

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

## 5. Frameworks

- Laravel: https://packagist.org/packages/illuminate/support · https://github.com/laravel/framework · https://github.com/laravel/framework/releases
- Symfony:
  - http-kernel: https://packagist.org/packages/symfony/http-kernel
  - config: https://packagist.org/packages/symfony/config
  - dependency-injection: https://packagist.org/packages/symfony/dependency-injection
  - GitHub: https://github.com/symfony/symfony

---

## 6. Hugging Face Models (embeddings)

- all-MiniLM-L6-v2 (384): https://huggingface.co/sentence-transformers/all-MiniLM-L6-v2
- all-mpnet-base-v2 (768): https://huggingface.co/sentence-transformers/all-mpnet-base-v2
- multilingual-e5-small (384): https://huggingface.co/intfloat/multilingual-e5-small
- bge-small-en-v1.5 (384): https://huggingface.co/BAAI/bge-small-en-v1.5
