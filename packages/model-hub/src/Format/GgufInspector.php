<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Format;

use FerryAI\Core\ValueObjects\ModelMetadata;

final class GgufInspector
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
     * @return array<string, mixed>
     */
    public static function metadata(string $path): array
    {
        if (!\file_exists($path)) {
            return [];
        }

        $handle = \fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $header = \fread($handle, 4);
        \fclose($handle);

        if ($header !== 'GGUF') {
            return [];
        }

        return [];
    }

    public static function sizeBytes(string $path): int
    {
        if (!\file_exists($path)) {
            return 0;
        }

        return (int) \filesize($path);
    }
}
