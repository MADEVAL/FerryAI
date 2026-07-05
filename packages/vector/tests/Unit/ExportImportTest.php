<?php

declare(strict_types=1);

namespace FerryAI\Vector\Tests\Unit;

use FerryAI\Vector\Collection;
use FerryAI\Vector\ExportImport;
use FerryAI\Vector\SQLiteStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExportImport::class)]
final class ExportImportTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/ferry-vector-test-' . \uniqid();
        \mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        \array_map('unlink', \glob($this->tempDir . '/*'));
        \rmdir($this->tempDir);
    }

    public function testToJsonAndFromJson(): void
    {
        $store = new SQLiteStore(':memory:');
        $store->createCollection('test', 2);
        $collection = new Collection('test', 2, $store);
        $collection->add('a', [1.0, 2.0], ['label' => 'A']);
        $collection->add('b', [3.0, 4.0], ['label' => 'B']);

        $path = $this->tempDir . '/export.jsonl';
        ExportImport::toJson($collection, $path);

        $imported = ExportImport::fromJson($path, 'imported', 2, $store);
        self::assertSame(2, $imported->count());
        self::assertSame('imported', $imported->collectionName());
    }

    public function testToCsv(): void
    {
        $store = new SQLiteStore(':memory:');
        $store->createCollection('test', 2);
        $collection = new Collection('test', 2, $store);
        $collection->add('a', [1.0, 2.0], ['label' => 'A']);

        $path = $this->tempDir . '/export.csv';
        ExportImport::toCsv($collection, $path);

        self::assertFileExists($path);
        $content = \file_get_contents($path);
        self::assertStringContainsString('a', $content);
        self::assertStringContainsString('1', $content);
        self::assertStringContainsString('2', $content);
    }

    public function testToJsonRoundTrip(): void
    {
        $store = new SQLiteStore(':memory:');
        $store->createCollection('test', 3);
        $collection = new Collection('test', 3, $store);
        $collection->add('id1', [0.1, 0.2, 0.3]);

        $path = $this->tempDir . '/roundtrip.jsonl';
        ExportImport::toJson($collection, $path);

        $otherStore = new SQLiteStore(':memory:');
        $imported = ExportImport::fromJson($path, 'test2', 3, $otherStore);

        self::assertSame(1, $imported->count());
        $results = $imported->search([0.1, 0.2, 0.3], 1);
        self::assertSame('id1', $results[0]['id']);
    }
}
