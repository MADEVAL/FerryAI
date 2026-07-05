<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer\Tests\Unit;

use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Exception\TokenizerException;
use FerryAI\Tokenizer\PureBpeTokenizer;
use FerryAI\Tokenizer\PureWordPieceTokenizer;
use FerryAI\Tokenizer\TokenizerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenizerFactory::class)]
final class TokenizerFactoryTest extends TestCase
{
    private TokenizerFactory $factory;

    private string $file = '';

    protected function setUp(): void
    {
        $this->factory = new TokenizerFactory();
    }

    protected function tearDown(): void
    {
        if ($this->file !== '' && is_file($this->file)) {
            unlink($this->file);
        }
    }

    private function writeConfig(string $json): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ferry_tokfac_');
        file_put_contents($path, $json);
        $this->file = $path;

        return $path;
    }

    public function testCreateFromBpeFile(): void
    {
        $path = $this->writeConfig('{"model":{"type":"BPE","vocab":{"a":0,"b":1},"merges":[]}}');

        $tokenizer = $this->factory->createFromFile($path);

        self::assertInstanceOf(Tokenizer::class, $tokenizer);
        self::assertInstanceOf(PureBpeTokenizer::class, $tokenizer);
    }

    public function testCreateFromWordPieceFile(): void
    {
        $path = $this->writeConfig('{"model":{"type":"WordPiece","vocab":{"a":0,"b":1}}}');

        self::assertInstanceOf(PureWordPieceTokenizer::class, $this->factory->createFromFile($path));
    }

    public function testCreateFromUnsupportedTypeThrows(): void
    {
        $path = $this->writeConfig('{"model":{"type":"Unigram","vocab":{}}}');

        $this->expectException(TokenizerException::class);

        $this->factory->createFromFile($path);
    }

    public function testCreateByPathDelegatesToFile(): void
    {
        $path = $this->writeConfig('{"model":{"type":"BPE","vocab":{"a":0},"merges":[]}}');

        self::assertInstanceOf(PureBpeTokenizer::class, $this->factory->create($path));
    }

    public function testCreateByModelNameRequiresHub(): void
    {
        $this->expectException(TokenizerException::class);

        $this->factory->create('gpt2');
    }
}
