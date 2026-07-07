<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\IO;

use FerryAI\Core\Exception\IoException;
use FerryAI\DataFrame\Column;
use FerryAI\DataFrame\DataFrame;

final class CsvReader
{
    public function read(string $path, bool $hasHeader = true): DataFrame
    {
        if (!\is_file($path) || !\is_readable($path)) {
            throw new IoException("CSV file not found or not readable: '{$path}'");
        }

        $handle = \fopen($path, 'rb');

        if ($handle === false) {
            throw new IoException("Cannot open CSV file: '{$path}'");
        }

        $rows = [];

        $delimiter = $this->detectDelimiter($path);

        while (($row = \fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $rows[] = $row;
        }

        \fclose($handle);

        if ($rows === []) {
            return new DataFrame();
        }

        $headers = $hasHeader ? \array_shift($rows) : null;
        $colCount = \count($rows[0] ?? []);

        if ($headers === null || \count($headers) === 0) {
            $headers = \array_map(static fn(int $i): string => "col_{$i}", \range(0, $colCount - 1));
        }

        $pivoted = \array_fill_keys($headers, []);

        foreach ($rows as $row) {
            foreach ($headers as $i => $name) {
                $value = $row[$i] ?? '';
                $pivoted[$name][] = $this->coerceValue((string) $value);
            }
        }

        $columns = [];

        foreach ($headers as $name) {
            $data = $pivoted[$name];
            $columns[] = new Column((string) $name, Column::inferType($data), $data);
        }

        return new DataFrame(...$columns);
    }

    private function detectDelimiter(string $path): string
    {
        $handle = \fopen($path, 'rb');

        if ($handle === false) {
            return ',';
        }

        $firstLine = \fgets($handle);
        \fclose($handle);

        if ($firstLine === false || $firstLine === '') {
            return ',';
        }

        $commaCount = \substr_count($firstLine, ',');
        $tabCount = \substr_count($firstLine, "\t");
        $semicolonCount = \substr_count($firstLine, ';');

        $max = \max($commaCount, $tabCount, $semicolonCount);

        if ($max === $tabCount && $max > 0) {
            return "\t";
        }

        if ($max === $semicolonCount && $max > 0) {
            return ';';
        }

        return ',';
    }

    private function coerceValue(string $value): string|int|float
    {
        if (\is_numeric($value)) {
            if (\str_contains($value, '.') || \stripos($value, 'e') !== false) {
                return (float) $value;
            }

            if ((string) (int) $value === $value) {
                return (int) $value;
            }

            return (float) $value;
        }

        return $value;
    }
}
