<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\IO;

use FerryAI\Core\Exception\IoException;
use FerryAI\DataFrame\DataFrame;

final class CsvWriter
{
    public function write(DataFrame $df, string $path, bool $includeHeader = true): void
    {
        $dir = \dirname($path);

        if ($dir !== '.' && !\is_dir($dir)) {
            \mkdir($dir, 0755, true);
        }

        $handle = \fopen($path, 'wb');

        if ($handle === false) {
            throw new IoException("Cannot open CSV file for writing: '{$path}'");
        }

        if ($includeHeader) {
            \fputcsv($handle, $df->columns(), ',', '"', '\\');
        }

        for ($i = 0; $i < $df->numRows(); ++$i) {
            $row = $df->row($i);
            \fputcsv($handle, \array_values($row), ',', '"', '\\');
        }

        \fclose($handle);
    }
}
