<?php

declare(strict_types=1);

namespace FerryAI\Core\FFI;

/**
 * Turns a C header into an `\FFI::cdef()`-compatible declaration string.
 *
 * This is a pragmatic cleaner, not a full C parser: it strips comments,
 * preprocessor directives, `extern "C"` wrappers, GCC/MSVC attributes and
 * user-listed export macros (e.g. `LLAMA_API`), then rebalances the brace left
 * dangling by the removed `extern "C" {`. What remains — typedefs, structs,
 * enums and function prototypes — is what FFI needs.
 *
 * Limitations: function-like macros keep their
 * argument list; `#define` integer constants are dropped (FFI ignores them);
 * fixed-width types rely on FFI's built-in `stdint` knowledge.
 */
final class CdefGenerator
{
    /**
     * @param string[] $stripMacros export/attribute macros to remove (bare tokens), e.g. ['LLAMA_API', 'GGML_API']
     */
    public function generate(string $header, array $stripMacros = []): string
    {
        $source = self::stripComments($header);
        $source = self::stripPreprocessor($source);
        $source = self::stripAttributes($source);
        $source = self::stripExternC($source);
        $source = self::stripMacros($source, $stripMacros);
        $source = self::balanceBraces($source);
        $source = self::normalizeSpacing($source);
        $source = self::collapseBlankLines($source);

        return \trim($source) . "\n";
    }

    private static function stripComments(string $source): string
    {
        $source = \preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;

        return \preg_replace('#//[^\n]*#', '', $source) ?? $source;
    }

    private static function stripPreprocessor(string $source): string
    {
        $lines = \preg_split('/\r\n|\n|\r/', $source) ?: [];
        $out = [];
        $continued = false;

        foreach ($lines as $line) {
            if ($continued) {
                $continued = \str_ends_with(\rtrim($line), '\\');

                continue;
            }

            if (\preg_match('/^\s*#/', $line) === 1) {
                $continued = \str_ends_with(\rtrim($line), '\\');

                continue;
            }

            $out[] = $line;
        }

        return \implode("\n", $out);
    }

    private static function stripAttributes(string $source): string
    {
        $source = \preg_replace('/__attribute__\s*\(\(.*?\)\)/s', '', $source) ?? $source;

        return \preg_replace('/__declspec\s*\([^)]*\)/', '', $source) ?? $source;
    }

    private static function stripExternC(string $source): string
    {
        return \preg_replace('/extern\s*"C"\s*\{?/', '', $source) ?? $source;
    }

    /**
     * @param string[] $stripMacros
     */
    private static function stripMacros(string $source, array $stripMacros): string
    {
        foreach ($stripMacros as $macro) {
            if ($macro === '') {
                continue;
            }

            $source = \preg_replace('/\b' . \preg_quote($macro, '/') . '\b/', '', $source) ?? $source;
        }

        return $source;
    }

    private static function balanceBraces(string $source): string
    {
        while (\substr_count($source, '}') > \substr_count($source, '{')) {
            $pos = \strrpos($source, '}');

            if ($pos === false) {
                break;
            }

            $source = \substr($source, 0, $pos) . \substr($source, $pos + 1);
        }

        return $source;
    }

    private static function normalizeSpacing(string $source): string
    {
        // Remove horizontal whitespace left before a semicolon (e.g. after stripping attributes).
        return \preg_replace('/[ \t]+;/', ';', $source) ?? $source;
    }

    private static function collapseBlankLines(string $source): string
    {
        $source = \preg_replace('/[ \t]+\n/', "\n", $source) ?? $source;

        return \preg_replace('/\n{3,}/', "\n\n", $source) ?? $source;
    }
}
