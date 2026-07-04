<?php

declare(strict_types=1);

namespace FerryAI\Core\Enums;

enum BackendType: string
{
    case Onnx = 'onnx';
    case Llama = 'llama';
    case CpuNative = 'cpu_native';
}
