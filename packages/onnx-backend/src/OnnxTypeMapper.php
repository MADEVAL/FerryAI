<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;

/**
 * Maps between ONNX Runtime type/provider strings and FerryAI enums.
 */
final class OnnxTypeMapper
{
    /**
     * Maps an ONNX element type to a FerryAI {@see DType}.
     *
     * Accepts both raw element names ("float", "int64") and wrapped forms ("tensor(float)").
     * Types without a direct FerryAI counterpart are mapped to the closest supported kind.
     */
    public static function toDType(string $onnxType): DType
    {
        $type = $onnxType;

        if (preg_match('/^tensor\((?<inner>.+)\)$/', $onnxType, $matches) === 1) {
            $type = $matches['inner'];
        }

        return match ($type) {
            'float', 'double' => DType::Float32,
            'float16', 'bfloat16' => DType::Float16,
            'int64', 'uint64' => DType::Int64,
            'string' => DType::String,
            default => DType::Int32,
        };
    }

    /**
     * Maps an ONNX Runtime execution provider name to a {@see Device}, or null when unknown.
     */
    public static function providerToDevice(string $provider): ?Device
    {
        return match ($provider) {
            'CPUExecutionProvider' => Device::CPU,
            'CUDAExecutionProvider', 'TensorrtExecutionProvider' => Device::CUDA,
            'ROCMExecutionProvider' => Device::ROCM,
            'CoreMLExecutionProvider' => Device::METAL,
            'DmlExecutionProvider' => Device::DIRECTML,
            'OpenVINOExecutionProvider' => Device::OPENVINO,
            default => null,
        };
    }

    /**
     * Ordered execution provider names to request for a target device (with CPU fallback).
     *
     * @return list<string>
     */
    public static function providerNamesForDevice(Device $device): array
    {
        return match ($device) {
            Device::CUDA => ['CUDAExecutionProvider', 'CPUExecutionProvider'],
            Device::METAL => ['CoreMLExecutionProvider', 'CPUExecutionProvider'],
            Device::DIRECTML => ['DmlExecutionProvider', 'CPUExecutionProvider'],
            Device::ROCM => ['ROCMExecutionProvider', 'CPUExecutionProvider'],
            Device::OPENVINO => ['OpenVINOExecutionProvider', 'CPUExecutionProvider'],
            default => ['CPUExecutionProvider'],
        };
    }
}
