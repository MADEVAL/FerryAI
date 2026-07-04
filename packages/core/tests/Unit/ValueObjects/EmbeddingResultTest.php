<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\ValueObjects;

use FerryAI\Core\ValueObjects\EmbeddingResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbeddingResult::class)]
final class EmbeddingResultTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $result = new EmbeddingResult([1.0, 2.0, 3.0], 3, 'test');

        self::assertSame([1.0, 2.0, 3.0], $result->vector);
        self::assertSame(3, $result->dimension);
        self::assertSame('test', $result->modelName);
    }

    public function testDimensionMismatchIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EmbeddingResult([1.0, 2.0], 3, 'test');
    }
}
