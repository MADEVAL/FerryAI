<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\ModelHub\Signature\Sha256Verifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sha256Verifier::class)]
final class Sha256VerifierTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/ferry-sha256-' . \uniqid();
        \mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        \array_map('unlink', \glob($this->tempDir . '/*'));
        \rmdir($this->tempDir);
    }

    public function testCompute(): void
    {
        $path = $this->tempDir . '/test.txt';
        \file_put_contents($path, 'hello world');

        $hash = Sha256Verifier::compute($path);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function testVerify(): void
    {
        $path = $this->tempDir . '/test.txt';
        \file_put_contents($path, 'hello');
        $expected = \hash('sha256', 'hello');

        self::assertTrue(Sha256Verifier::verify($path, $expected));
    }

    public function testVerifyFails(): void
    {
        $path = $this->tempDir . '/test.txt';
        \file_put_contents($path, 'hello');

        self::assertFalse(Sha256Verifier::verify($path, '0000000000000000000000000000000000000000000000000000000000000000'));
    }

    public function testVerifyFile(): void
    {
        $dataPath = $this->tempDir . '/data.bin';
        $shaPath = $this->tempDir . '/data.bin.sha256';
        $content = 'test data';
        \file_put_contents($dataPath, $content);
        \file_put_contents($shaPath, \hash('sha256', $content));

        self::assertTrue(Sha256Verifier::verifyFile($dataPath, $shaPath));
    }

    public function testVerifyFileWithStandardSha256sumFormat(): void
    {
        $dataPath = $this->tempDir . '/data.bin';
        $shaPath = $this->tempDir . '/data.bin.sha256';
        $content = 'test data';
        \file_put_contents($dataPath, $content);
        // Standard `sha256sum` output: "<hash>  <filename>".
        \file_put_contents($shaPath, \hash('sha256', $content) . '  data.bin' . "\n");

        self::assertTrue(Sha256Verifier::verifyFile($dataPath, $shaPath));
    }
}
