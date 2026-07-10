<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\ModelHub\ModelVerifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelVerifier::class)]
final class ModelVerifierTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/ferry-verify-' . \uniqid();
        \mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        \array_map('unlink', \glob($this->tempDir . '/*'));
        \rmdir($this->tempDir);
    }

    public function testVerifyWithSha256(): void
    {
        $path = $this->tempDir . '/model.onnx';
        \file_put_contents($path, "\x08\x08\x12\x08" . 'test-data');
        $sha256 = \hash('sha256', "\x08\x08\x12\x08" . 'test-data');

        self::assertTrue(ModelVerifier::verify($path, $sha256));
    }

    public function testVerifyWithWrongSha256(): void
    {
        $path = $this->tempDir . '/model.onnx';
        \file_put_contents($path, "\x08\x08\x12\x08" . 'data');

        self::assertFalse(ModelVerifier::verify($path, 'badhash'));
    }

    public function testQuickVerifyWithValidMagicBytes(): void
    {
        $path = $this->tempDir . '/model.gguf';
        \file_put_contents($path, 'GGUF' . \str_repeat('x', 100));

        self::assertTrue(ModelVerifier::quickVerify($path));
    }

    public function testQuickVerifyWithInvalidFile(): void
    {
        $path = $this->tempDir . '/model.txt';
        \file_put_contents($path, 'not a model file');

        self::assertFalse(ModelVerifier::quickVerify($path));
    }

    public function testQuickVerifyWithMissingFile(): void
    {
        self::assertFalse(ModelVerifier::quickVerify('/nonexistent/model.bin'));
    }
}
