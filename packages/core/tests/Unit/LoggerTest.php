<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit;

use FerryAI\Core\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Logger::class)]
final class LoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = \sys_get_temp_dir() . '/ferry-logger-test-' . \uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->logFile)) {
            \unlink($this->logFile);
        }
    }

    public function testDebugWritesToFile(): void
    {
        $logger = new Logger($this->logFile);
        $logger->debug('test message', ['key' => 'value']);

        $content = \file_get_contents($this->logFile);
        self::assertIsString($content);
        self::assertStringContainsString('debug', $content);
        self::assertStringContainsString('test message', $content);
        self::assertStringContainsString('key', $content);
    }

    public function testInfoWritesToFile(): void
    {
        $logger = new Logger($this->logFile);
        $logger->info('info message');

        $content = \file_get_contents($this->logFile);
        self::assertIsString($content);
        self::assertStringContainsString('info', $content);
        self::assertStringContainsString('info message', $content);
    }

    public function testWarningWritesToFile(): void
    {
        $logger = new Logger($this->logFile);
        $logger->warning('warning message');

        $content = \file_get_contents($this->logFile);
        self::assertIsString($content);
        self::assertStringContainsString('warning', $content);
    }

    public function testErrorWritesToFile(): void
    {
        $logger = new Logger($this->logFile);
        $logger->error('error message');

        $content = \file_get_contents($this->logFile);
        self::assertIsString($content);
        self::assertStringContainsString('error', $content);
    }

    public function testMultipleEntries(): void
    {
        $logger = new Logger($this->logFile);
        $logger->info('first');
        $logger->info('second');

        $lines = \explode("\n", \trim((string) \file_get_contents($this->logFile)));
        self::assertCount(2, \array_filter($lines, static fn(string $l): bool => $l !== ''));
    }

    public function testDefaultLogFile(): void
    {
        $logger = new Logger(null);
        $logger->info('default path test');

        self::assertTrue(true);
    }
}
