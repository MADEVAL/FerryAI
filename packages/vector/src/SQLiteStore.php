<?php

declare(strict_types=1);

namespace FerryAI\Vector;

use FerryAI\Core\Exception\ValidationException;

final class SQLiteStore
{
    private \PDO $pdo;

    public function __construct(string $dbPath)
    {
        $this->pdo = \class_exists(\Pdo\Sqlite::class)
            ? \Pdo\Sqlite::connect('sqlite:' . $dbPath)
            : new \PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE IF NOT EXISTS collections (
            name TEXT PRIMARY KEY,
            dimension INTEGER NOT NULL,
            metric TEXT DEFAULT \'cosine\',
            index_type TEXT DEFAULT \'flat\',
            created_at TEXT DEFAULT (datetime(\'now\'))
        )');
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    public function createCollection(string $name, int $dimension, string $metric = 'cosine', string $indexType = 'flat'): void
    {
        $tableName = $this->vectorTableName($name);

        $stmt = $this->pdo->prepare('INSERT INTO collections (name, dimension, metric, index_type) VALUES (:name, :dimension, :metric, :index_type)');
        $stmt->execute([
            ':name' => $name,
            ':dimension' => $dimension,
            ':metric' => $metric,
            ':index_type' => $indexType,
        ]);

        $this->pdo->exec(\sprintf(
            'CREATE TABLE IF NOT EXISTS "%s" (
                id TEXT PRIMARY KEY,
                vector BLOB NOT NULL,
                metadata TEXT,
                created_at TEXT DEFAULT (datetime(\'now\'))
            )',
            $tableName,
        ));
    }

    public function collectionExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM collections WHERE name = :name');
        $stmt->execute([':name' => $name]);

        return (bool) $stmt->fetchColumn();
    }

    public function insertVector(string $collection, string $id, string $vectorBlob, ?string $metadata = null): void
    {
        $tableName = $this->vectorTableName($collection);
        $stmt = $this->pdo->prepare(\sprintf(
            'INSERT OR REPLACE INTO "%s" (id, vector, metadata) VALUES (:id, :vector, :metadata)',
            $tableName,
        ));
        $stmt->execute([
            ':id' => $id,
            ':vector' => $vectorBlob,
            ':metadata' => $metadata,
        ]);
    }

    /**
     * @return array{id: string, vector: string, metadata: string|null}|null
     */
    public function getVector(string $collection, string $id): ?array
    {
        $tableName = $this->vectorTableName($collection);
        $stmt = $this->pdo->prepare(\sprintf('SELECT id, vector, metadata FROM "%s" WHERE id = :id', $tableName));
        $stmt->execute([':id' => $id]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        return $result;
    }

    public function deleteVector(string $collection, string $id): void
    {
        $tableName = $this->vectorTableName($collection);
        $stmt = $this->pdo->prepare(\sprintf('DELETE FROM "%s" WHERE id = :id', $tableName));
        $stmt->execute([':id' => $id]);
    }

    public function countVectors(string $collection): int
    {
        $tableName = $this->vectorTableName($collection);
        $stmt = $this->pdo->query(\sprintf('SELECT COUNT(*) FROM "%s"', $tableName));

        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return \Generator<array{id: string, vector: string, metadata: string|null}>
     */
    public function iterateVectors(string $collection): \Generator
    {
        $tableName = $this->vectorTableName($collection);
        $stmt = $this->pdo->query(\sprintf('SELECT id, vector, metadata FROM "%s"', $tableName));

        if ($stmt === false) {
            return;
        }

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    public function clearCollection(string $collection): void
    {
        $tableName = $this->vectorTableName($collection);
        $this->pdo->exec(\sprintf('DELETE FROM "%s"', $tableName));
    }

    /**
     * @param  array<string, mixed>             $params
     * @return array<int, array<string, mixed>>
     */
    public function rawQuery(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function vectorTableName(string $collection): string
    {
        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $collection) !== 1) {
            throw new ValidationException(\sprintf(
                'Invalid collection name "%s": only letters, digits and underscores are allowed, '
                . 'and it must not start with a digit.',
                $collection,
            ));
        }

        return 'vectors_' . $collection;
    }
}
