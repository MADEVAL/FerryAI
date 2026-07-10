<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\FFI;

use FerryAI\Core\FFI\CdefGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CdefGenerator::class)]
final class CdefGeneratorTest extends TestCase
{
    private const HEADER = <<<'C'
        #ifndef LLAMA_H
        #define LLAMA_H
        #include <stdint.h>

        #ifdef __cplusplus
        extern "C" {
        #endif

        // a single-line comment
        /* model params
           spanning lines */
        typedef struct llama_model_params {
            int32_t n_gpu_layers;
            bool    use_mmap;
        } llama_model_params;

        LLAMA_API struct llama_model * llama_model_load_from_file(const char * path, struct llama_model_params params);
        LLAMA_API void llama_free(struct llama_context * ctx) __attribute__((deprecated));

        #ifdef __cplusplus
        }
        #endif
        #endif // LLAMA_H
        C;

    public function testStripsCommentsPreprocessorMacrosAndExternC(): void
    {
        $cdef = (new CdefGenerator())->generate(self::HEADER, ['LLAMA_API']);

        self::assertStringNotContainsString('LLAMA_API', $cdef);
        self::assertStringNotContainsString('#', $cdef);
        self::assertStringNotContainsString('//', $cdef);
        self::assertStringNotContainsString('/*', $cdef);
        self::assertStringNotContainsString('extern', $cdef);
        self::assertStringNotContainsString('__attribute__', $cdef);
    }

    public function testKeepsTypedefsAndFunctionPrototypes(): void
    {
        $cdef = (new CdefGenerator())->generate(self::HEADER, ['LLAMA_API']);

        self::assertStringContainsString('typedef struct llama_model_params', $cdef);
        self::assertStringContainsString('llama_model_load_from_file(const char * path', $cdef);
        self::assertStringContainsString('void llama_free(struct llama_context * ctx);', $cdef);
    }

    public function testBalancesBracesAfterRemovingExternC(): void
    {
        $cdef = (new CdefGenerator())->generate(self::HEADER, ['LLAMA_API']);

        self::assertSame(\substr_count($cdef, '{'), \substr_count($cdef, '}'));
    }

    public function testProducesFfiParseableCdef(): void
    {
        if (!\extension_loaded('ffi')) {
            self::markTestSkipped('ext-ffi not available.');
        }

        $header = <<<'C'
            #ifdef __cplusplus
            extern "C" {
            #endif
            EXPORT typedef struct point { int32_t x; int32_t y; } point;
            EXPORT enum color { RED, GREEN, BLUE };
            #ifdef __cplusplus
            }
            #endif
            C;

        $cdef = (new CdefGenerator())->generate($header, ['EXPORT']);

        // FFI::cdef with no library parses the type declarations and must not throw.
        \FFI::cdef($cdef);

        self::assertSame(\substr_count($cdef, '{'), \substr_count($cdef, '}'));
    }
}
