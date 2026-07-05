<?php

declare(strict_types=1);

namespace FerryAI\ModelHub;

final class StreamLoader
{
    /**
     * Load a file via memory-mapped I/O for large models.
     *
     * @return resource|null
     */
    public function loadMmap(string $path)
    {
        if (!\file_exists($path)) {
            return null;
        }

        $handle = \fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        return $handle;
    }

    /**
     * Stream a file in chunks.
     *
     * @return \Generator<int, string>
     */
    public function loadStream(string $path, int $chunkSize = 1048576): \Generator
    {
        if (!\file_exists($path)) {
            return;
        }

        $handle = \fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        while (!\feof($handle)) {
            $chunk = \fread($handle, $chunkSize);

            if ($chunk === false || $chunk === '') {
                break;
            }
            yield $chunk;
        }

        \fclose($handle);
    }
}
