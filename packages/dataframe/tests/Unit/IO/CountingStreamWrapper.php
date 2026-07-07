<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\Tests\Unit\IO;

/**
 * Minimal in-memory stream wrapper that counts how many times a path is opened,
 * used to assert that CsvReader does not reopen the file on every row.
 *
 * @internal test-only helper
 */
final class CountingStreamWrapper
{
    public static int $opens = 0;

    public static string $content = '';

    /** @var resource|null */
    public $context;

    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        ++self::$opens;
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = \substr(self::$content, $this->position, $count);
        $this->position += \strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen(self::$content);
    }

    public function stream_seek(int $offset, int $whence = \SEEK_SET): bool
    {
        $this->position = match ($whence) {
            \SEEK_CUR => $this->position + $offset,
            \SEEK_END => \strlen(self::$content) + $offset,
            default => $offset,
        };

        return true;
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    /**
     * @return array<int|string, int>
     */
    public function stream_stat(): array
    {
        return ['mode' => 0100644, 'size' => \strlen(self::$content)];
    }

    /**
     * @return array<int|string, int>
     */
    public function url_stat(string $path, int $flags): array
    {
        return ['mode' => 0100644, 'size' => \strlen(self::$content)];
    }

    public function stream_close(): void {}
}
