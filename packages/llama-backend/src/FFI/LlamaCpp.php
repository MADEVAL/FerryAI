<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\FFI;

/**
 * FFI definitions for the llama.cpp C API.
 *
 * This is the FFI boundary of the package (excluded from static analysis). Only ABI-safe primitives
 * are declared here (no struct-by-value): library resolution, a loadability probe and the runtime
 * version string. The full generation binding (model/context params are struct-by-value and vary by
 * build) is validated by the integration suite against a concrete llama.cpp shared library.
 */
final class LlamaCpp
{
    private const string CDEF = <<<'C'
        void llama_backend_init(void);
        const char * llama_print_system_info(void);
        C;

    private ?\FFI $ffi = null;

    public function __construct(private readonly ?string $libraryPath = null) {}

    /**
     * Resolves the llama.cpp shared library path from the environment.
     */
    public static function resolveLibraryPath(): ?string
    {
        $path = getenv('FERRY_AI_LLAMA_LIB');

        return $path === false || $path === '' ? null : $path;
    }

    /**
     * Whether the native library can be loaded in the current environment.
     */
    public function isLibraryLoadable(): bool
    {
        try {
            return $this->ffi() instanceof \FFI;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Returns the llama.cpp system/version info string, or a placeholder when unavailable.
     */
    public function version(): string
    {
        try {
            $info = ($this->ffi()->llama_print_system_info)();

            return \is_string($info) && $info !== '' ? $info : 'llama.cpp (native)';
        } catch (\Throwable) {
            return 'llama.cpp (native, unavailable)';
        }
    }

    private function ffi(): \FFI
    {
        if ($this->ffi instanceof \FFI) {
            return $this->ffi;
        }

        $path = $this->libraryPath ?? self::resolveLibraryPath();

        if ($path === null || !is_file($path)) {
            throw new \RuntimeException('llama.cpp shared library not found; set FERRY_AI_LLAMA_LIB.');
        }

        $ffi = \FFI::cdef(self::CDEF, $path);
        ($ffi->llama_backend_init)();
        $this->ffi = $ffi;

        return $ffi;
    }
}
