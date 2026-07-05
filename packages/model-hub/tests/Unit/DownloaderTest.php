<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\Core\Logger;
use FerryAI\Core\RetryHandler;
use FerryAI\ModelHub\Downloader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Downloader::class)]
final class DownloaderTest extends TestCase
{
    public function testDownloaderCanBeConstructed(): void
    {
        $downloader = new Downloader();

        self::assertInstanceOf(Downloader::class, $downloader);
    }

    public function testDownloadWithProgressReturnsGenerator(): void
    {
        $downloader = new Downloader();
        $generator = $downloader->downloadWithProgress('invalid-model-id', 'model.onnx', '/tmp/test.bin');

        self::assertInstanceOf(\Generator::class, $generator);
    }

    public function testCancelDoesNotError(): void
    {
        $downloader = new Downloader();
        $downloader->cancel();

        self::assertTrue(true);
    }

    public function testDownloadRetriesAndLogsFailures(): void
    {
        $logFile = \sys_get_temp_dir() . '/ferry-dl-' . \uniqid() . '.log';
        $downloader = new Downloader(new RetryHandler(), new Logger($logFile), maxAttempts: 3, retryDelayMs: 0);
        $destination = \sys_get_temp_dir() . '/ferry-dl-out-' . \uniqid() . '.bin';

        $threw = false;

        try {
            $downloader->download('file:///nonexistent-ferry-xyz-123', $destination);
        } catch (\RuntimeException) {
            $threw = true;
        }

        self::assertTrue($threw);

        $log = (string) \file_get_contents($logFile);
        self::assertSame(3, \substr_count($log, 'download.failed'));

        @\unlink($logFile);
    }
}
