<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\Core\RetryHandler;
use FerryAI\ModelHub\HuggingFaceClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HuggingFaceClient::class)]
final class HuggingFaceClientTest extends TestCase
{
    public function testClientCanBeConstructed(): void
    {
        $client = new HuggingFaceClient();

        self::assertInstanceOf(HuggingFaceClient::class, $client);
    }

    public function testClientWithToken(): void
    {
        $client = new HuggingFaceClient('hf_test_token');

        self::assertInstanceOf(HuggingFaceClient::class, $client);
    }

    public function testDownloadFileRetriesThenSucceeds(): void
    {
        $calls = 0;
        $httpGet = static function (string $url) use (&$calls): string|false {
            $calls++;

            return $calls < 3 ? false : 'BINARY_DATA';
        };
        $destination = \sys_get_temp_dir() . '/ferry-hf-' . \uniqid() . '.bin';

        $client = new HuggingFaceClient(null, new RetryHandler(), null, $httpGet, maxAttempts: 5, retryDelayMs: 0);
        $client->downloadFile('org/model', 'model.bin', $destination);

        self::assertSame('BINARY_DATA', (string) \file_get_contents($destination));
        self::assertSame(3, $calls);

        @\unlink($destination);
    }

    public function testDownloadFileThrowsAfterMaxAttempts(): void
    {
        $calls = 0;
        $httpGet = static function () use (&$calls): string|false {
            $calls++;

            return false;
        };

        $client = new HuggingFaceClient(null, new RetryHandler(), null, $httpGet, maxAttempts: 2, retryDelayMs: 0);

        $threw = false;

        try {
            $client->downloadFile('org/model', 'model.bin', \sys_get_temp_dir() . '/ferry-hf-none-' . \uniqid());
        } catch (\RuntimeException) {
            $threw = true;
        }

        self::assertTrue($threw);
        self::assertSame(2, $calls);
    }

    public function testGetModelInfoReturnsEmptyForInvalidModel(): void
    {
        $client = new HuggingFaceClient();
        $info = $client->getModelInfo('nonexistent/model-xyz-12345');

        self::assertSame([], $info);
    }

    public function testListFilesReturnsEmptyForInvalidModel(): void
    {
        $client = new HuggingFaceClient();
        $files = $client->listFiles('nonexistent/model-xyz-12345');

        self::assertSame([], $files);
    }

    public function testSearchModelsReturnsArray(): void
    {
        $client = new HuggingFaceClient();
        $results = $client->searchModels('bert');

        self::assertIsArray($results);
    }
}
