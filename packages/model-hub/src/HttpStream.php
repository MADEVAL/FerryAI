<?php

declare(strict_types=1);

namespace FerryAI\ModelHub;

use FerryAI\Core\Exception\IoException;

/**
 * Shared chunked stream copy used by {@see Downloader} and {@see HuggingFaceClient}. Callers own
 * opening/closing the handles (their contexts, error messages and cancellation differ); this only
 * performs the fixed-size fread/fwrite loop, yielding the cumulative bytes written after each chunk.
 */
final class HttpStream
{
    /**
     * @param resource $in          readable source stream
     * @param resource $out         writable destination stream
     * @param string   $destination destination path, for error messages only
     *
     * @return \Generator<int, int> cumulative bytes written so far
     */
    public static function copy($in, $out, string $destination, int $chunkSize = 8192): \Generator
    {
        $downloaded = 0;

        while (!\feof($in)) {
            $chunk = \fread($in, $chunkSize);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $written = \fwrite($out, $chunk);

            if ($written === false || $written !== \strlen($chunk)) {
                throw new IoException(\sprintf('Failed to write downloaded data to: %s', $destination));
            }

            $downloaded += \strlen($chunk);

            yield $downloaded;
        }
    }
}
