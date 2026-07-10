<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Enums;

use FerryAI\Core\Enums\QuantizationType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuantizationType::class)]
final class QuantizationTypeTest extends TestCase
{
    public function testAllFourCasesAreDefined(): void
    {
        self::assertCount(4, QuantizationType::cases());
    }

    public function testBackingValues(): void
    {
        self::assertSame('float32', QuantizationType::FLOAT32->value);
        self::assertSame('float16', QuantizationType::FLOAT16->value);
        self::assertSame('int8', QuantizationType::INT8->value);
        self::assertSame('binary', QuantizationType::BINARY->value);
    }
}
