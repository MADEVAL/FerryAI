<?php

declare(strict_types=1);

namespace FerryAI\Vector;

use FerryAI\Core\Exception\InvalidStateException;

final class CollectionManager
{
    public function __construct(
        private SQLiteStore $store,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function create(string $name, int $dimension, array $options = []): Collection
    {
        $metric = \is_string($options['metric'] ?? null) ? $options['metric'] : 'cosine';

        if (!$this->store->collectionExists($name)) {
            $this->store->createCollection($name, $dimension, $metric);
        }

        return new Collection($name, $dimension, $this->store, $metric);
    }

    public function open(string $name): Collection
    {
        $rows = $this->store->rawQuery(
            'SELECT dimension, metric FROM collections WHERE name = :name',
            [':name' => $name],
        );

        if ($rows === []) {
            throw new InvalidStateException(\sprintf('Collection "%s" does not exist', $name));
        }

        $dimension = (int) $rows[0]['dimension'];
        $metric = \is_string($rows[0]['metric'] ?? null) ? $rows[0]['metric'] : 'cosine';

        return new Collection($name, $dimension, $this->store, $metric);
    }

    public function delete(string $name): void
    {
        if ($this->store->collectionExists($name)) {
            $this->store->clearCollection($name);
            $this->store->rawQuery('DELETE FROM collections WHERE name = :name', [':name' => $name]);
        }
    }

    /**
     * @return string[]
     */
    public function list(): array
    {
        $rows = $this->store->rawQuery('SELECT name FROM collections ORDER BY name');
        $names = [];

        foreach ($rows as $row) {
            $names[] = $row['name'];
        }

        return $names;
    }

    public function exists(string $name): bool
    {
        return $this->store->collectionExists($name);
    }
}
