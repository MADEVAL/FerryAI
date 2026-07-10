<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\IO;

use FerryAI\Core\Exception\IoException;
use FerryAI\DataFrame\Column;
use FerryAI\DataFrame\DataFrame;

final class JsonReader
{
    public function read(string $path): DataFrame
    {
        if (!\is_file($path) || !\is_readable($path)) {
            throw new IoException("JSON file not found or not readable: '{$path}'");
        }

        $content = \file_get_contents($path);

        if ($content === false || \trim($content) === '') {
            return new DataFrame();
        }

        $trimmed = \trim($content);

        if (\str_starts_with($trimmed, '[')) {
            return $this->readArrayOfObjects($trimmed);
        }

        return $this->readNdjson($trimmed);
    }

    private function readArrayOfObjects(string $json): DataFrame
    {
        try {
            $data = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new IoException('Invalid JSON: ' . $e->getMessage());
        }

        if (!\is_array($data) || $data === []) {
            return new DataFrame();
        }

        return $this->rowsToDataFrame($data);
    }

    private function readNdjson(string $content): DataFrame
    {
        $lines = \explode("\n", $content);
        $rows = [];

        foreach ($lines as $line) {
            $line = \trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $row = \json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (\is_array($row)) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return new DataFrame();
        }

        return $this->rowsToDataFrame($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function rowsToDataFrame(array $rows): DataFrame
    {
        $allKeys = [];

        foreach ($rows as $row) {
            foreach (\array_keys($row) as $key) {
                $allKeys[$key] = true;
            }
        }

        $colNames = \array_keys($allKeys);
        $pivoted = \array_fill_keys($colNames, []);

        foreach ($rows as $row) {
            foreach ($colNames as $name) {
                $pivoted[$name][] = $row[$name] ?? null;
            }
        }

        $columns = [];

        foreach ($colNames as $name) {
            $data = $pivoted[$name];
            $columns[] = new Column($name, Column::inferType($data), $data);
        }

        return new DataFrame(...$columns);
    }
}
