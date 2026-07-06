# CPU backend (RubixML)

`FerryAI\CpuBackend\CpuNativeBackend` is the always-available pure-PHP fallback. It powers
`AI::predict()` for classic ML models and provides a pure-PHP tensor (`CpuNativeTensor`). It never
requires native libraries; when [RubixML](https://rubixml.com) is present it runs real `.rbm`
estimators.

## What you need

- Nothing for tensor math — `CpuNativeTensor` is pure PHP.
- For real predictions: install `rubix/ml` (isolated is fine) and point PHP at its autoloader via
  `FERRY_AI_RUBIXML_AUTOLOAD=/path/to/rubixml/vendor/autoload.php`.

## Availability

```php
$cpu = new FerryAI\CpuBackend\CpuNativeBackend();
echo $cpu->isAvailable() ? 'ready' : 'unavailable';   // always true (pure PHP)
```

## Prediction through the facade

```php
FerryAI\AI::config(['backends' => ['predict' => ['model_path' => '/path/to/model.rbm']]]);
$label = FerryAI\AI::predict(['age' => 30, 'income' => 50000]);
```

`AI::predict()` wraps the feature map into a single sample and calls the estimator. Without RubixML,
`CpuNativeModel::run()` throws `BackendNotAvailableException` with actionable guidance (see
`docs/DEBT_REPORT.md` §3).

## Components

- `CpuNativeBackend` / `CpuNativeModel` — `Backend`/`Model` over a serialized estimator.
- `RubixMLAdapter` — real `predict`/`proba`/`loadModel` bridge to RubixML.
- `CpuNativeTensor` — pure-PHP `Tensor` (matmul/transpose/reshape/elementwise). Fixed shape:
  appending via `[]` or `unset()` throws `\BadMethodCallException`.

> RubixML is verified end to end on Windows + Linux via an isolated subprocess harness (it uses
> amphp, which can collide in-process) — see `docs/DEBT_REPORT.md`.
