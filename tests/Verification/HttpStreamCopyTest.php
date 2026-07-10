<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\ModelHub\Downloader;
use FerryAI\ModelHub\HttpStream;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard for the extracted shared streaming helper: the chunked copy loop
 * (previously duplicated in Downloader x2 and HuggingFaceClient) must copy bytes intact and
 * report cumulative progress, and the Downloader must still stream a resource to disk.
 */
#[CoversNothing]
final class HttpStreamCopyTest extends TestCase
{
    public function testCopyTransfersBytesAndYieldsCumulativeProgress(): void
    {
        $payload = \str_repeat('A', 8192) . \str_repeat('B', 100); // 2 chunks
        $srcPath = (string) \tempnam(\sys_get_temp_dir(), 'ferry_src_');
        $dstPath = (string) \tempnam(\sys_get_temp_dir(), 'ferry_dst_');
        \file_put_contents($srcPath, $payload);

        $in = \fopen($srcPath, 'rb');
        $out = \fopen($dstPath, 'wb');
        self::assertNotFalse($in);
        self::assertNotFalse($out);

        $progress = [];

        try {
            foreach (HttpStream::copy($in, $out, $dstPath) as $downloaded) {
                $progress[] = $downloaded;
            }
        } finally {
            \fclose($in);
            \fclose($out);
        }

        self::assertSame($payload, (string) \file_get_contents($dstPath));
        self::assertSame([8192, 8292], $progress);

        @\unlink($srcPath);
        @\unlink($dstPath);
    }

    public function testDownloaderStreamsFileUrlToDisk(): void
    {
        $payload = \str_repeat('MODEL', 5000); // 25000 bytes, multiple chunks
        $srcPath = (string) \tempnam(\sys_get_temp_dir(), 'ferry_dlsrc_');
        $dstPath = (string) \tempnam(\sys_get_temp_dir(), 'ferry_dldst_');
        \file_put_contents($srcPath, $payload);

        (new Downloader(retryDelayMs: 0))->download('file://' . $srcPath, $dstPath);

        self::assertSame($payload, (string) \file_get_contents($dstPath));

        @\unlink($srcPath);
        @\unlink($dstPath);
    }

    public function testDownloadWithProgressStreamsFileUrlAndFinishes(): void
    {
        $payload = \str_repeat('X', 20000);
        $srcPath = (string) \tempnam(\sys_get_temp_dir(), 'ferry_dlpsrc_');
        $dstPath = (string) \tempnam(\sys_get_temp_dir(), 'ferry_dlpdst_');
        \file_put_contents($srcPath, $payload);

        $events = \iterator_to_array((new Downloader())->downloadWithProgress('file://' . $srcPath, $dstPath), false);
        $last = $events[\count($events) - 1];

        self::assertSame(1.0, $last['progress']);
        self::assertSame(20000, $last['downloaded']);
        self::assertSame($payload, (string) \file_get_contents($dstPath));

        @\unlink($srcPath);
        @\unlink($dstPath);
    }
}
