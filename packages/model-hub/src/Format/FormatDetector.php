<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Format;

final class FormatDetector
{
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

        if (\str_starts_with($header, 'GGUF')) {
            return 'gguf';
        }

        if (\str_starts_with($header, "PK\x03\x04")) {
            return 'ai';
        }

        if (\str_starts_with($header, "RBM\x00")) {
            return 'rbm';
        }

        $b0 = \ord($header[0]);
        $b1 = \strlen($header) >= 2 ? \ord($header[1]) : 0;

        if ($b0 === 0x08 && $b1 < 0x20) {
            return 'onnx';
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
