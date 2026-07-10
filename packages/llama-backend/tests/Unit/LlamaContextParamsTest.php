<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit;

use FerryAI\LlamaBackend\LlamaContextParams;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LlamaContextParams::class)]
final class LlamaContextParamsTest extends TestCase
{
    public function testDefaults(): void
    {
        $params = new LlamaContextParams();

        self::assertSame(2048, $params->nCtx);
        self::assertSame(512, $params->nBatch);
        self::assertSame(0, $params->nGpuLayers);
        self::assertSame(0, $params->nThreads);
        self::assertFalse($params->flashAttn);
        self::assertTrue($params->useMmap);
        self::assertFalse($params->useMlock);
    }

    public function testToArrayContainsAllKeys(): void
    {
        $array = (new LlamaContextParams())->toArray();

        foreach (['n_ctx', 'n_batch', 'n_gpu_layers', 'n_threads', 'flash_attn', 'use_mmap', 'use_mlock'] as $key) {
            self::assertArrayHasKey($key, $array);
        }
    }

    public function testToArrayReflectsValues(): void
    {
        $array = (new LlamaContextParams(nCtx: 4096, nGpuLayers: 33))->toArray();

        self::assertSame(4096, $array['n_ctx']);
        self::assertSame(33, $array['n_gpu_layers']);
    }
}
