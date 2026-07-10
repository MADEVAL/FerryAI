<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Enums;

use FerryAI\Core\Enums\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexType::class)]
final class IndexTypeTest extends TestCase
{
    public function testAllThreeCasesAreDefined(): void
    {
        self::assertCount(3, IndexType::cases());
    }

    public function testBackingValues(): void
    {
        self::assertSame('hnsw', IndexType::HNSW->value);
        self::assertSame('ivf', IndexType::IVF->value);
        self::assertSame('flat', IndexType::FLAT->value);
    }
}
