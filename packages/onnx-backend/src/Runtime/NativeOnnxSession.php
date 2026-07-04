<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Runtime;

use OnnxRuntime\Model as OrtModel;

/**
 * Native session handle: wraps an ankane/onnxruntime {@see OrtModel}.
 *
 * Excluded from static analysis (holds an untyped third-party FFI object).
 */
final class NativeOnnxSession implements OnnxSession
{
    public function __construct(private readonly OrtModel $model) {}

    public function model(): OrtModel
    {
        return $this->model;
    }
}
