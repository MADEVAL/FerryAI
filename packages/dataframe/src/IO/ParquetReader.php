<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\IO;

use FerryAI\Core\Exception\IoException;
use FerryAI\DataFrame\DataFrame;

final class ParquetReader
{
    public function read(string $path): DataFrame
    {
        if (!\is_file($path) || !\is_readable($path)) {
            throw new IoException("Parquet file not found or not readable: '{$path}'");
        }

        $data = \file_get_contents($path);

        if ($data === false) {
            throw new IoException("Cannot read Parquet file: '{$path}'");
        }

        $size = \strlen($data);

        if ($size < 12 || \substr($data, 0, 4) !== 'PAR1' || \substr($data, $size - 4, 4) !== 'PAR1') {
            throw new IoException('Invalid Parquet file: missing PAR1 magic bytes.');
        }

        throw new IoException(
            'Parquet support is not yet implemented. '
            . 'The Parquet format requires a Thrift CompactProtocol decoder for metadata parsing. '
            . 'Use CSV or JSON for tabular data in the current version. '
            . 'Parquet support will be added in a future release.',
        );
    }
}
