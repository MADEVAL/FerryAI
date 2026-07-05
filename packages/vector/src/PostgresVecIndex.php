<?php

declare(strict_types=1);

namespace FerryAI\Vector;

use FerryAI\Core\Exception\ValidationException;

/**
 * Manages pgvector ANN indexes (HNSW / IVFFlat) for a {@see PostgresStore}.
 *
 * pgvector indexes are per-column and metric-specific: the operator class
 * (e.g. `vector_cosine_ops`) must match the distance operator used at query
 * time, otherwise the index is ignored by the planner.
 */
final class PostgresVecIndex
{
    private const VALID_INDEX_TYPES = ['hnsw', 'ivfflat'];

    public function __construct(
        private PostgresStore $store,
    ) {}

    public function createIndex(string $collection, string $indexType = 'hnsw', string $metric = 'cosine'): void
    {
        $this->store->pdo()->exec(self::buildCreateIndexSql($collection, $indexType, $metric));
    }

    public function dropIndex(string $collection, string $indexType = 'hnsw'): void
    {
        $table = PostgresStore::vectorTableName($collection);
        $this->store->pdo()->exec(\sprintf('DROP INDEX IF EXISTS "%s_%s"', $table, $indexType));
    }

    public static function opClass(string $metric): string
    {
        return match ($metric) {
            'cosine' => 'vector_cosine_ops',
            'euclidean' => 'vector_l2_ops',
            'dot' => 'vector_ip_ops',
            default => throw new ValidationException(\sprintf(
                'Unknown distance metric "%s": expected one of cosine, euclidean, dot.',
                $metric,
            )),
        };
    }

    public static function buildCreateIndexSql(string $collection, string $indexType, string $metric): string
    {
        if (!\in_array($indexType, self::VALID_INDEX_TYPES, true)) {
            throw new ValidationException(\sprintf(
                'Unknown index type "%s": expected one of %s.',
                $indexType,
                \implode(', ', self::VALID_INDEX_TYPES),
            ));
        }

        $table = PostgresStore::vectorTableName($collection);
        $opClass = self::opClass($metric);

        $sql = \sprintf(
            'CREATE INDEX IF NOT EXISTS "%s_%s" ON "%s" USING %s (embedding %s)',
            $table,
            $indexType,
            $table,
            $indexType,
            $opClass,
        );

        if ($indexType === 'ivfflat') {
            $sql .= ' WITH (lists = 100)';
        }

        return $sql;
    }
}
