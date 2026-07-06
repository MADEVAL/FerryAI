<?php

declare(strict_types=1);

namespace FerryAI\DataFrame\Tests\Unit;

use FerryAI\Core\Contracts\Tensor;
use FerryAI\Core\Enums\DType;
use FerryAI\DataFrame\Column;
use FerryAI\DataFrame\DataFrame;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataFrame::class)]
final class DataFrameTest extends TestCase
{
    private DataFrame $df;

    protected function setUp(): void
    {
        $this->df = new DataFrame(
            new Column('name', 'string', ['alice', 'bob', 'charlie']),
            new Column('age', 'int', [25, 30, 35]),
            new Column('score', 'float', [0.8, 0.9, 0.7]),
        );
    }

    public function testColumnsReturnsOrderedNames(): void
    {
        self::assertSame(['name', 'age', 'score'], $this->df->columns());
    }

    public function testDtypesReturnsColumnTypes(): void
    {
        self::assertSame(['name' => 'string', 'age' => 'int', 'score' => 'float'], $this->df->dtypes());
    }

    public function testNumRowsReturnsRowCount(): void
    {
        self::assertSame(3, $this->df->numRows());
    }

    public function testNumColsReturnsColumnCount(): void
    {
        self::assertSame(3, $this->df->numCols());
    }

    public function testEmptyDataFrameHasZeroRows(): void
    {
        $empty = new DataFrame();

        self::assertSame(0, $empty->numRows());
        self::assertSame(0, $empty->numCols());
        self::assertSame([], $empty->columns());
    }

    public function testColumnReturnsNamedColumnData(): void
    {
        self::assertSame(['alice', 'bob', 'charlie'], $this->df->column('name'));
        self::assertSame([25, 30, 35], $this->df->column('age'));
    }

    public function testColumnThrowsForUnknownName(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);

        $this->df->column('nonexistent');
    }

    public function testRowReturnsAssociativeArray(): void
    {
        $row = $this->df->row(1);

        self::assertSame(['name' => 'bob', 'age' => 30, 'score' => 0.9], $row);
    }

    public function testRowThrowsForOutOfBoundsIndex(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);

        $this->df->row(99);
    }

    public function testToArrayReturnsNestedArray(): void
    {
        $arr = $this->df->toArray();

        self::assertCount(3, $arr);
        self::assertSame(['name' => 'alice', 'age' => 25, 'score' => 0.8], $arr[0]);
        self::assertSame(['name' => 'bob', 'age' => 30, 'score' => 0.9], $arr[1]);
    }

    public function testFromArrayCreatesDataFrame(): void
    {
        $data = [
            ['name' => 'david', 'age' => 40, 'score' => 0.5],
            ['name' => 'eve', 'age' => 28, 'score' => 0.6],
        ];

        $df = DataFrame::fromArray($data);

        self::assertSame(2, $df->numRows());
        self::assertSame(3, $df->numCols());
        self::assertSame(['david', 'eve'], $df->column('name'));
    }

    public function testFromArrayWithColumnsHint(): void
    {
        $data = [
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 4, 'b' => 5, 'c' => 6],
        ];

        $df = DataFrame::fromArray($data, ['a', 'c']);

        self::assertSame(['a', 'c'], $df->columns());
    }

    public function testFilterReturnsNewDataFrame(): void
    {
        $filtered = $this->df->filter(fn(array $row): bool => $row['age'] >= 30);

        self::assertSame(2, $filtered->numRows());
        self::assertSame(['bob', 'charlie'], $filtered->column('name'));
        self::assertNotSame($this->df, $filtered);
    }

    public function testFilterPreservesOriginal(): void
    {
        $this->df->filter(fn(array $row): bool => $row['age'] >= 30);

        self::assertSame(3, $this->df->numRows());
    }

    public function testFilterWithNoMatchesReturnsEmpty(): void
    {
        $filtered = $this->df->filter(fn(array $row): bool => false);

        self::assertSame(0, $filtered->numRows());
        self::assertSame(['name', 'age', 'score'], $filtered->columns());
    }

    public function testSortAscending(): void
    {
        $sorted = $this->df->sort('age', true);

        self::assertSame([25, 30, 35], $sorted->column('age'));
        self::assertSame(['alice', 'bob', 'charlie'], $sorted->column('name'));
    }

    public function testSortDescending(): void
    {
        $sorted = $this->df->sort('age', false);

        self::assertSame([35, 30, 25], $sorted->column('age'));
    }

    public function testSortPreservesOriginal(): void
    {
        $this->df->sort('age');

        self::assertSame([25, 30, 35], $this->df->column('age'));
    }

    public function testSelectReturnsSubsetOfColumns(): void
    {
        $selected = $this->df->select(['name', 'score']);

        self::assertSame(['name', 'score'], $selected->columns());
        self::assertSame(3, $selected->numRows());
        self::assertSame(['alice', 'bob', 'charlie'], $selected->column('name'));
    }

    public function testSelectThrowsForUnknownColumn(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);

        $this->df->select(['name', 'bad_column']);
    }

    public function testGroupByReturnsBucketedDataFrames(): void
    {
        $df = new DataFrame(
            new Column('category', 'string', ['a', 'b', 'a', 'b', 'a']),
            new Column('value', 'int', [1, 2, 3, 4, 5]),
        );

        $groups = $df->groupBy('category');

        self::assertCount(2, $groups);
        self::assertArrayHasKey('a', $groups);
        self::assertArrayHasKey('b', $groups);
        self::assertSame(3, $groups['a']->numRows());
        self::assertSame(2, $groups['b']->numRows());
    }

    public function testAggregateSum(): void
    {
        self::assertEqualsWithDelta(90, $this->df->aggregate('age', 'sum'), 0.0);
    }

    public function testAggregateMean(): void
    {
        self::assertEqualsWithDelta(30.0, $this->df->aggregate('age', 'mean'), 0.0001);
    }

    public function testAggregateMin(): void
    {
        self::assertSame(25, $this->df->aggregate('age', 'min'));
    }

    public function testAggregateMax(): void
    {
        self::assertSame(35, $this->df->aggregate('age', 'max'));
    }

    public function testAggregateCount(): void
    {
        self::assertSame(3, $this->df->aggregate('age', 'count'));
    }

    public function testAggregateThrowsForUnknownFunction(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);

        $this->df->aggregate('age', 'median');
    }

    public function testAggregateThrowsForUnknownColumn(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);

        $this->df->aggregate('bad', 'sum');
    }

    public function testAggregateThrowsOnEmptyDataFrame(): void
    {
        $empty = new DataFrame();

        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);

        $empty->aggregate('x', 'sum');
    }

    public function testToTensorNumericColumnReturnsFloat32Tensor(): void
    {
        $tensor = $this->df->toTensor('score');

        self::assertInstanceOf(Tensor::class, $tensor);
        self::assertSame(DType::Float32, $tensor->dtype());
        self::assertSame([0.8, 0.9, 0.7], $tensor->toArray());
    }

    public function testToTensorIntColumnReturnsInt32Tensor(): void
    {
        $tensor = $this->df->toTensor('age');

        self::assertInstanceOf(Tensor::class, $tensor);
        self::assertSame(DType::Int32, $tensor->dtype());
    }

    public function testToTensorStringColumnLabelEncodes(): void
    {
        $tensor = $this->df->toTensor('name');

        self::assertInstanceOf(Tensor::class, $tensor);
        self::assertSame(DType::Int32, $tensor->dtype());
        self::assertCount(3, $tensor->toArray());
    }

    public function testIteratorYieldsRows(): void
    {
        $rows = iterator_to_array($this->df);

        self::assertCount(3, $rows);
        self::assertSame('bob', $rows[1]['name']);
    }

    public function testIteratorCanRewind(): void
    {
        $first = [];

        foreach ($this->df as $row) {
            $first[] = $row['name'];
        }

        $second = [];

        foreach ($this->df as $row) {
            $second[] = $row['name'];
        }

        self::assertSame($first, $second);
    }

    public function testCountableReturnsRowCount(): void
    {
        self::assertCount(3, $this->df);
    }

    public function testConstructorRejectsMismatchedColumnLengths(): void
    {
        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);

        new DataFrame(
            new Column('a', 'int', [1, 2, 3]),
            new Column('b', 'int', [1, 2]),
        );
    }
}
