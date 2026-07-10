# Tensor

The `tensor` package provides FerryAI's pure-PHP tensor and a factory. Backends return their
own `Tensor` implementations (`OnnxTensor`, `CpuNativeTensor`); the `Tensor` contract lives
in `packages/core/src/Contracts/Tensor.php` and extends `ArrayAccess`, `Countable`,
`JsonSerializable`.

## Contract

```php
interface Tensor extends ArrayAccess, Countable, JsonSerializable
{
    public function shape(): Shape;
    public function dtype(): DType;
    public function to(Device $device): self;
    public function device(): Device;
    public function toArray(): array;
    public function data(): mixed;
    public function add(self $other): self;
    public function sub(self $other): self;
    public function mul(self $other): self;
    public function matmul(self $other): self;
    public function transpose(?array $axes = null): self;
    public function reshape(Shape $newShape): self;
    public function slice(array $slices): self;
}
```

(Plus `__clone`, `jsonSerialize`, `__serialize`, `__unserialize`. Full signatures:
[INTERFACE_CONTRACTS.md](INTERFACE_CONTRACTS.md).)

A tensor has a **fixed shape**. Element assignment (`$t[$i] = $v`) is allowed; appending
(`$t[] = $v`) and `unset($t[$i])` throw `\BadMethodCallException` because they break the
shape→data invariant.

`toArray()` is marked as potentially expensive — backends prefer zero-copy access via
FFI where possible.

## Creating tensors

```php
use FerryAI\Tensor\ArrayTensor;
use FerryAI\Tensor\TensorFactory;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\ValueObjects\Shape;

// From nested PHP arrays
$a = ArrayTensor::fromNested([[1, 2], [3, 4]], DType::Int32);
$b = ArrayTensor::fromNested([5, 6, 7, 8], DType::Float32);

// Factory methods
$factory = new TensorFactory();
$z = $factory->zeros(new Shape([2, 3]));       // all 0.0
$o = $factory->ones(new Shape([3, 1]));        // all 1.0
$r = $factory->full(new Shape([4]), 3.14);    // scalar fill
$rand = $factory->random(new Shape([10]));     // uniform [0,1)
```

## Working with a tensor

```php
$a->shape();        // Shape([2, 2])
$a->dtype();        // DType::Int32
$a->device();       // Device::CPU
count($a);          // 4  (flat element count)

$a[0];              // 1  (flat element access)
$a[1] = 99;         // element assignment (in-place)

$a->reshape(new Shape([4]));       // (2,2) → (4,) — shape must match element count
$a->transpose();                   // transpose last two dims
$a->transpose([1, 0]);            // explicit perm, must be a permutation of 0..rank-1

$a->add($b);       // element-wise addition
$a->sub($b);       // element-wise subtraction
$a->mul($b);       // element-wise multiplication

$a->toArray();     // nested PHP array — EXPENSIVE
json_encode($a);   // JSON via JsonSerializable
```

## Implementations

| Class | Package | Purpose |
|-------|---------|---------|
| `ArrayTensor` | tensor | Primary pure-PHP tensor for CPU fallback. Full math support. |
| `OnnxTensor` | onnx-backend | Wraps ONNX Runtime output (ONNXRuntime\OrtValue). Math ops throw — ONNX does the math natively. |
| `CpuNativeTensor` | cpu-backend | Pure-PHP tensor used by CPU backend. Full math support. |

## Shape

```php
use FerryAI\Core\ValueObjects\Shape;

$s = new Shape([2, 3, 4]);
$s->dimensions;      // [2, 3, 4]  (the only property)
$s->rank();          // 3
$s->size();          // 24  (2 × 3 × 4; -1 if any axis is dynamic)
$s->dimension(1);    // 3   (size along an axis)
$s->isStatic();      // true (no -1 dynamic axes)
(string) $s;         // "2,3,4"  (Stringable)
Shape::fromString('1,3,224,224');   // parse from string
```

A dimension of `-1` marks a dynamic axis; any other negative value throws
`ValidationException`.
