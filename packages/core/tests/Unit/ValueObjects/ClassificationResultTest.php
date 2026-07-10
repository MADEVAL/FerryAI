<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\ValueObjects;

use FerryAI\Core\Exception\ValidationException;
use FerryAI\Core\ValueObjects\ClassificationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassificationResult::class)]
final class ClassificationResultTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $result = new ClassificationResult('positive', 0.98, ['positive' => 0.98, 'negative' => 0.02]);

        self::assertSame('positive', $result->label);
        self::assertSame(0.98, $result->confidence);
        self::assertSame(['positive' => 0.98, 'negative' => 0.02], $result->allScores);
    }

    public function testAllScoresDefaultsToEmpty(): void
    {
        self::assertSame([], (new ClassificationResult('x', 0.5))->allScores);
    }

    public function testConfidenceOutOfRangeIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new ClassificationResult('x', 1.5);
    }
}
