<?php

declare(strict_types=1);

namespace FerryAI\Vector;

/**
 * Optional ANN acceleration backed by the sqlite-vec loadable extension (vec0).
 *
 * Enabled only when FERRY_AI_VEC_EXTENSION_LIB points at the sqlite-vec shared
 * library and the connection is a {@see \Pdo\Sqlite} (PHP 8.4+, which exposes
 * loadExtension). When unavailable, every method is a graceful no-op / empty
 * result and callers fall back to {@see BruteForceIndex}.
 *
 * vec0 virtual tables key rows by integer rowid, so a side table maps the store's
 * TEXT ids to integer rowids: `vecmap_{collection}(rowid, ext_id)` alongside the
 * vector table `vec_{collection}(embedding float[dim] distance_metric=...)`.
 */
final class SqliteVecExtension
{
    private bool $loaded = false;

    public function isAvailable(): bool
    {
        return \class_exists(\Pdo\Sqlite::class) && $this->libraryPath() !== null;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    public function load(SQLiteStore $store): bool
    {
        $lib = $this->libraryPath();

        if ($lib === null || !\class_exists(\Pdo\Sqlite::class)) {
            $this->loaded = false;

            return false;
        }

        $pdo = $store->pdo();

        if (!$pdo instanceof \Pdo\Sqlite) {
            $this->loaded = false;

            return false;
        }

        try {
            $pdo->loadExtension($lib);
            $this->loaded = true;
        } catch (\Throwable) {
            $this->loaded = false;
        }

        return $this->loaded;
    }

    public function createIndex(SQLiteStore $store, string $collection, int $dimension, string $metric = 'cosine'): void
    {
        if (!$this->loaded || $dimension < 1) {
            return;
        }

        $pdo = $store->pdo();

        $pdo->exec(\sprintf(
            'CREATE VIRTUAL TABLE IF NOT EXISTS "%s" USING vec0(embedding float[%d] distance_metric=%s)',
            self::vecTable($collection),
            $dimension,
            self::metricKeyword($metric),
        ));
        $pdo->exec(\sprintf(
            'CREATE TABLE IF NOT EXISTS "%s" (rowid INTEGER PRIMARY KEY AUTOINCREMENT, ext_id TEXT UNIQUE)',
            self::mapTable($collection),
        ));
    }

    /**
     * @param array<int, float> $vector
     */
    public function upsert(SQLiteStore $store, string $collection, string $id, array $vector): void
    {
        if (!$this->loaded) {
            return;
        }

        $pdo = $store->pdo();
        $rowid = $this->rowidFor($store, $collection, $id, true);

        $delete = $pdo->prepare(\sprintf('DELETE FROM "%s" WHERE rowid = :rowid', self::vecTable($collection)));
        $delete->bindValue(':rowid', $rowid, \PDO::PARAM_INT);
        $delete->execute();

        $insert = $pdo->prepare(\sprintf('INSERT INTO "%s"(rowid, embedding) VALUES (:rowid, :emb)', self::vecTable($collection)));
        $insert->bindValue(':rowid', $rowid, \PDO::PARAM_INT);
        $insert->bindValue(':emb', self::encodeVector($vector));
        $insert->execute();
    }

    public function remove(SQLiteStore $store, string $collection, string $id): void
    {
        if (!$this->loaded) {
            return;
        }

        $rowid = $this->rowidFor($store, $collection, $id, false);

        if ($rowid === null) {
            return;
        }

        $pdo = $store->pdo();

        $deleteVec = $pdo->prepare(\sprintf('DELETE FROM "%s" WHERE rowid = :rowid', self::vecTable($collection)));
        $deleteVec->bindValue(':rowid', $rowid, \PDO::PARAM_INT);
        $deleteVec->execute();

        $deleteMap = $pdo->prepare(\sprintf('DELETE FROM "%s" WHERE ext_id = :id', self::mapTable($collection)));
        $deleteMap->bindValue(':id', $id);
        $deleteMap->execute();
    }

    public function clear(SQLiteStore $store, string $collection): void
    {
        if (!$this->loaded) {
            return;
        }

        $pdo = $store->pdo();
        $pdo->exec(\sprintf('DELETE FROM "%s"', self::vecTable($collection)));
        $pdo->exec(\sprintf('DELETE FROM "%s"', self::mapTable($collection)));
    }

    /**
     * @param array<int, float> $vector
     *
     * @return array<int, array{id: string, distance: float}>
     */
    public function search(SQLiteStore $store, string $collection, array $vector, int $k): array
    {
        if (!$this->loaded) {
            return [];
        }

        $sql = \sprintf(
            'SELECT m.ext_id AS id, v.distance AS distance
             FROM "%s" v JOIN "%s" m ON m.rowid = v.rowid
             WHERE v.embedding MATCH :q AND k = :k
             ORDER BY v.distance',
            self::vecTable($collection),
            self::mapTable($collection),
        );

        $stmt = $store->pdo()->prepare($sql);
        $stmt->bindValue(':q', self::encodeVector($vector));
        $stmt->bindValue(':k', $k, \PDO::PARAM_INT);
        $stmt->execute();

        $results = [];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $results[] = ['id' => (string) $row['id'], 'distance' => (float) $row['distance']];
        }

        return $results;
    }

    private function rowidFor(SQLiteStore $store, string $collection, string $id, bool $create): ?int
    {
        $pdo = $store->pdo();
        $select = $pdo->prepare(\sprintf('SELECT rowid FROM "%s" WHERE ext_id = :id', self::mapTable($collection)));
        $select->bindValue(':id', $id);
        $select->execute();
        $rowid = $select->fetchColumn();

        if ($rowid !== false) {
            return (int) $rowid;
        }

        if (!$create) {
            return null;
        }

        $insert = $pdo->prepare(\sprintf('INSERT INTO "%s"(ext_id) VALUES (:id)', self::mapTable($collection)));
        $insert->bindValue(':id', $id);
        $insert->execute();

        return (int) $pdo->lastInsertId();
    }

    private function libraryPath(): ?string
    {
        $lib = \getenv('FERRY_AI_VEC_EXTENSION_LIB');

        if ($lib === false || $lib === '' || !\is_file($lib)) {
            return null;
        }

        return $lib;
    }

    /**
     * @param array<int, float> $vector
     */
    private static function encodeVector(array $vector): string
    {
        return \json_encode(\array_map(static fn(float $v): float => $v, \array_values($vector)), JSON_THROW_ON_ERROR);
    }

    private static function metricKeyword(string $metric): string
    {
        return match ($metric) {
            'euclidean' => 'l2',
            default => 'cosine',
        };
    }

    private static function vecTable(string $collection): string
    {
        return 'vec_' . self::assertSafeName($collection);
    }

    private static function mapTable(string $collection): string
    {
        return 'vecmap_' . self::assertSafeName($collection);
    }

    private static function assertSafeName(string $collection): string
    {
        if (\preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $collection) !== 1) {
            throw new \FerryAI\Core\Exception\ValidationException(\sprintf(
                'Invalid collection name "%s": only letters, digits and underscores are allowed, '
                . 'and it must not start with a digit.',
                $collection,
            ));
        }

        return $collection;
    }
}
