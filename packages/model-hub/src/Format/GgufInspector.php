<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Format;

use FerryAI\Core\ValueObjects\ModelMetadata;

final class GgufInspector
{
    public static function inspect(string $path): ModelMetadata
    {
        $name = \pathinfo($path, PATHINFO_FILENAME);

        return new ModelMetadata(
            name: $name,
            version: 'unknown',
            author: 'unknown',
            license: 'unknown',
            tags: [],
            sizeBytes: \file_exists($path) ? (int) \filesize($path) : 0,
        );
    }

    /**
     * Reads GGUF metadata key-value pairs.
     *
     * @return array<string, mixed>
     */
    public static function metadata(string $path): array
    {
        if (!\file_exists($path)) {
            return [];
        }

        $handle = \fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $magic = self::readBytes($handle, 4);

        if ($magic !== 'GGUF') {
            \fclose($handle);

            return [];
        }

        $version = self::readUint32($handle);

        if ($version < 2 || $version > 3) {
            \fclose($handle);

            return [];
        }

        self::readUint64($handle);
        $kvCount = self::readUint64($handle);

        $metadata = [];

        for ($i = 0; $i < $kvCount; $i++) {
            $key = self::readString($handle);
            $valueType = self::readUint32($handle);
            $metadata[$key] = self::readValue($handle, $valueType);
        }

        \fclose($handle);

        return $metadata;
    }

    public static function sizeBytes(string $path): int
    {
        if (!\file_exists($path)) {
            return 0;
        }

        return (int) \filesize($path);
    }

    /**
     * @param resource $handle
     */
    private static function readBytes($handle, int $length): string
    {
        $data = \fread($handle, $length);

        return $data === false ? '' : $data;
    }

    /**
     * @param resource $handle
     */
    private static function readUint32($handle): int
    {
        $data = self::readBytes($handle, 4);

        if (\strlen($data) < 4) {
            return 0;
        }

        $unpacked = \unpack('V', $data);

        return \is_array($unpacked) ? (int) ($unpacked[1] ?? 0) : 0;
    }

    /**
     * @param resource $handle
     */
    private static function readUint64($handle): int
    {
        $data = self::readBytes($handle, 8);

        if (\strlen($data) < 8) {
            return 0;
        }

        $unpacked = \unpack('P', $data);

        return \is_array($unpacked) ? (int) ($unpacked[1] ?? 0) : 0;
    }

    /**
     * @param resource $handle
     */
    private static function readString($handle): string
    {
        $length = self::readUint64($handle);

        if ($length === 0) {
            return '';
        }

        $data = self::readBytes($handle, (int) $length);

        $pos = \ftell($handle);

        if ($pos !== false) {
            $align = (8 - ($pos % 8)) % 8;
            \fseek($handle, $align, \SEEK_CUR);
        }

        return $data;
    }

    /**
     * @param resource $handle
     */
    private static function readValue($handle, int $type): mixed
    {
        return match ($type) {
            0 => \ord(self::readBytes($handle, 1)),
            1 => self::readBytes($handle, 1) === '' ? 0 : \unpack('c', self::readBytes($handle, 1))[1] ?? 0,
            2 => self::readUint16($handle),
            3 => self::readInt16($handle),
            4 => self::readUint32($handle),
            5 => self::readInt32($handle),
            6 => self::readFloat32($handle),
            7 => (bool) \ord(self::readBytes($handle, 1)),
            8 => self::readString($handle),
            9 => self::readArray($handle),
            10 => self::readUint64($handle),
            11 => self::readInt64($handle),
            12 => self::readFloat64($handle),
            default => null,
        };
    }

    /**
     * @param resource $handle
     */
    private static function readUint16($handle): int
    {
        $data = self::readBytes($handle, 2);

        if (\strlen($data) < 2) {
            return 0;
        }

        $unpacked = \unpack('v', $data);

        return \is_array($unpacked) ? (int) ($unpacked[1] ?? 0) : 0;
    }

    /**
     * @param resource $handle
     */
    private static function readInt16($handle): int
    {
        $data = self::readBytes($handle, 2);

        if (\strlen($data) < 2) {
            return 0;
        }

        $unpacked = \unpack('s', $data);
        $values = \array_values($unpacked);

        return (int) ($values[0] ?? 0);
    }

    /**
     * @param resource $handle
     */
    private static function readInt32($handle): int
    {
        $data = self::readBytes($handle, 4);

        if (\strlen($data) < 4) {
            return 0;
        }

        $unpacked = \unpack('l', $data);

        return \is_array($unpacked) ? (int) ($unpacked[1] ?? 0) : 0;
    }

    /**
     * @param resource $handle
     */
    private static function readInt64($handle): int
    {
        $data = self::readBytes($handle, 8);

        if (\strlen($data) < 8) {
            return 0;
        }

        $unpacked = \unpack('q', $data);

        return \is_array($unpacked) ? (int) ($unpacked[1] ?? 0) : 0;
    }

    /**
     * @param resource $handle
     */
    private static function readFloat32($handle): float
    {
        $data = self::readBytes($handle, 4);

        if (\strlen($data) < 4) {
            return 0.0;
        }

        $unpacked = \unpack('g', $data);

        return \is_array($unpacked) ? (float) ($unpacked[1] ?? 0.0) : 0.0;
    }

    /**
     * @param resource $handle
     */
    private static function readFloat64($handle): float
    {
        $data = self::readBytes($handle, 8);

        if (\strlen($data) < 8) {
            return 0.0;
        }

        $unpacked = \unpack('e', $data);

        return \is_array($unpacked) ? (float) ($unpacked[1] ?? 0.0) : 0.0;
    }

    /**
     * @param resource $handle
     *
     * @return array<int, mixed>
     */
    private static function readArray($handle): array
    {
        $elementType = self::readUint32($handle);
        $length = self::readUint64($handle);

        $values = [];

        for ($i = 0; $i < $length; $i++) {
            $values[] = self::readValue($handle, $elementType);
        }

        return $values;
    }
}
