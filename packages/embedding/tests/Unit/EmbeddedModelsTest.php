<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Tests\Unit;

use FerryAI\Embedding\EmbeddedModels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbeddedModels::class)]
final class EmbeddedModelsTest extends TestCase
{
    public function testListReturnsAllModels(): void
    {
        $models = EmbeddedModels::list();

        self::assertIsArray($models);
        self::assertNotEmpty($models);
        self::assertArrayHasKey('all-MiniLM-L6-v2', $models);
        self::assertArrayHasKey('all-mpnet-base-v2', $models);
        self::assertArrayHasKey('multilingual-e5-small', $models);
        self::assertArrayHasKey('bge-small-en', $models);
    }

    public function testGetReturnsModelInfo(): void
    {
        $info = EmbeddedModels::get('all-MiniLM-L6-v2');

        self::assertIsArray($info);
        self::assertSame('sentence-transformers/all-MiniLM-L6-v2', $info['hf_id']);
        self::assertSame(384, $info['dimension']);
        self::assertSame('mean', $info['pooling']);
    }

    public function testGetReturnsNullForUnknownModel(): void
    {
        $info = EmbeddedModels::get('nonexistent-model');

        self::assertNull($info);
    }

    public function testIsEmbeddedReturnsTrueForKnownModel(): void
    {
        self::assertTrue(EmbeddedModels::isEmbedded('all-MiniLM-L6-v2'));
    }

    public function testIsEmbeddedReturnsFalseForUnknownModel(): void
    {
        self::assertFalse(EmbeddedModels::isEmbedded('unknown'));
    }

    public function testModelsHaveExpectedStructure(): void
    {
        foreach (EmbeddedModels::list() as $info) {
            self::assertArrayHasKey('hf_id', $info);
            self::assertArrayHasKey('dimension', $info);
            self::assertArrayHasKey('pooling', $info);
            self::assertIsString($info['hf_id']);
            self::assertIsInt($info['dimension']);
            self::assertIsString($info['pooling']);
            self::assertGreaterThan(0, $info['dimension']);
        }
    }
}
