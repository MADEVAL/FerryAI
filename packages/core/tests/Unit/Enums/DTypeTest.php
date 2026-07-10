<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Enums;

use FerryAI\Core\Enums\DType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DType::class)]
final class DTypeTest extends TestCase
{
    public function testAllFiveCasesAreDefined(): void
    {
        self::assertCount(5, DType::cases());
    }

    public function testBackingValues(): void
    {
        self::assertSame('float32', DType::Float32->value);
        self::assertSame('float16', DType::Float16->value);
        self::assertSame('int32', DType::Int32->value);
        self::assertSame('int64', DType::Int64->value);
        self::assertSame('string', DType::String->value);
    }

    /**
     * @return iterable<string, array{DType, int}>
     */
    public static function sizeProvider(): iterable
    {
        yield 'float32' => [DType::Float32, 4];
        yield 'float16' => [DType::Float16, 2];
        yield 'int32' => [DType::Int32, 4];
        yield 'int64' => [DType::Int64, 8];
        yield 'string' => [DType::String, 0];
    }

    #[DataProvider('sizeProvider')]
    public function testSizeInBytes(DType $dtype, int $expected): void
    {
        self::assertSame($expected, $dtype->sizeInBytes());
    }
}
