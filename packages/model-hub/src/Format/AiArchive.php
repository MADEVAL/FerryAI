<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Format;

use FerryAI\Core\Exception\IoException;

final class AiArchive
{
    /**
     * @param array<string, string> $files name => sourcePath
     */
    public static function create(string $outputPath, array $files): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new IoException(\sprintf('Cannot create archive: %s', $outputPath));
        }

        foreach ($files as $name => $sourcePath) {
            $zip->addFile($sourcePath, $name);
        }

        $zip->close();
    }

    /**
     * @return array<string, string> type => extractedPath
     */
    public static function extract(string $archivePath, string $outputDir): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new IoException(\sprintf('Cannot open archive: %s', $archivePath));
        }

        $extracted = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false) {
                continue;
            }

            $targetPath = $outputDir . '/' . \basename($name);
            \copy('zip://' . $archivePath . '#' . $name, $targetPath);
            $extracted[$name] = $targetPath;
        }

        $zip->close();

        return $extracted;
    }

    /**
     * @return string[]
     */
    public static function list(string $archivePath): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new IoException(\sprintf('Cannot open archive: %s', $archivePath));
        }

        $files = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name !== false) {
                $files[] = $name;
            }
        }

        $zip->close();

        return $files;
    }

    public static function validate(string $archivePath): bool
    {
        $files = self::list($archivePath);

        foreach ($files as $file) {
            if (\str_contains($file, 'model.onnx') || \str_contains($file, 'model.gguf') || \str_contains($file, 'model.rbm')) {
                return true;
            }
        }

        return false;
    }
}
