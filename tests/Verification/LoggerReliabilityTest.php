<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Core\Logger;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: the Logger must not silently drop messages when the log write fails
 * (fallback to error_log()), and must rotate the file once it exceeds the configured size cap.
 */
#[CoversNothing]
final class LoggerReliabilityTest extends TestCase
{
    public function testWriteFailureFallsBackToErrorLog(): void
    {
        // A path under a non-existent directory makes file_put_contents fail.
        $badPath = \sys_get_temp_dir() . '/ferry-nonexistent-' . \uniqid() . '/app.log';

        $capture = (string) \tempnam(\sys_get_temp_dir(), 'ferry_errlog_');
        $previous = \ini_set('error_log', $capture);

        try {
            $logger = new Logger($badPath, 'debug');
            $logger->error('disk full simulation');

            self::assertFileDoesNotExist($badPath);
            self::assertStringContainsString(
                'disk full simulation',
                (string) \file_get_contents($capture),
                'A failed write must fall back to error_log(), not vanish silently.',
            );
        } finally {
            if ($previous !== false) {
                \ini_set('error_log', $previous);
            }
            @\unlink($capture);
        }
    }

    public function testRotatesWhenExceedingMaxBytes(): void
    {
        $logFile = \sys_get_temp_dir() . '/ferry-rot-' . \uniqid() . '.log';
        $backup = $logFile . '.1';

        try {
            $logger = new Logger($logFile, 'debug', 50);

            $logger->error('first message that alone exceeds fifty bytes threshold');
            $logger->error('second message after rotation should start a fresh file');

            self::assertFileExists($backup, 'Exceeding maxBytes must rotate the old log to <log>.1');
            self::assertFileExists($logFile);

            $lines = \array_values(\array_filter(\explode("\n", (string) \file_get_contents($logFile))));
            self::assertCount(1, $lines, 'The active log must contain only entries written after rotation.');
            self::assertStringContainsString('second message', $lines[0]);
        } finally {
            @\unlink($logFile);
            @\unlink($backup);
        }
    }

    public function testNoRotationWhenMaxBytesNull(): void
    {
        $logFile = \sys_get_temp_dir() . '/ferry-norot-' . \uniqid() . '.log';

        try {
            $logger = new Logger($logFile, 'debug');

            for ($i = 0; $i < 5; $i++) {
                $logger->error('message ' . $i);
            }

            self::assertFileDoesNotExist($logFile . '.1', 'Without maxBytes there must be no rotation.');
            $lines = \array_values(\array_filter(\explode("\n", (string) \file_get_contents($logFile))));
            self::assertCount(5, $lines);
        } finally {
            @\unlink($logFile);
        }
    }
}
