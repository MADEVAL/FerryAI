<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\ValueObjects;

use FerryAI\Core\ValueObjects\ModelMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelMetadata::class)]
final class ModelMetadataTest extends TestCase
{
    private function sample(): ModelMetadata
    {
        return new ModelMetadata(
            name: 'all-MiniLM-L6-v2',
            version: '1.0',
            author: 'sentence-transformers',
            license: 'Apache-2.0',
            tags: ['embedding', 'sentence-similarity'],
            sizeBytes: 90_000_000,
            architecture: 'bert',
            description: 'A small embedding model',
            homepage: 'https://example.com',
        );
    }

    public function testFieldsAreReadable(): void
    {
        $metadata = $this->sample();

        self::assertSame('all-MiniLM-L6-v2', $metadata->name);
        self::assertSame(['embedding', 'sentence-similarity'], $metadata->tags);
        self::assertSame(90_000_000, $metadata->sizeBytes);
    }

    public function testOptionalFieldsDefaultToNull(): void
    {
        $metadata = new ModelMetadata('m', '1', 'a', 'MIT', [], 1);

        self::assertNull($metadata->architecture);
        self::assertNull($metadata->description);
        self::assertNull($metadata->homepage);
    }

    public function testJsonRoundTrip(): void
    {
        $metadata = $this->sample();

        self::assertEquals($metadata, ModelMetadata::fromJson($metadata->toJson()));
    }
}
