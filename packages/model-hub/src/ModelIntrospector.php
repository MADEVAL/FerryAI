<?php

declare(strict_types=1);

namespace FerryAI\ModelHub;

use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\ModelHub\Format\FormatDetector;
use FerryAI\ModelHub\Format\GgufInspector;
use FerryAI\ModelHub\Format\OnnxInspector;

final class ModelIntrospector
{
    public static function introspect(string $path): ModelMetadata
    {
        $format = FormatDetector::detect($path);

        return match ($format) {
            'onnx' => OnnxInspector::inspect($path),
            'gguf' => GgufInspector::inspect($path),
            default => self::basicInfo($path),
        };
    }

    private static function basicInfo(string $path): ModelMetadata
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
}
