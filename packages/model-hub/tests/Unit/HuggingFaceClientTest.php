<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

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
