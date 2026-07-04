<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit;

use FerryAI\LlamaBackend\LlamaModelParams;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LlamaModelParams::class)]
final class LlamaModelParamsTest extends TestCase
{
    public function testDefaults(): void
    {
        $params = new LlamaModelParams();

        self::assertSame(0, $params->nGpuLayers);
        self::assertTrue($params->useMmap);
        self::assertFalse($params->useMlock);
        self::assertFalse($params->vocabOnly);
    }

    public function testToArray(): void
    {
        $array = (new LlamaModelParams(nGpuLayers: 20, vocabOnly: true))->toArray();

        self::assertSame(20, $array['n_gpu_layers']);
        self::assertTrue($array['vocab_only']);
        self::assertArrayHasKey('use_mmap', $array);
        self::assertArrayHasKey('use_mlock', $array);
    }
}
