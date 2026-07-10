<?php

declare(strict_types=1);

namespace FerryAI\Core;

final class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    private string $logFile;
    private int $threshold;
    private ?int $maxBytes;

    public function __construct(?string $logFile = null, string $level = 'debug', ?int $maxBytes = null)
    {
        $this->logFile = $logFile ?? \sys_get_temp_dir() . '/ferry-ai.log';
        // Normalise case; fall back to 'warning' for unknown levels rather than logging everything.
        $this->threshold = self::LEVELS[\strtolower($level)] ?? self::LEVELS['warning'];
        $this->maxBytes = $maxBytes !== null && $maxBytes > 0 ? $maxBytes : null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context): void
    {
        if ((self::LEVELS[$level] ?? 0) < $this->threshold) {
            return;
        }

        $entry = \json_encode([
            'timestamp' => \date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE);

        if (\is_string($entry)) {
            $line = $entry . "\n";
            $this->rotateIfNeeded(\strlen($line));

            if (@\file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX) === false) {
                // Never let a logging failure (full disk, bad permissions) break the caller or
                // silently drop the message: fall back to the PHP error log.
                \error_log('ferry-ai logger write failed: ' . $entry);
            }
        }
    }

    /**
     * Rotates the current log to `<file>.1` once appending would exceed the configured size cap.
     * A single backup is kept; the previous `.1` is replaced. Disabled when maxBytes is null.
     */
    private function rotateIfNeeded(int $incomingBytes): void
    {
        if ($this->maxBytes === null) {
            return;
        }

        \clearstatcache(true, $this->logFile);

        if (!\is_file($this->logFile)) {
            return;
        }

        $current = \filesize($this->logFile);

        if ($current === false || $current + $incomingBytes <= $this->maxBytes) {
            return;
        }

        $backup = $this->logFile . '.1';
        @\unlink($backup);
        @\rename($this->logFile, $backup);
    }
}
