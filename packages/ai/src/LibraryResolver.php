<?php

declare(strict_types=1);

namespace FerryAI;

/**
 * Resolves the on-disk path of a native shared library by logical name
 * (e.g. `llama`, `onnxruntime`). Implemented by {@see NativeBinaryManager}.
 */
interface LibraryResolver
{
    /**
     * @return string|null Absolute path to the library, or null when it cannot be located
     */
    public function resolve(string $library): ?string;
}
