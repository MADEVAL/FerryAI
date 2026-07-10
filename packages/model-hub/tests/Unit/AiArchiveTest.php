<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Tests\Unit;

use FerryAI\ModelHub\Format\AiArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AiArchive::class)]
final class AiArchiveTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/ferry-ai-archive-' . \uniqid();
        \mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->tempDir);
    }

    private function cleanDir(string $dir): void
    {
        foreach (\glob($dir . '/*') as $file) {
            \is_dir($file) ? $this->cleanDir($file) : \unlink($file);
        }
        \rmdir($dir);
    }

    public function testCreateAndExtract(): void
    {
        $modelPath = $this->tempDir . '/model.onnx';
        $configPath = $this->tempDir . '/config.json';
        \file_put_contents($modelPath, 'fake-model-data');
        \file_put_contents($configPath, '{"type": "test"}');

        $archivePath = $this->tempDir . '/output.ai';
        AiArchive::create($archivePath, [
            'model.onnx' => $modelPath,
            'config.json' => $configPath,
        ]);

        self::assertFileExists($archivePath);

        $extractDir = $this->tempDir . '/extracted';
        \mkdir($extractDir);
        $extracted = AiArchive::extract($archivePath, $extractDir);

        self::assertArrayHasKey('model.onnx', $extracted);
        self::assertArrayHasKey('config.json', $extracted);
        self::assertStringContainsString('model.onnx', $extracted['model.onnx']);
        self::assertStringContainsString('config.json', $extracted['config.json']);
    }

    public function testList(): void
    {
        $modelPath = $this->tempDir . '/model.onnx';
        \file_put_contents($modelPath, 'data');

        $archivePath = $this->tempDir . '/output.ai';
        AiArchive::create($archivePath, ['model.onnx' => $modelPath]);

        $files = AiArchive::list($archivePath);

        self::assertContains('model.onnx', $files);
    }

    public function testValidate(): void
    {
        $modelPath = $this->tempDir . '/model.onnx';
        \file_put_contents($modelPath, 'data');

        $archivePath = $this->tempDir . '/valid.ai';
        AiArchive::create($archivePath, ['model.onnx' => $modelPath]);

        self::assertTrue(AiArchive::validate($archivePath));
    }

    public function testValidateMissingModel(): void
    {
        $configPath = $this->tempDir . '/config.json';
        \file_put_contents($configPath, '{}');

        $archivePath = $this->tempDir . '/invalid.ai';
        AiArchive::create($archivePath, ['config.json' => $configPath]);

        self::assertFalse(AiArchive::validate($archivePath));
    }
}
