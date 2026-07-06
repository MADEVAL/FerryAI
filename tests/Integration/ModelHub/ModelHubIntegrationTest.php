<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\ModelHub;

use FerryAI\ModelHub\CacheManager;
use FerryAI\ModelHub\Hub;
use FerryAI\ModelHub\ModelVerifier;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Model Hub download → cache → verify cycle.
 *
 * CacheManager and Hub::verify / Hub::register / Hub::remove are tested
 * without network access. Hub::download and HuggingFaceClient are
 * skipped when HF is unreachable.
 */
#[Group('integration')]
#[CoversNothing]
final class ModelHubIntegrationTest extends TestCase
{
    private string $cacheDir;

    private Hub $hub;

    private string $existingModel;

    private string $sourceDir;

    protected function setUp(): void
    {
        $this->cacheDir = \sys_get_temp_dir() . '/ferry_ai_hub_test_' . \uniqid();
        $this->sourceDir = \sys_get_temp_dir() . '/ferry_ai_hub_src_' . \uniqid();

        if (!\is_dir($this->cacheDir)) {
            \mkdir($this->cacheDir, 0755, true);
        }

        if (!\is_dir($this->sourceDir)) {
            \mkdir($this->sourceDir, 0755, true);
        }

        $this->hub = new Hub($this->cacheDir);

        $onnxModel = 'D:\\FerryAI\\all-MiniLM-L6-v2-onnx\\model.onnx';
        $this->existingModel = \is_file($onnxModel) ? $onnxModel : '';
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->cacheDir);
        $this->rmdir($this->sourceDir);
    }

    private function rmdir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        foreach (\array_diff((array) \scandir($dir), ['.', '..']) as $item) {
            $path = $dir . \DIRECTORY_SEPARATOR . $item;
            \is_dir($path) ? $this->rmdir($path) : \unlink($path);
        }

        \rmdir($dir);
    }

    private function hasNetwork(): bool
    {
        return (bool) @\file_get_contents('https://huggingface.co', false, \stream_context_create([
            'http' => ['timeout' => 3, 'ignore_errors' => true],
        ]));
    }

    private function createSourceFile(string $content): string
    {
        $path = $this->sourceDir . '/test_source_' . \uniqid() . '.txt';
        \file_put_contents($path, $content);

        return $path;
    }

    public function testCacheManagerPutAndGet(): void
    {
        $cache = new CacheManager($this->cacheDir);
        $sourceFile = $this->createSourceFile('hello cache');

        $cache->put('test_model', $sourceFile);

        $cached = $cache->get('test_model');
        self::assertNotNull($cached);
        self::assertStringEqualsFile($cached, 'hello cache');
    }

    public function testCacheManagerHasForExisingAndMissing(): void
    {
        $cache = new CacheManager($this->cacheDir);
        $sourceFile = $this->createSourceFile('content');

        $cache->put('has_test', $sourceFile);

        self::assertTrue($cache->has('has_test'));
        self::assertFalse($cache->has('does_not_exist'));
    }

    public function testCacheManagerRemove(): void
    {
        $cache = new CacheManager($this->cacheDir);
        $sourceFile = $this->createSourceFile('content');

        $cache->put('remove_test', $sourceFile);
        self::assertTrue($cache->has('remove_test'));

        $cache->remove('remove_test');
        self::assertFalse($cache->has('remove_test'));
    }

    public function testCacheManagerPruneEvictsOldestFiles(): void
    {
        $cache = new CacheManager($this->cacheDir);
        $sourceFile = $this->createSourceFile(str_repeat('x', 1000));

        $cache->put('large_a', $sourceFile);
        $cache->put('large_b', $sourceFile);

        self::assertTrue($cache->has('large_a'));
        self::assertTrue($cache->has('large_b'));

        $pruned = $cache->prune(1500);

        self::assertSame(1, $pruned);
        self::assertLessThanOrEqual(1500, $cache->cacheSize());
    }

    public function testCacheManagerCacheSize(): void
    {
        $cache = new CacheManager($this->cacheDir);
        $sourceFile = $this->createSourceFile(str_repeat('b', 500));

        $cache->put('size_test', $sourceFile);

        self::assertSame(500, $cache->cacheSize());
    }

    public function testCacheManagerList(): void
    {
        $cache = new CacheManager($this->cacheDir);
        $sourceFile = $this->createSourceFile('content');

        $cache->put('list_test', $sourceFile);

        $list = $cache->list();
        self::assertArrayHasKey('list_test', $list);
        self::assertSame(\filesize($cache->get('list_test')), $list['list_test']['size']);
    }

    public function testCacheManagerClear(): void
    {
        $cache = new CacheManager($this->cacheDir);
        $sourceFile = $this->createSourceFile('content');

        $cache->put('clear_test', $sourceFile);
        $cache->clear();

        self::assertSame(0, $cache->cacheSize());
        self::assertSame([], $cache->list());
    }

    public function testHubRegisterAndCached(): void
    {
        $sourceFile = $this->createSourceFile('hub test content');

        $this->hub->register('my-model', $sourceFile);

        $cached = $this->hub->cached('my-model');
        self::assertNotNull($cached);
        self::assertStringEqualsFile($cached, 'hub test content');

        $this->hub->remove('my-model');
        self::assertNull($this->hub->cached('my-model'));
    }

    public function testHubPrune(): void
    {
        $sourceFile = $this->createSourceFile(str_repeat('x', 500));

        $this->hub->register('prune_a', $sourceFile);
        $this->hub->register('prune_b', $sourceFile);

        self::assertGreaterThan(0, $this->hub->cacheSize());

        $pruned = $this->hub->prune(600);

        self::assertSame(1, $pruned);
        self::assertLessThanOrEqual(600, $this->hub->cacheSize());
    }

    public function testHubVerifyExistingOnnxModel(): void
    {
        if ($this->existingModel === '') {
            self::markTestSkipped('No ONNX model available for verification test.');
        }

        self::assertTrue($this->hub->verify($this->existingModel));
    }

    public function testHubQuickVerifyExistingOnnxModel(): void
    {
        if ($this->existingModel === '') {
            self::markTestSkipped('No ONNX model available for verification test.');
        }

        self::assertTrue(ModelVerifier::quickVerify($this->existingModel));
    }

    public function testHubVerifyFailsForNonexistentPath(): void
    {
        self::assertFalse($this->hub->verify('/nonexistent/path/model.onnx'));
    }

    public function testHubVerifyUnknownFormatReturnsFalse(): void
    {
        $tmpFile = $this->cacheDir . '/not_a_model.txt';
        \file_put_contents($tmpFile, 'hello world');

        self::assertFalse($this->hub->verify($tmpFile), 'Non-model file should not pass verification');
    }

    public function testHubCacheSizeIsZeroEmptyCache(): void
    {
        self::assertSame(0, $this->hub->cacheSize());
    }

    public function testHubList(): void
    {
        $sourceFile = $this->createSourceFile('content');

        $this->hub->register('list_model', $sourceFile);

        $keys = $this->hub->list();
        self::assertContains('list_model', $keys);
    }

    public function testHubWarmup(): void
    {
        $sourceFile = $this->createSourceFile('warmup content');

        $this->hub->register('warmup_test', $sourceFile);

        $this->hub->warmup(['warmup_test', 'nonexistent']);

        self::assertNull($this->hub->cached('nonexistent'), 'Missing model is not cached by warmup');
    }

    public function testHuggingFaceClientListsFilesForPublicModel(): void
    {
        if (!$this->hasNetwork()) {
            self::markTestSkipped('No network - skipping HuggingFace API test.');
        }

        $client = new \FerryAI\ModelHub\HuggingFaceClient();
        $files = $client->listFiles('sentence-transformers/all-MiniLM-L6-v2');

        self::assertNotEmpty($files, 'Should list files for a well-known public model');
    }

    public function testHuggingFaceClientGetsModelInfo(): void
    {
        if (!$this->hasNetwork()) {
            self::markTestSkipped('No network - skipping HuggingFace API test.');
        }

        $client = new \FerryAI\ModelHub\HuggingFaceClient();
        $info = $client->getModelInfo('sentence-transformers/all-MiniLM-L6-v2');

        self::assertNotEmpty($info, 'Should get info for a well-known public model');
        self::assertArrayHasKey('modelId', $info);
    }

    public function testHuggingFaceClientHandlesNonexistentModelGracefully(): void
    {
        if (!$this->hasNetwork()) {
            self::markTestSkipped('No network - skipping HuggingFace API test.');
        }

        $client = new \FerryAI\ModelHub\HuggingFaceClient();
        $files = $client->listFiles('nonexistent/this-model-does-not-exist-12345');

        self::assertSame([], $files);
    }

    public function testHubDownloadPublicModel(): void
    {
        if (!$this->hasNetwork()) {
            self::markTestSkipped('No network - skipping download test.');
        }

        $modelId = 'sentence-transformers/all-MiniLM-L6-v2';

        $path = $this->hub->download($modelId);

        self::assertFileExists($path);

        $cached = $this->hub->cached($modelId);
        self::assertNotNull($cached);
        self::assertFileExists($cached);

        $this->hub->remove($modelId);
        self::assertNull($this->hub->cached($modelId));
    }
}
