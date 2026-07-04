<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Enums;

use FerryAI\Core\Enums\GraphOptimizationLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GraphOptimizationLevel::class)]
final class GraphOptimizationLevelTest extends TestCase
{
    public function testAllFourCasesAreDefined(): void
    {
        self::assertCount(4, GraphOptimizationLevel::cases());
    }

    public function testBackingValues(): void
    {
        self::assertSame('disable_all', GraphOptimizationLevel::DISABLE_ALL->value);
        self::assertSame('basic', GraphOptimizationLevel::BASIC->value);
        self::assertSame('extended', GraphOptimizationLevel::EXTENDED->value);
        self::assertSame('all', GraphOptimizationLevel::ALL->value);
    }
}
