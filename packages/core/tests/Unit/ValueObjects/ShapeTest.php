<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\ValueObjects;

use FerryAI\Core\Exception\ValidationException;
use FerryAI\Core\ValueObjects\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Shape::class)]
final class ShapeTest extends TestCase
{
    public function testRank(): void
    {
        self::assertSame(4, (new Shape([1, 3, 224, 224]))->rank());
    }

    public function testSizeIsProductOfDimensions(): void
    {
        self::assertSame(6, (new Shape([2, 3]))->size());
    }

    public function testSizeIsMinusOneWhenDynamic(): void
    {
        self::assertSame(-1, (new Shape([1, -1]))->size());
    }

    public function testIsStatic(): void
    {
        self::assertTrue((new Shape([1, 3, 224]))->isStatic());
        self::assertFalse((new Shape([1, 3, -1]))->isStatic());
    }

    public function testDimension(): void
    {
        self::assertSame(224, (new Shape([1, 3, 224]))->dimension(2));
    }

    public function testDimensionThrowsForMissingAxis(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        (new Shape([1, 3]))->dimension(5);
    }

    public function testConstructorRejectsInvalidDimension(): void
    {
        $this->expectException(ValidationException::class);

        new Shape([-5]);
    }

    public function testMinusOneIsAllowed(): void
    {
        self::assertSame([-1, 3], (new Shape([-1, 3]))->toArray());
    }

    public function testFromStringEqualsConstructor(): void
    {
        self::assertEquals(new Shape([1, 3, 224, 224]), Shape::fromString('1,3,224,224'));
    }

    public function testToString(): void
    {
        self::assertSame('1,3,224,224', (string) new Shape([1, 3, 224, 224]));
    }

    public function testJsonSerialize(): void
    {
        self::assertSame('[2,3]', json_encode(new Shape([2, 3])));
    }

    public function testCompatibleWithBroadcasting(): void
    {
        self::assertTrue((new Shape([1, 3, 224, 224]))->compatibleWith(new Shape([3, 224, 224])));
        self::assertTrue((new Shape([2, 1]))->compatibleWith(new Shape([2, 3])));
        self::assertTrue((new Shape([-1, 3]))->compatibleWith(new Shape([5, 3])));
    }

    public function testIncompatibleShapes(): void
    {
        self::assertFalse((new Shape([2, 3]))->compatibleWith(new Shape([4, 3])));
    }
}
