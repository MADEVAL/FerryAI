<?php

declare(strict_types=1);

namespace FerryAI\Core;

final class Logger
{
    private string $logFile;
    private string $level;

    public function __construct(?string $logFile = null, string $level = 'info')
    {
        $this->logFile = $logFile ?? \sys_get_temp_dir() . '/ferry-ai.log';
        $this->level = $level;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
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
