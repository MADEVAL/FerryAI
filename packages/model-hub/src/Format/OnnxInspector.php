<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Format;

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
        if (!\file_exists($path)) {
            return [];
        }

        return [];
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public static function outputs(string $path): array
    {
        if (!\file_exists($path)) {
            return [];
        }

        return [];
    }
}
