<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Format;

use FerryAI\Core\Exception\InvalidStateException;
use FerryAI\Core\ValueObjects\ModelMetadata;

final class OnnxInspector
{
    public static function inspect(string $path): ModelMetadata
    {
        $name = \pathinfo($path, PATHINFO_FILENAME);

        return new ModelMetadata(
            name: $name,
            version: 'unknown',
            author: 'unknown',
            license: 'unknown',
            tags: [],
            sizeBytes: \file_exists($path) ? (int) \filesize($path) : 0,
        );
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public static function inputs(string $path): array
    {
        throw new InvalidStateException(
            'OnnxInspector::inputs() is not yet implemented. '
            . 'Use a backend (OnnxBackend) to load the model and inspect its inputs at runtime.',
        );
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public static function outputs(string $path): array
    {
        throw new InvalidStateException(
            'OnnxInspector::outputs() is not yet implemented. '
            . 'Use a backend (OnnxBackend) to load the model and inspect its outputs at runtime.',
        );
    }
}
