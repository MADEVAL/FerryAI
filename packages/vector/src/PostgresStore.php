<?php

declare(strict_types=1);

namespace FerryAI\Vector;

use FerryAI\Core\Exception\ValidationException;

/**
 * PDO-backed vector store using PostgreSQL + the pgvector extension.
 *
 * Mirrors the multi-collection storage surface of {@see SQLiteStore} but stores
 * embeddings in native `vector(dim)` columns and performs nearest-neighbour
 * search through pgvector distance operators instead of a PHP brute-force scan.
 *
 * Vectors are exchanged as `float[]` (not packed blobs) because pgvector expects
 * its own textual `[a,b,c]` literal on input and returns the same on output.
 */
final class PostgresStore
{
    private const VALID_METRICS = ['cosine', 'euclidean', 'dot'];

    private \PDO $pdo;

    public function __construct(
        string $dsn,
        string $user = '',
        string $password = '',
        ?\PDO $pdo = null,
    ) {
        $this->pdo = $pdo ?? new \PDO($dsn, $user, $password);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE EXTENSION IF NOT EXISTS vector');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ferry_collections (
            name TEXT PRIMARY KEY,
            dimension INTEGER NOT NULL,
            metric TEXT NOT NULL DEFAULT \'cosine\',
            index_type TEXT NOT NULL DEFAULT \'flat\',
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    public function createCollection(string $name, int $dimension, string $metric = 'cosine', string $indexType = 'flat'): void
    {
        self::assertMetric($metric);
        $table = self::vectorTableName($name);

        $stmt = $this->pdo->prepare(
            'INSERT INTO ferry_collections (name, dimension, metric, index_type)
             VALUES (:name, :dimension, :metric, :index_type)
             ON CONFLICT (name) DO NOTHING',
        );
        $stmt->execute([
            ':name' => $name,
            ':dimension' => $dimension,
            ':metric' => $metric,
            ':index_type' => $indexType,
        ]);

        $this->pdo->exec(\sprintf(
            'CREATE TABLE IF NOT EXISTS "%s" (
                id TEXT PRIMARY KEY,
                embedding vector(%d) NOT NULL,
                metadata JSONB,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )',
            $table,
            $dimension,
        ));
    }

    public function collectionExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM ferry_collections WHERE name = :name');
        $stmt->execute([':name' => $name]);

        return (bool) $stmt->fetchColumn();
    }

    public function getDimension(string $name): ?int
    {
        $stmt = $this->pdo->prepare('SELECT dimension FROM ferry_collections WHERE name = :name');
        $stmt->execute([':name' => $name]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    public function getMetric(string $name): string
    {
        $stmt = $this->pdo->prepare('SELECT metric FROM ferry_collections WHERE name = :name');
        $stmt->execute([':name' => $name]);
        $value = $stmt->fetchColumn();

        return \is_string($value) ? $value : 'cosine';
    }

    /**
     * @param array<int, float>         $vector
     * @param array<string, mixed>|null $metadata
     */
    public function insertVector(string $collection, string $id, array $vector, ?array $metadata = null): void
    {
        $table = self::vectorTableName($collection);
        $metadataJson = $metadata !== null ? \json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $this->pdo->prepare(\sprintf(
            'INSERT INTO "%s" (id, embedding, metadata) VALUES (:id, :embedding, :metadata)
             ON CONFLICT (id) DO UPDATE SET embedding = EXCLUDED.embedding, metadata = EXCLUDED.metadata',
            $table,
        ));
        $stmt->execute([
            ':id' => $id,
            ':embedding' => self::vectorToLiteral($vector),
            ':metadata' => \is_string($metadataJson) ? $metadataJson : null,
        ]);
    }

    /**
     * @return array{id: string, vector: array<int, float>, metadata: array<string, mixed>|null}|null
     */
    public function getVector(string $collection, string $id): ?array
    {
        $table = self::vectorTableName($collection);
        $stmt = $this->pdo->prepare(\sprintf('SELECT id, embedding, metadata FROM "%s" WHERE id = :id', $table));
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'vector' => self::literalToVector((string) $row['embedding']),
            'metadata' => self::decodeMetadata($row['metadata']),
        ];
    }

    public function deleteVector(string $collection, string $id): void
    {
        $table = self::vectorTableName($collection);
        $stmt = $this->pdo->prepare(\sprintf('DELETE FROM "%s" WHERE id = :id', $table));
        $stmt->execute([':id' => $id]);
    }

    public function countVectors(string $collection): int
    {
        $table = self::vectorTableName($collection);
        $stmt = $this->pdo->query(\sprintf('SELECT COUNT(*) FROM "%s"', $table));

        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return \Generator<array{id: string, vector: array<int, float>, metadata: array<string, mixed>|null}>
     */
    public function iterateVectors(string $collection): \Generator
    {
        $table = self::vectorTableName($collection);
        $stmt = $this->pdo->query(\sprintf('SELECT id, embedding, metadata FROM "%s"', $table));

        if ($stmt === false) {
            return;
        }

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield [
                'id' => (string) $row['id'],
                'vector' => self::literalToVector((string) $row['embedding']),
                'metadata' => self::decodeMetadata($row['metadata']),
            ];
        }
    }

    public function clearCollection(string $collection): void
    {
        $table = self::vectorTableName($collection);
        $this->pdo->exec(\sprintf('TRUNCATE TABLE "%s"', $table));
    }

    public function dropCollection(string $name): void
    {
        $table = self::vectorTableName($name);
        $this->pdo->exec(\sprintf('DROP TABLE IF EXISTS "%s"', $table));
        $stmt = $this->pdo->prepare('DELETE FROM ferry_collections WHERE name = :name');
        $stmt->execute([':name' => $name]);
    }

    /**
     * @return string[]
     */
    public function listCollections(): array
    {
        $stmt = $this->pdo->query('SELECT name FROM ferry_collections ORDER BY name');

        if ($stmt === false) {
            return [];
        }

        $names = [];

        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $name) {
            $names[] = (string) $name;
        }

        return $names;
    }

    /**
     * Nearest-neighbour search using the native pgvector distance operator.
     *
     * @param array<int, float> $queryVector
     * @param string[]|null     $restrictIds When provided, only these ids are considered
     *
     * @return array<int, array{id: string, distance: float, metadata: array<string, mixed>}>
     */
    public function search(string $collection, array $queryVector, int $k, string $metric = 'cosine', ?array $restrictIds = null): array
    {
        $table = self::vectorTableName($collection);
        $operator = self::distanceOperator($metric);

        $where = '';
        $params = [':query' => self::vectorToLiteral($queryVector)];

        if ($restrictIds !== null) {
            if ($restrictIds === []) {
                return [];
            }

            $placeholders = [];

            foreach (\array_values($restrictIds) as $i => $rid) {
                $key = ':id' . $i;
                $placeholders[] = $key;
                $params[$key] = $rid;
            }

            $where = ' WHERE id IN (' . \implode(', ', $placeholders) . ')';
        }

        $sql = \sprintf(
            'SELECT id, metadata, embedding %s :query AS distance FROM "%s"%s ORDER BY distance ASC LIMIT %d',
            $operator,
            $table,
            $where,
            $k,
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $results = [];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'id' => (string) $row['id'],
                'distance' => (float) $row['distance'],
                'metadata' => self::decodeMetadata($row['metadata']) ?? [],
            ];
        }

        return $results;
    }

    public static function vectorTableName(string $collection): string
    {
        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $collection) !== 1) {
            throw new ValidationException(\sprintf(
                'Invalid collection name "%s": only letters, digits and underscores are allowed, '
                . 'and the name must not start with a digit. Rename the collection to a valid SQL identifier.',
                $collection,
            ));
        }

        return 'vectors_' . $collection;
    }

    /**
     * @param array<int, float> $vector
     */
    public static function vectorToLiteral(array $vector): string
    {
        $parts = [];

        foreach ($vector as $value) {
            $parts[] = self::formatFloat($value);
        }

        return '[' . \implode(',', $parts) . ']';
    }

    public static function distanceOperator(string $metric): string
    {
        return match ($metric) {
            'cosine' => '<=>',
            'euclidean' => '<->',
            'dot' => '<#>',
            default => throw new ValidationException(\sprintf(
                'Unknown distance metric "%s": expected one of cosine, euclidean, dot.',
                $metric,
            )),
        };
    }

    private static function assertMetric(string $metric): void
    {
        if (!\in_array($metric, self::VALID_METRICS, true)) {
            throw new ValidationException(\sprintf(
                'Unknown distance metric "%s": expected one of %s.',
                $metric,
                \implode(', ', self::VALID_METRICS),
            ));
        }
    }

    private static function formatFloat(float $value): string
    {
        if ($value === (float) (int) $value && \abs($value) < 1e15) {
            return (string) (int) $value;
        }

        return \rtrim(\rtrim(\sprintf('%.8f', $value), '0'), '.');
    }

    /**
     * @return array<int, float>
     */
    private static function literalToVector(string $literal): array
    {
        $trimmed = \trim($literal, "[] \t\n\r");

        if ($trimmed === '') {
            return [];
        }

        return \array_map(static fn(string $v): float => (float) $v, \explode(',', $trimmed));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeMetadata(mixed $metadata): ?array
    {
        if (!\is_string($metadata) || $metadata === '') {
            return null;
        }

        $decoded = \json_decode($metadata, true);

        return \is_array($decoded) ? $decoded : null;
    }
}
