<?php

declare(strict_types=1);

namespace FerryAI\Vector\Tests\Unit;

use FerryAI\Vector\PostgresStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgresStore::class)]
final class PostgresStoreHelpersTest extends TestCase
{
    public function testVectorTableNameForValidIdentifier(): void
    {
        self::assertSame('vectors_docs', PostgresStore::vectorTableName('docs'));
        self::assertSame('vectors_my_col2', PostgresStore::vectorTableName('my_col2'));
    }

    public function testVectorTableNameRejectsInjection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PostgresStore::vectorTableName('docs"; DROP TABLE users; --');
    }

    public function testVectorTableNameRejectsLeadingDigit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PostgresStore::vectorTableName('1col');
    }

    public function testVectorToLiteral(): void
    {
        self::assertSame('[1,0,0]', PostgresStore::vectorToLiteral([1.0, 0.0, 0.0]));
        self::assertSame('[0.5,0.25,-1]', PostgresStore::vectorToLiteral([0.5, 0.25, -1.0]));
    }

    public function testVectorToLiteralEmpty(): void
    {
        self::assertSame('[]', PostgresStore::vectorToLiteral([]));
    }

    public function testDistanceOperatorForEachMetric(): void
    {
        self::assertSame('<=>', PostgresStore::distanceOperator('cosine'));
        self::assertSame('<->', PostgresStore::distanceOperator('euclidean'));
        self::assertSame('<#>', PostgresStore::distanceOperator('dot'));
    }

    public function testDistanceOperatorRejectsUnknownMetric(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PostgresStore::distanceOperator('manhattan');
    }
}
