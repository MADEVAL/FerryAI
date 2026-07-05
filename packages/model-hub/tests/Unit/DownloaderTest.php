<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

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
}
