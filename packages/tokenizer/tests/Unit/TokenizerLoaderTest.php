<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer\Tests\Unit;

use FerryAI\Core\Enums\TokenizerType;
use FerryAI\Core\Exception\TokenizerException;
use FerryAI\Tokenizer\TokenizerLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenizerLoader::class)]
final class TokenizerLoaderTest extends TestCase
{
    private TokenizerLoader $loader;

    private string $file = '';

    protected function setUp(): void
    {
        $this->loader = new TokenizerLoader();
    }

    protected function tearDown(): void
    {
        if ($this->file !== '' && is_file($this->file)) {
            unlink($this->file);
        }
    }

    private function writeConfig(string $json): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ferry_tok_');
        file_put_contents($path, $json);
        $this->file = $path;

        return $path;
    }

    public function testLoadFromFile(): void
    {
        $path = $this->writeConfig('{"model":{"type":"BPE","vocab":{}}}');

        self::assertSame('BPE', $this->loader->loadFromFile($path)['model']['type']);
    }

    public function testLoadFromMissingFileThrows(): void
    {
        $this->expectException(TokenizerException::class);

        $this->loader->loadFromFile(sys_get_temp_dir() . '/ferry-missing-tokenizer.json');
    }

    public function testLoadInvalidJsonThrows(): void
    {
        $path = $this->writeConfig('{not valid json');

        $this->expectException(TokenizerException::class);

        $this->loader->loadFromFile($path);
    }

    public function testDetectType(): void
    {
        self::assertSame(TokenizerType::BPE, $this->loader->detectType(['model' => ['type' => 'BPE']]));
        self::assertSame(TokenizerType::WordPiece, $this->loader->detectType(['model' => ['type' => 'WordPiece']]));
        self::assertSame(TokenizerType::Unigram, $this->loader->detectType(['model' => ['type' => 'Unigram']]));
    }

    public function testDetectTypeUnknownThrows(): void
    {
        $this->expectException(TokenizerException::class);

        $this->loader->detectType(['model' => ['type' => 'Mystery']]);
    }
}
