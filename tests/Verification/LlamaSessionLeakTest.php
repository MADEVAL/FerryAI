<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\LlamaBackend\FFI\FerryLlama;
use FerryAI\LlamaBackend\LlamaContextParams;
use FerryAI\LlamaBackend\LlamaModelParams;
use FerryAI\LlamaBackend\Runtime\NativeLlamaRuntime;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: a failure while creating the llama context must not leak the
 * already-loaded native model. The model handle is an opaque native pointer, so PHP GC does
 * not release it; createSession must free it explicitly before propagating the exception.
 */
#[CoversNothing]
final class LlamaSessionLeakTest extends TestCase
{
    public function testCreateSessionFreesModelWhenContextCreationFails(): void
    {
        $model = \FFI::cdef('')->new('int');

        $ffi = $this->createMock(FerryLlama::class);
        $ffi->method('loadModel')->willReturn($model);
        $ffi->method('newContext')->willThrowException(new \RuntimeException('ferry_new_context failed'));
        $ffi->expects(self::once())->method('freeModel')->with($model);

        $runtime = new NativeLlamaRuntime();
        $ref = new \ReflectionProperty(NativeLlamaRuntime::class, 'ffi');
        $ref->setValue($runtime, $ffi);

        $this->expectException(\RuntimeException::class);

        try {
            $runtime->createSession('model.gguf', new LlamaModelParams(), new LlamaContextParams());
        } finally {
            // freeModel expectation is verified by PHPUnit on teardown.
        }
    }
}
