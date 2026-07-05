<?php

declare(strict_types=1);

namespace FerryAI\Vector\Tests\Unit;

use FerryAI\Vector\PostgresVecIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgresVecIndex::class)]
final class PostgresVecIndexHelpersTest extends TestCase
{
    public function testOpClassForEachMetric(): void
    {
        self::assertSame('vector_cosine_ops', PostgresVecIndex::opClass('cosine'));
        self::assertSame('vector_l2_ops', PostgresVecIndex::opClass('euclidean'));
        self::assertSame('vector_ip_ops', PostgresVecIndex::opClass('dot'));
    }

    public function testOpClassRejectsUnknownMetric(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PostgresVecIndex::opClass('hamming');
    }

    public function testBuildHnswIndexSql(): void
    {
        $sql = PostgresVecIndex::buildCreateIndexSql('docs', 'hnsw', 'cosine');

        self::assertSame(
            'CREATE INDEX IF NOT EXISTS "vectors_docs_hnsw" ON "vectors_docs" '
            . 'USING hnsw (embedding vector_cosine_ops)',
            $sql,
        );
    }

    public function testBuildIvfflatIndexSql(): void
    {
        $sql = PostgresVecIndex::buildCreateIndexSql('docs', 'ivfflat', 'euclidean');

        self::assertSame(
            'CREATE INDEX IF NOT EXISTS "vectors_docs_ivfflat" ON "vectors_docs" '
            . 'USING ivfflat (embedding vector_l2_ops) WITH (lists = 100)',
            $sql,
        );
    }

    public function testBuildCreateIndexSqlRejectsUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PostgresVecIndex::buildCreateIndexSql('docs', 'bogus', 'cosine');
    }
}
