<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\FFI;

/**
 * FFI definitions for the llama.cpp C API — build 9873 (commit a4107133a).
 *
 * This is the FFI boundary of the package (excluded from static analysis).
 * The llama.cpp C API uses opaque struct pointers for llama_model, llama_context,
 * and llama_vocab — defined here as incomplete types for probe/load-only use.
 *
 * Full inference (model loading, tokenization, decode) requires the complete
 * struct declarations from llama.h aligned to your specific build. The current
 * CDEF is sufficient for library probing, version detection, and GPU capability checks.
 *
 * **Windows users:** the directory containing the DLL must be in PATH so
 * that dependent DLLs (ggml.dll, ggml-cpu-*.dll, etc.) can be found.
 */
final class LlamaCpp
{
    private const string CDEF = <<<'C'
        typedef struct llama_model   llama_model;
        typedef struct llama_context llama_context;
        typedef struct llama_vocab   llama_vocab;
        typedef int32_t              llama_token;

        void        llama_backend_init(void);
        void        llama_backend_free(void);
        const char* llama_print_system_info(void);

        bool        llama_supports_mmap(void);
        bool        llama_supports_mlock(void);
        bool        llama_supports_gpu_offload(void);
        int64_t     llama_time_us(void);
        C;

    private ?\FFI $ffi = null;

    private bool $initialized = false;

    public function __construct(private readonly ?string $libraryPath = null) {}

    public static function resolveLibraryPath(): ?string
    {
        $path = \getenv('FERRY_AI_LLAMA_LIB');

        return $path === false || $path === '' ? null : $path;
    }

    /**
     * Ensures the DLL directory is in PATH so dependent DLLs can be found (Windows).
     */
    public static function registerLibraryPath(string $libPath): void
    {
        $dir = \dirname($libPath);
        $currentPath = \getenv('PATH') ?: '';
        $sep = \PHP_OS_FAMILY === 'Windows' ? ';' : ':';

        if (!\str_contains($currentPath, $dir)) {
            \putenv('PATH=' . $currentPath . $sep . $dir);
        }
    }

    public function isLibraryLoadable(): bool
    {
        try {
            return $this->ffi() instanceof \FFI;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Initialize the llama.cpp + ggml backend. Safe to call — catches C++ boundary errors.
     */
    public function tryInit(): bool
    {
        if ($this->initialized) {
            return true;
        }

        try {
            ($this->ffi()->llama_backend_init)();
            $this->initialized = true;

            return true;
        } catch (\FFI\Exception) {
            return false;
        }
    }

    public function version(): string
    {
        try {
            $info = ($this->ffi()->llama_print_system_info)();

            return \is_string($info) && $info !== '' ? $info : 'llama.cpp (native)';
        } catch (\Throwable) {
            return 'llama.cpp (probe only)';
        }
    }

    public function supportsGpu(): bool
    {
        try {
            return ($this->ffi()->llama_supports_gpu_offload)();
        } catch (\Throwable) {
            return false;
        }
    }

    public function supportsMmap(): bool
    {
        try {
            return ($this->ffi()->llama_supports_mmap)();
        } catch (\Throwable) {
            return false;
        }
    }

    public function supportsMlock(): bool
    {
        try {
            return ($this->ffi()->llama_supports_mlock)();
        } catch (\Throwable) {
            return false;
        }
    }

    private function ffi(): \FFI
    {
        if ($this->ffi instanceof \FFI) {
            return $this->ffi;
        }

        $path = $this->libraryPath ?? self::resolveLibraryPath();

        if ($path === null || !\is_file($path)) {
            throw new \RuntimeException('llama.cpp shared library not found; set FERRY_AI_LLAMA_LIB.');
        }

        self::registerLibraryPath($path);

        $this->ffi = \FFI::cdef(self::CDEF, $path);

        return $this->ffi;
    }
}
