<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\Core\Contracts\ModelHub;
use FerryAI\ModelHub\Hub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Hub::class)]
final class HubTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = \sys_get_temp_dir() . '/ferry-hub-' . \uniqid();
        \mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        \array_map('unlink', \glob($this->cacheDir . '/*'));
        \rmdir($this->cacheDir);
    }

    public function testImplementsModelHub(): void
    {
        $hub = new Hub($this->cacheDir);

        self::assertInstanceOf(ModelHub::class, $hub);
    }

    public function testCacheSize(): void
    {
        $hub = new Hub($this->cacheDir);

        self::assertSame(0, $hub->cacheSize());
    }

    public function testCachedReturnsNullForUnknownModel(): void
    {
        $hub = new Hub($this->cacheDir);

        self::assertNull($hub->cached('unknown/model'));
    }

    public function testVerify(): void
    {
        $hub = new Hub($this->cacheDir);
        $path = $this->cacheDir . '/test.onnx';
        \file_put_contents($path, "\x08\x08\x12\x08" . \str_repeat('x', 100));
        $sha256 = \hash('sha256', "\x08\x08\x12\x08" . \str_repeat('x', 100));

        self::assertTrue($hub->verify($path, $sha256));
    }

    public function testIntrospect(): void
    {
        $hub = new Hub($this->cacheDir);
        $path = $this->cacheDir . '/model.onnx';
        \file_put_contents($path, "\x08\x08\x12\x08" . \str_repeat('x', 100));

        $metadata = $hub->introspect($path);

        self::assertSame('model', $metadata->name);
    }

    public function testPrune(): void
    {
        $hub = new Hub($this->cacheDir);
        $path1 = $this->cacheDir . '/m1.bin';
        $path2 = $this->cacheDir . '/m2.bin';
        \file_put_contents($path1, \str_repeat('x', 80));
        \file_put_contents($path2, \str_repeat('x', 80));
        $hub->register('m1', $path1);
        $hub->register('m2', $path2);

        $pruned = $hub->prune(100);

        self::assertGreaterThan(0, $pruned);
    }

    public function testRegisterAndList(): void
    {
        $hub = new Hub($this->cacheDir);
        $path = $this->cacheDir . '/registered.onnx';
        \file_put_contents($path, "\x08\x08\x12\x08" . 'data');
        $hub->register('my-model', $path, 'abc123');

        $list = $hub->list();

        self::assertNotEmpty($list);
    }

    public function testRemove(): void
    {
        $hub = new Hub($this->cacheDir);
        $path = $this->cacheDir . '/tmp.bin';
        \file_put_contents($path, 'data');
        $hub->register('tmp', $path);

        $hub->remove('tmp');

        self::assertNull($hub->cached('tmp'));
    }

    public function testWarmupSkipsAlreadyCachedModels(): void
    {
        $hub = new Hub($this->cacheDir);
        $path = $this->cacheDir . '/warm.onnx';
        \file_put_contents($path, "\x08\x08\x12\x08" . 'data');
        $hub->register('warm-model', $path);

        $hub->warmup(['warm-model']);

        self::assertNotNull($hub->cached('warm-model'));
    }

    public function testWarmupAttemptsToDownloadUncachedModels(): void
    {
        $hub = new Hub($this->cacheDir);

        $this->expectException(\FerryAI\Core\Exception\ModelNotFoundException::class);

        $hub->warmup(['uncached/model']);
    }

    public function testDownloadWithProgressReturnsGenerator(): void
    {
        $hub = new Hub($this->cacheDir);
        $generator = $hub->downloadWithProgress('nonexistent/model');

        self::assertInstanceOf(\Generator::class, $generator);
    }

    public function testVerifySignatureIsSilentlySkippedWithoutPublicKey(): void
    {
        if (!\extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium is required for Ed25519 verification.');
        }

        $path = $this->cacheDir . '/data.bin';
        \file_put_contents($path, "\x08\x08\x12\x08" . \str_repeat('x', 100));

        $keypair = \sodium_crypto_sign_keypair();
        $secretKey = \sodium_crypto_sign_secretkey($keypair);
        $publicKeyRaw = \sodium_crypto_sign_publickey($keypair);

        $signature = \sodium_crypto_sign_detached(
            \file_get_contents($path),
            $secretKey,
        );

        $sigPath = $this->cacheDir . '/data.sig';
        $pubPath = $this->cacheDir . '/data.pub';
        \file_put_contents($sigPath, $signature);
        \file_put_contents($pubPath, $publicKeyRaw);

        $hubWithoutKey = new Hub($this->cacheDir);
        $result = $hubWithoutKey->verify($path, signature: $sigPath);

        self::assertFalse(
            $result,
            'Hub::verify() must return false when signature is provided but public key is absent.',
        );

        $hubWithKey = new Hub($this->cacheDir, publicKey: $pubPath);
        $resultWithKey = $hubWithKey->verify($path, signature: $sigPath);

        self::assertTrue(
            $resultWithKey,
            'Hub::verify() must return true when the signature is valid and the public key is provided.',
        );
    }
}
