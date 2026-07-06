# CPU backend (RubixML)

`FerryAI\CpuBackend\CpuNativeBackend` is the always-available pure-PHP fallback. It powers
`AI::predict()` for classic ML models and provides a pure-PHP tensor (`CpuNativeTensor`).
No native libraries required; when [RubixML](https://rubixml.com) is present it runs real
`.rbm` estimators for tabular prediction and classification.

## What you need

- **Nothing** for tensor math — `CpuNativeTensor` is pure PHP (add, sub, mul, matmul,
  transpose, reshape, slice).
- For real `.rbm` predictions: install `rubix/ml` + `rubix/tensor` (isolated is fine) and
  point PHP at its autoloader:
  ```bash
  composer require rubix/ml rubix/tensor
  # Or isolated:
  composer create-project rubix/ml isolated-rubix
  export FERRY_AI_RUBIXML_AUTOLOAD=/path/to/isolated-rubix/vendor/autoload.php
  ```

## Availability

```php
$cpu = new FerryAI\CpuBackend\CpuNativeBackend();
echo $cpu->isAvailable() ? 'ready' : 'unavailable';   // Always true — pure PHP
```

Tensor math always works. Prediction throws only if `.rbm` is needed and RubixML isn't installed.

## Prediction through the facade

```php
AI::config([
    'backend'  => 'cpu',
    'backends' => ['predict' => ['model_path' => '/path/to/model.rbm']],
]);

// Single prediction
$label = AI::predict(['age' => 30, 'income' => 50000, 'credit_score' => 720]);
// → 'approved'

// Probability estimates
$probabilities = AI::predict(['age' => 30, 'income' => 50000]);
// via CpuNativeModel::run() with probability output
```

`AI::predict()` wraps the feature map into a single sample and calls the estimator. Without
RubixML installed, `CpuNativeModel::run()` throws `BackendNotAvailableException` with
actionable guidance to install `rubix/ml` via Composer.

## Pure-PHP tensor

`CpuNativeTensor` implements the full `Tensor` contract with pure PHP math:

```php
use FerryAI\CpuBackend\CpuNativeTensor;
use FerryAI\Core\Enums\DType;

$a = CpuNativeTensor::fromNested([[1, 2], [3, 4]], DType::Float32);
$b = CpuNativeTensor::fromNested([[5, 6], [7, 8]], DType::Float32);

$c = $a->add($b);                    // [[6, 8], [10, 12]]
$d = $a->sub($b);                    // [[-4, -4], [-4, -4]]
$e = $a->mul($b);                    // [[5, 12], [21, 32]]

// Matrix multiplication: (2,2) × (2,2) → (2,2)
$f = $a->matmul($b);                 // [[19, 22], [43, 50]]

// Reshape and transpose
$g = $a->reshape(new Shape([4]));   // [1, 2, 3, 4]
$h = $a->transpose();                // [[1, 3], [2, 4]]

// Slice
$i = $a->slice(new Shape([1, 1]), new Shape([1, 1]));   // [[4]] (slice from [1,1])
```

All operations are validated — shape mismatches throw `ShapeMismatchException`. The tensor
has a fixed shape: appending via `$t[] =` or `unset($t[$i])` throws `\BadMethodCallException`.

## Components

| Class | Purpose |
|-------|---------|
| `CpuNativeBackend` | Implements `Backend` — `isAvailable()`, `load()`, `version()` |
| `CpuNativeModel` | Implements `Model` — `run()`, `metadata()`; delegates to `RubixMLAdapter` |
| `RubixMLAdapter` | Real `predict`/`proba` bridge to RubixML; `loadModel()` deserializes `.rbm` |
| `CpuNativeTensor` | Pure-PHP tensor with full arithmetic |

`RubixMLAdapter` handles the serialization-based model loading (RubixML stores estimators
as PHP serialized objects) and delegates `predict()`/`proba()` to the loaded estimator.

## Isolated RubixML setup

RubixML depends on `amphp/parallel ^1`, which conflicts with Psalm's `amphp`
dependency. FerryAI therefore supports **isolated** installations:

1. Install RubixML in a **separate directory** (not in FerryAI's vendor):
   ```bash
   cd /opt/rubixml
   composer require rubix/ml rubix/tensor
   ```

2. Set the autoloader path:
   ```bash
   export FERRY_AI_RUBIXML_AUTOLOAD=/opt/rubixml/vendor/autoload.php
   ```
   Or in PHP: `putenv('FERRY_AI_RUBIXML_AUTOLOAD=/opt/rubixml/vendor/autoload.php');`

3. The `RubixMLAdapter` conditionally loads RubixML classes through this autoloader,
   keeping FerryAI's own vendor directory clean.

See [`examples/24-rubix-cpu.php`](../../examples/24-rubix-cpu.php).

## Notes

- `CpuNativeBackend` is always the fallback — if ONNX and llama are unavailable, the cpu
  backend still provides tensor math.
- RubixML is verified end to end on Windows + Linux via an isolated subprocess harness.
  See `docs/DEBT_REPORT.md`.
- The `.rbm` model path must point at a serialized RubixML `Estimator` object, not a
  directory.
