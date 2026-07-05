<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Format;

final class FormatDetector
{
    private const MAGIC_BYTES = [
        'onnx' => "\x08\x08\x12\x08",
        'gguf' => 'GGUF',
        'ai' => "PK\x03\x04",
        'rbm' => "RBM\x00",
    ];

    public static function detect(string $path): string
    {
        if (!\file_exists($path)) {
            return 'unknown';
        }

        $handle = \fopen($path, 'rb');

        if ($handle === false) {
            return 'unknown';
        }

        $header = \fread($handle, 16);
        \fclose($handle);

        if ($header === false || \strlen($header) < 4) {
            return 'unknown';
        }

        foreach (self::MAGIC_BYTES as $format => $magic) {
            if (\str_starts_with($header, $magic)) {
                return $format;
            }
        }

        if (\strlen($header) >= 8) {
            $headerLen = \unpack('P', \substr($header, 0, 8));

            if (\is_array($headerLen) && isset($headerLen[1]) && $headerLen[1] > 0 && $headerLen[1] < 100_000_000) {
                return 'safetensors';
            }
        }

        return 'unknown';
    }
}
