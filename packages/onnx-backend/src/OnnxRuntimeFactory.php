<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend;

use FerryAI\Core\Enums\GraphOptimizationLevel;
use OnnxRuntime\FFI as OrtFFI;
use OnnxRuntime\GraphOptimizationLevel as OrtGraphOptimizationLevel;
use OnnxRuntime\Model as OrtModel;

/**
 * The single point of coupling to ankane/onnxruntime (the native ONNX Runtime FFI binding).
 *
 * This class is intentionally excluded from static analysis: it bridges to an untyped
 * third-party FFI library and is exercised by the integration test suite (real runtime).
 */
final class OnnxRuntimeFactory
{
    /**
     * Whether the native ONNX Runtime shared library can be loaded.
     */
    public function isAvailable(): bool
    {
        try {
            OrtFFI::instance();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Native engine version string, e.g. "1.20.0".
     */
    public function version(): string
    {
        return (string) OrtFFI::libVersion();
    }

    /**
     * Execution provider names available in the current environment.
     *
     * @return list<string>
     */
    public function availableProviders(): array
    {
        $ffi = OrtFFI::instance();
        $api = OrtFFI::api();

        $outPtr = $ffi->new('char**');
        $lengthPtr = $ffi->new('int');
        ($api->GetAvailableProviders)(\FFI::addr($outPtr), \FFI::addr($lengthPtr));

        $length = $lengthPtr->cdata;
        $providers = [];

        for ($i = 0; $i < $length; ++$i) {
            $providers[] = \FFI::string($outPtr[$i]);
        }

        ($api->ReleaseAvailableProviders)($outPtr, $length);

        return $providers;
    }

    /**
     * Creates an ankane ONNX Runtime model for the given local file.
     *
     * @param list<string> $providerNames
     */
    public function createModel(
        string $path,
        array $providerNames,
        GraphOptimizationLevel $optimization,
    ): OrtModel {
        return new OrtModel(
            $path,
            graphOptimizationLevel: $this->mapOptimization($optimization),
            providers: $providerNames,
        );
    }

    private function mapOptimization(GraphOptimizationLevel $level): OrtGraphOptimizationLevel
    {
        return match ($level) {
            GraphOptimizationLevel::DISABLE_ALL => OrtGraphOptimizationLevel::None,
            GraphOptimizationLevel::BASIC => OrtGraphOptimizationLevel::Basic,
            GraphOptimizationLevel::EXTENDED => OrtGraphOptimizationLevel::Extended,
            GraphOptimizationLevel::ALL => OrtGraphOptimizationLevel::All,
        };
    }
}
