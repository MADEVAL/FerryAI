# ONNX backend

`FerryAI\OnnxBackend\OnnxBackend` runs any `.onnx` model through ONNX Runtime via
[`ankane/onnxruntime`](https://github.com/ankane/onnxruntime-php) (FFI). Used for embeddings and
classification.

## What you need

- Composer dep `ankane/onnxruntime` (installed automatically) + `ext-ffi`.
- The **ONNX Runtime shared library** (`onnxruntime.{dll,so,dylib}` + `onnxruntime_providers_shared`).
  Download from [microsoft/onnxruntime releases](https://github.com/microsoft/onnxruntime/releases)
  and place under `vendor/ankane/onnxruntime/lib/…/lib/`.
- A model: `.onnx` file (+ `tokenizer.json` for text models). E.g.
  [`sentence-transformers/all-MiniLM-L6-v2`](https://huggingface.co/sentence-transformers/all-MiniLM-L6-v2)
  exported to ONNX.

## Availability check

```php
$onnx = new FerryAI\OnnxBackend\OnnxBackend();
echo $onnx->isAvailable() ? $onnx->version() : 'not available';   // e.g. 1.27.0
```

## Loading & running

```php
$model = $onnx->load('/path/to/model.onnx');       // or via AI::embed()/classify()
$out   = $model->run(['input_ids' => [[101, 100, 102]], 'attention_mask' => [[1,1,1]]]);
```

Typically you use it through the facade — see [embedding](../embedding.md).

## Execution providers / GPU

`availableDevices()` reflects the providers reported by the runtime. The CPU build exposes
`CPUExecutionProvider` (+ `AzureExecutionProvider`). For GPU you need a **GPU build** of ONNX
Runtime plus the NVIDIA CUDA Toolkit / cuDNN (and TensorRT for the TRT provider):

- ONNX Runtime GPU package: microsoft/onnxruntime releases (`*-gpu-*`).
- CUDA: <https://developer.nvidia.com/cuda-downloads> · cuDNN: <https://developer.nvidia.com/cudnn>.

Provider selection maps from the configured `device` via `OnnxTypeMapper`.

> Status: CPU inference is verified end to end (all-MiniLM-L6-v2, 384-d). The ONNX **GPU** path is
> not yet exercised in this repo — see `docs/DEBT_REPORT.md` §14.

## Notes

- The FFI runtime files (`OnnxRuntimeFactory`, `NativeOnnxRuntime`, `NativeOnnxSession`) are the
  untyped FFI boundary and are covered by the integration suite, not static analysis.
- `OnnxTensor` wraps native output; tensor math lives in the `tensor` package.
