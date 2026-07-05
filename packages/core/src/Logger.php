<?php

declare(strict_types=1);

namespace FerryAI\Core;

final class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    private string $logFile;
    private int $threshold;

    public function __construct(?string $logFile = null, string $level = 'debug')
    {
        $this->logFile = $logFile ?? \sys_get_temp_dir() . '/ferry-ai.log';
        $this->threshold = self::LEVELS[$level] ?? 0;
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
            \file_put_contents($this->logFile, $entry . "\n", FILE_APPEND | LOCK_EX);
        }
    }
}
