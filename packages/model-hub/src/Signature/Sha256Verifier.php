<?php

declare(strict_types=1);

namespace FerryAI\ModelHub\Signature;

final class Sha256Verifier
{
    public static function compute(string $path): string
    {
        $hash = \hash_file('sha256', $path);

        return \is_string($hash) ? $hash : '';
    }

    public static function verify(string $path, string $expectedHash): bool
    {
        return \hash_equals(self::compute($path), $expectedHash);
    }

    public static function verifyFile(string $path, string $sha256Path): bool
    {
        if (!\file_exists($sha256Path)) {
            return false;
        }

        $content = \file_get_contents($sha256Path);

        if (!\is_string($content)) {
            return false;
        }

        // Accept both a bare hash and the standard `sha256sum` format ("<hash>  <filename>").
        $expected = (string) \strtok(\trim($content), " \t\r\n");

        return self::verify($path, $expected);
    }
}
