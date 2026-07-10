<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Format;

/**
 * Reads a safetensors file header and returns structured metadata about its tensors
 * without loading the weight data into memory. Useful for Model Hub introspection.
 *
 * Safetensors format: [8-byte uint64 LE header-length] [JSON header] [binary tensor data...]
 *
 * The JSON header maps tensor names to their dtype, shape and byte-range offsets:
 *   {"weight": {"dtype":"F32","shape":[M,N],"data_offsets":[start,end]}, ...}
 */
final class SafetensorsInspector
{
    /**
     * @return array{format: string, tensor_count: int, data_size_bytes: int, tensors: array<string, array{dtype: string, shape: int[], size_bytes: int}>}
     */
    public static function inspect(string $path): array
    {
        if (!\file_exists($path)) {
            return [];
        }

        $handle = \fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $lenBytes = \fread($handle, 8);

        if ($lenBytes === false || \strlen($lenBytes) < 8) {
            \fclose($handle);

            return [];
        }

        $headerArr = \unpack('P', $lenBytes);

        if (!\is_array($headerArr) || !isset($headerArr[1]) || $headerArr[1] <= 0 || $headerArr[1] > 100_000_000) {
            \fclose($handle);

            return [];
        }

        $headerLen = (int) $headerArr[1];
        $headerJson = \fread($handle, $headerLen);
        \fclose($handle);

        if ($headerJson === false || \strlen($headerJson) < $headerLen) {
            return [];
        }

        $header = \json_decode($headerJson, true);

        if (!\is_array($header)) {
            return [];
        }

        $tensors = [];
        $totalData = 0;

        foreach ($header as $name => $meta) {
            if (!\is_string($name) || !\is_array($meta)) {
                continue;
            }

            $offsets = $meta['data_offsets'] ?? null;

            if (!\is_array($offsets) || !isset($offsets[0], $offsets[1])) {
                continue;
            }

            $size = (int) $offsets[1] - (int) $offsets[0];

            $tensors[$name] = [
                'dtype' => \is_string($meta['dtype'] ?? null) ? $meta['dtype'] : 'unknown',
                'shape' => \is_array($meta['shape'] ?? null) ? $meta['shape'] : [],
                'size_bytes' => $size,
            ];

            $totalData += $size;
        }

        return [
            'format' => 'safetensors',
            'tensor_count' => \count($tensors),
            'data_size_bytes' => $totalData,
            'tensors' => $tensors,
        ];
    }

    public static function sizeBytes(string $path): int
    {
        if (!\file_exists($path)) {
            return 0;
        }

        return (int) \filesize($path);
    }
}
