<?php

declare(strict_types=1);

namespace FerryAI\Vector;

final class ExportImport
{
    public static function toJson(Collection $collection, string $path): void
    {
        $handle = \fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException(\sprintf('Cannot open file for writing: %s', $path));
        }

        foreach ($collection->iterator() as $item) {
            $json = \json_encode($item, JSON_UNESCAPED_UNICODE);
            \fwrite($handle, (\is_string($json) ? $json : '') . "\n");
        }

        \fclose($handle);
    }

    public static function fromJson(string $path, string $collectionName, int $dimension, SQLiteStore $store): Collection
    {
        $handle = \fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException(\sprintf('Cannot open file for reading: %s', $path));
        }

        $store->createCollection($collectionName, $dimension);
        $collection = new Collection($collectionName, $dimension, $store);

        while (($line = \fgets($handle)) !== false) {
            $line = \trim($line);

            if ($line === '') {
                continue;
            }

            $data = \json_decode($line, true);

            if (!\is_array($data) || !isset($data['id'], $data['vector'])) {
                continue;
            }

            $collection->add((string) $data['id'], $data['vector'], $data['metadata'] ?? null);
        }

        \fclose($handle);

        return $collection;
    }

    public static function toCsv(Collection $collection, string $path): void
    {
        $handle = \fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException(\sprintf('Cannot open file for writing: %s', $path));
        }

        \fwrite($handle, "id,vector,metadata\n");

        foreach ($collection->iterator() as $item) {
            $vectorStr = \implode(' ', $item['vector']);
            $metadataStr = \json_encode($item['metadata'] ?? [], JSON_UNESCAPED_UNICODE);
            $metadataStr = \is_string($metadataStr) ? $metadataStr : '{}';
            \fwrite($handle, \sprintf("%s,\"%s\",\"%s\"\n", $item['id'], $vectorStr, $metadataStr));
        }

        \fclose($handle);
    }
}
