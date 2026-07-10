<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Enums;

use FerryAI\Core\Enums\BackendType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackendType::class)]
final class BackendTypeTest extends TestCase
{
    public function testAllThreeCasesAreDefined(): void
    {
        self::assertCount(3, BackendType::cases());
    }

    public function testBackingValues(): void
    {
        self::assertSame('onnx', BackendType::Onnx->value);
        self::assertSame('llama', BackendType::Llama->value);
        self::assertSame('cpu_native', BackendType::CpuNative->value);
    }
}
