# Tensor

The `tensor` package provides FerryAI''s pure-PHP tensor and a factory. Backends return their own
`Tensor` implementations (`OnnxTensor`, `CpuNativeTensor`); the `Tensor` contract lives in
`packages/core/src/Contracts/Tensor.php` and extends `ArrayAccess`, `Countable`, `JsonSerializable`.

## Creating tensors

```php
use FerryAI\Tensor\ArrayTensor;
use FerryAI\Tensor\TensorFactory;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\ValueObjects\Shape;

$a = ArrayTensor::fromNested([[1, 2], [3, 4]], DType::Int32);   // from a nested PHP array
$z = (new TensorFactory())->zeros(new Shape([2, 3]));          // zeros / full / random
```

## Working with a tensor

```php
$a->shape();        // Shape([2, 2])
$a->dtype();        // DType::Int32
$a->device();       // Device::CPU
count($a);          // 4  (flat element count)
$a[0];              // flat element access (ArrayAccess<int, mixed>)
$a->reshape(new Shape([4]));
$a->transpose();            // reverse axes; explicit axes must be a permutation of 0..rank-1
$a->add($b); $a->sub($b); $a->mul($b);
$a->toArray();      // nested PHP array — marked EXPENSIVE (zero-copy is preferred)
```

## Fixed shape

A tensor has a fixed shape. In-place element assignment (`$t[$i] = $v`) is allowed, but appending
(`$t[] = $v`) and `unset($t[$i])` throw `\BadMethodCallException` because they would desync the
shape from the data.

## Implementations

- `ArrayTensor` — the working pure-PHP tensor (CPU fallback, no FFI).
- `OnnxTensor` (onnx-backend) — wraps ONNX Runtime output; arithmetic is done natively, so its
  math methods throw by design (see `docs/backends/onnx.md`).
- `CpuNativeTensor` (cpu-backend) — pure-PHP tensor used by the CPU backend.
- `BackedTensor` — a Phase-1 **stub** (a tensor over a native backend tensor); its ops throw
  `Not implemented in Phase 1.` and no path uses it. Tracked in `docs/DEBT_REPORT.md` §3.
