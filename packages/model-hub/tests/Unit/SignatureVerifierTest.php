<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\ModelHub\Signature\SignatureVerifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignatureVerifier::class)]
final class SignatureVerifierTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/ferry-sig-' . \uniqid();
        \mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        \array_map('unlink', \glob($this->tempDir . '/*'));
        \rmdir($this->tempDir);
    }

    public function testSignAndVerifyWithSodium(): void
    {
        if (!\extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium not available');
        }

        $keypair = \sodium_crypto_sign_keypair();
        $publicKey = \sodium_crypto_sign_publickey($keypair);
        $privateKey = \sodium_crypto_sign_secretkey($keypair);

        $dataPath = $this->tempDir . '/data.bin';
        \file_put_contents($dataPath, 'important data to sign');

        $privPath = $this->tempDir . '/private.key';
        \file_put_contents($privPath, $privateKey);

        $signature = SignatureVerifier::sign($dataPath, $privPath);

        $pubPath = $this->tempDir . '/public.key';
        \file_put_contents($pubPath, $publicKey);

        $sigPath = $this->tempDir . '/data.sig';
        \file_put_contents($sigPath, $signature);

        self::assertTrue(SignatureVerifier::verify($dataPath, $sigPath, $pubPath));
    }

    public function testVerifyFailsWithWrongKey(): void
    {
        if (!\extension_loaded('sodium')) {
            self::markTestSkipped('ext-sodium not available');
        }

        $keypair = \sodium_crypto_sign_keypair();
        $publicKey = \sodium_crypto_sign_publickey($keypair);
        $privateKey = \sodium_crypto_sign_secretkey($keypair);

        $otherKeypair = \sodium_crypto_sign_keypair();
        $otherPublicKey = \sodium_crypto_sign_publickey($otherKeypair);

        $dataPath = $this->tempDir . '/data.bin';
        \file_put_contents($dataPath, 'test');
        $privPath = $this->tempDir . '/private.key';
        \file_put_contents($privPath, $privateKey);
        $signature = SignatureVerifier::sign($dataPath, $privPath);

        $pubPath = $this->tempDir . '/public.key';
        \file_put_contents($pubPath, $otherPublicKey);
        $sigPath = $this->tempDir . '/data.sig';
        \file_put_contents($sigPath, $signature);

        self::assertFalse(SignatureVerifier::verify($dataPath, $sigPath, $pubPath));
    }
}
