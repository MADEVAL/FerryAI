# ONNX backend

`FerryAI\OnnxBackend\OnnxBackend` runs any `.onnx` model through ONNX Runtime via
[`ankane/onnxruntime`](https://github.com/ankane/onnxruntime-php) (FFI). This is the
default backend for embeddings and classification.

## What you need

- Composer dep `ankane/onnxruntime` (auto-installed with `ferry-ai/php-inference`) + `ext-ffi`.
- The **ONNX Runtime shared library** (`onnxruntime.{dll,so,dylib}` +
  `onnxruntime_providers_shared`). Download the platform-appropriate build from
  [microsoft/onnxruntime releases](https://github.com/microsoft/onnxruntime/releases) and
  extract into `vendor/ankane/onnxruntime/lib/…/lib/`.
  - On Linux: `php -r 'OnnxRuntime\Vendor::check();'` will auto-download the CPU build.
- A **model**: `.onnx` file (+ `tokenizer.json` for text models). E.g.
  [`sentence-transformers/all-MiniLM-L6-v2`](https://huggingface.co/sentence-transformers/all-MiniLM-L6-v2)
  exported to ONNX with `optimum-cli export onnx`.

## Availability check

```php
$onnx = new FerryAI\OnnxBackend\OnnxBackend();
echo $onnx->isAvailable() ? $onnx->version() : 'not available';   // e.g. "1.27.0"
```

## Loading & running

```php
$onnx = new FerryAI\OnnxBackend\OnnxBackend();
$model = $onnx->load('/path/to/model.onnx');

// Direct inference
$out = $model->run([
    'input_ids'      => [[101, 100, 102]],
    'attention_mask' => [[1, 1, 1]],
]);
```

Typically you use it through the facade — see [embedding](../embedding.md).

## Execution providers

`availableDevices()` reflects the providers reported by the runtime:

```php
$devices = $onnx->availableDevices();   // [Device::CPU]
```

Device → provider mapping is handled entirely by `OnnxTypeMapper` (there is no per-provider
PHP class beyond `CpuProvider`). `OnnxTypeMapper::providerNamesForDevice()` returns the ordered
ONNX Runtime provider strings requested for a target device, always with a `CPUExecutionProvider`
fallback:

| `Device` | ONNX Runtime providers (in order) | Requires |
|----------|-----------------------------------|----------|
| `CPU` | `CPUExecutionProvider` | CPU build (default) |
| `CUDA` | `CUDAExecutionProvider`, `CPUExecutionProvider` | GPU build + CUDA Toolkit + cuDNN |
| `METAL` | `CoreMLExecutionProvider`, `CPUExecutionProvider` | macOS (built into ONNX Runtime macOS) |
| `DIRECTML` | `DmlExecutionProvider`, `CPUExecutionProvider` | Windows GPU build |
| `ROCM` | `ROCMExecutionProvider`, `CPUExecutionProvider` | AMD ROCm stack |
| `OPENVINO` | `OpenVINOExecutionProvider`, `CPUExecutionProvider` | Intel OpenVINO toolkit |

`OnnxTypeMapper::providerToDevice()` performs the reverse lookup (e.g. both
`CUDAExecutionProvider` and `TensorrtExecutionProvider` map back to `Device::CUDA`), and
`OnnxTypeMapper::toDType()` maps ONNX element types (`tensor(float)`, `int64`, …) to `DType`.

## GPU (CUDA)

For GPU inference you need a **GPU build** of ONNX Runtime plus NVIDIA dependencies:

**Windows:**
1. Download the GPU zip from [ONNX Runtime releases](https://github.com/microsoft/onnxruntime/releases).
2. Copy `onnxruntime.dll` + `onnxruntime_providers_cuda.dll` + `onnxruntime_providers_shared.dll`
   into `vendor/ankane/onnxruntime/lib/onnxruntime-win-x64-*/lib/`.
3. Install [CUDA Toolkit](https://developer.nvidia.com/cuda-downloads) and
   [cuDNN](https://developer.nvidia.com/cudnn); copy cuDNN DLLs to the vendor lib dir.
4. `curand64_10.dll` and `cufft64_11.dll` can be extracted from pip wheels:
   ```
   pip download nvidia-curand-cu12 nvidia-cufft-cu12 --no-deps
   ```
    See the [GPU setup](../../docs/DOCUMENTATION.md#onnx-gpu-on-windows) for exact steps.

**Linux:**
Copy the GPU `.so` files into the vendor lib dir and point `LD_LIBRARY_PATH` at it.
The CUDA runtime math libraries (`libcurand`, `libcufft`, `libcudnn`) can be extracted
from `.deb` packages without root — see the [GPU setup](../../docs/DOCUMENTATION.md#onnx-gpu-on-linux).

**Verify:**
```bash
# Windows
php -r "require 'vendor/autoload.php'; var_dump((new FerryAI\OnnxBackend\OnnxRuntimeFactory())->availableProviders());"
# Expected: TensorrtExecutionProvider, CUDAExecutionProvider, CPUExecutionProvider

# Linux
LD_LIBRARY_PATH=vendor/ankane/onnxruntime/lib/onnxruntime-linux-x64-*/lib:/usr/local/cuda/lib64 php \
  -r "require 'vendor/autoload.php'; \$b=new FerryAI\OnnxBackend\OnnxBackend(); echo implode(',',array_map(fn(\$d)=>\$d->value,\$b->availableDevices()));"
# Expected: cuda,cpu
```

## Architecture

The backend has three layers:

| Class | Purpose |
|-------|---------|
| `OnnxRuntimeFactory` | Creates the FFI runtime handle; detects available providers |
| `OnnxBackend` | Implements `Backend` — `isAvailable()`, `version()`, `load()`, `availableDevices()` |
| `OnnxModel` | Implements `Model` — `run()`, `metadata()`, wraps `OnnxTensor` |

The runtime FFI layer (`Runtime\NativeOnnxRuntime`, `Runtime\NativeOnnxSession`) is the
untyped FFI boundary and is covered by the integration suite, not static analysis.

## Notes

- `OnnxTensor` wraps native ONNX Runtime output (`ONNXRuntime\OrtValue`). Its math methods
  (`add`, `sub`, `mul`) throw — do tensor arithmetic with `ArrayTensor` instead.
- Model output is extracted zero-copy where possible; `toArray()` forces a full copy.
- The model is pooled via `ModelPool` across calls (second inference is instant).
