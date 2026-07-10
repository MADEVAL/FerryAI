# ferry-ai/inference-onnx-backend

ONNX Runtime inference backend for [FerryAI](https://github.com/MADEVAL/FerryAI), the inference-only
runtime for PHP 8.3+.

## Installation

```bash
composer require ferry-ai/inference-onnx-backend
```

## What's inside

- **`OnnxBackend`** — implements the `Backend` contract; loads `.onnx` models and runs the forward pass.
- **`OnnxModel`**, **`OnnxTensor`** — model handle and tensor wrapper.
- **`OnnxRuntimeInterface`** / **`NativeOnnxRuntime`** — the FFI seam: a mockable interface with a
  native production implementation. Only plain PHP values cross the seam.
- **`OnnxRuntimeFactory`** — builds a runtime for the selected execution provider / device.

GPU → CPU fallback is automatic and silent: when a non-CPU device fails to initialize, the backend
retries on CPU.

## Requirements

- PHP >= 8.3
- `ferry-ai/inference-core`
- `ankane/onnxruntime` (bundles the native ONNX Runtime library per platform)
- `ext-ffi` at runtime

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/backends/onnx.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/backends/onnx.md).
