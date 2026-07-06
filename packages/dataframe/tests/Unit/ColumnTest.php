<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\Tests\Unit;

use FerryAI\DataFrame\Column;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Column::class)]
final class ColumnTest extends TestCase
{
    public function testColumnHoldsNameAndTypeAndData(): void
    {
        $column = new Column('age', 'int', [25, 30, 35]);

        self::assertSame('age', $column->name);
        self::assertSame('int', $column->type);
        self::assertSame([25, 30, 35], $column->data);
    }

    public function testCountReturnsDataSize(): void
    {
        $column = new Column('x', 'float', [1.0, 2.0, 3.0, 4.0]);

        self::assertSame(4, $column->count());
    }

    public function testEmptyColumnHasZeroCount(): void
    {
        $column = new Column('empty', 'string', []);

        self::assertSame(0, $column->count());
    }

    public function testInferTypeAllIntReturnsInt(): void
    {
        self::assertSame('int', Column::inferType([1, 2, 3, 100]));
    }

    public function testInferTypeMixedNumericReturnsFloat(): void
    {
        self::assertSame('float', Column::inferType([1, 2.5, 3]));
    }

    public function testInferTypeAllFloatReturnsFloat(): void
    {
        self::assertSame('float', Column::inferType([1.1, 2.2, 3.3]));
    }

    public function testInferTypeFewUniqueReturnsCategorical(): void
    {
        $data = array_merge(
            array_fill(0, 40, 'cat'),
            array_fill(0, 40, 'dog'),
            array_fill(0, 10, 'bird'),
            array_fill(0, 10, 'fox'),
        );

        self::assertSame('categorical', Column::inferType($data));
    }

    public function testInferTypeManyUniqueReturnsString(): void
    {
        $data = array_map(static fn(int $i): string => "value_{$i}", range(1, 100));

        self::assertSame('string', Column::inferType($data));
    }

    public function testInferTypeStringsWith20percentThresholdReturnsCategorical(): void
    {
        $data = array_merge(
            array_fill(0, 80, 'yes'),
            array_fill(0, 19, 'no'),
        );

        self::assertSame('categorical', Column::inferType($data));
    }

    public function testInferTypeStringsAbove20percentReturnsString(): void
    {
        $data = array_merge(
            array_fill(0, 79, 'yes'),
            array_map(static fn(int $i): string => "no_{$i}", range(1, 21)),
        );

        self::assertSame('string', Column::inferType($data));
    }

    public function testInferTypeEmptyArrayReturnsString(): void
    {
        self::assertSame('string', Column::inferType([]));
    }

    public function testMixedIntAndStringReturnsString(): void
    {
        self::assertSame('string', Column::inferType([1, 'two', 3]));
    }

    public function testColumnWithStringTypeAcceptsStrings(): void
    {
        $column = new Column('name', 'string', ['alice', 'bob']);

        self::assertSame('string', $column->type);
        self::assertSame(['alice', 'bob'], $column->data);
    }
}
