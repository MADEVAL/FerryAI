<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Core\Exception\ModelLoadException;
use FerryAI\ModelHub\CacheManager;
use FerryAI\ModelHub\Hub;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guards for the Hub reliability fixes: no temp-file leak (store moves,
 * not copies), register honours $sha256, cache keys round-trip losslessly, and progress
 * downloads expose the final cache path via the generator return value.
 */
#[CoversNothing]
final class HubImprovementsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = \sys_get_temp_dir() . '/ferry-hubimp-' . \uniqid();
        \mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $glob = \glob($this->dir . '/*');

        if ($glob !== false) {
            \array_map('unlink', $glob);
        }

        \rmdir($this->dir);
    }

    public function testStoreMovesSourceInsteadOfCopying(): void
    {
        $cache = new CacheManager($this->dir);
        $source = \sys_get_temp_dir() . '/ferry-src-' . \uniqid() . '.model';
        \file_put_contents($source, 'weights');

        $target = $cache->store('model-key', $source);

        self::assertFileDoesNotExist($source, 'store() must move the temp file, not leave a copy behind.');
        self::assertFileExists($target);
        self::assertSame('weights', \file_get_contents($target));
    }

    public function testRegisterRejectsWrongSha256(): void
    {
        $hub = new Hub($this->dir);
        $path = $this->dir . '/reg.onnx';
        \file_put_contents($path, "\x08\x08\x12\x08" . 'data');

        $this->expectException(ModelLoadException::class);
        $hub->register('bad', $path, 'deadbeef');
    }

    public function testRegisterAcceptsCorrectSha256(): void
    {
        $hub = new Hub($this->dir);
        $path = $this->dir . '/ok.onnx';
        $content = "\x08\x08\x12\x08" . 'data';
        \file_put_contents($path, $content);

        $hub->register('good', $path, \hash('sha256', $content));

        self::assertNotNull($hub->cached('good'));
    }

    public function testCacheKeyRoundTripsModelIdWithSlashAndUnderscore(): void
    {
        $hub = new Hub($this->dir);

        $key = (new \ReflectionMethod(Hub::class, 'cacheKey'))->invoke($hub, 'microsoft/phi_3', null);
        $modelId = (new \ReflectionMethod(Hub::class, 'decodeModelId'))->invoke(null, $key);

        self::assertSame('microsoft/phi_3', $modelId);
    }

    public function testCacheKeyRoundTripsIgnoringVersionSuffix(): void
    {
        $hub = new Hub($this->dir);

        $key = (new \ReflectionMethod(Hub::class, 'cacheKey'))->invoke($hub, 'org/my_model', '1.0');
        $modelId = (new \ReflectionMethod(Hub::class, 'decodeModelId'))->invoke(null, $key);

        self::assertSame('org/my_model', $modelId);
    }

    public function testDownloadWithProgressReturnsCachePathOnCacheHit(): void
    {
        $hub = new Hub($this->dir);
        $path = $this->dir . '/cached.onnx';
        \file_put_contents($path, "\x08\x08\x12\x08" . 'data');

        // Register under the exact cache key the Hub computes for this modelId.
        $key = (new \ReflectionMethod(Hub::class, 'cacheKey'))->invoke($hub, 'acme/model', null);
        $hub->register($key, $path, \hash('sha256', "\x08\x08\x12\x08" . 'data'));

        $gen = $hub->downloadWithProgress('acme/model');

        foreach ($gen as $_) {
            // drain progress events
        }

        self::assertSame($hub->cached('acme/model'), $gen->getReturn());
        self::assertNotNull($gen->getReturn());
    }
}
