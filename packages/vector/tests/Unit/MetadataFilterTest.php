<?php

declare(strict_types=1);

namespace FerryAI\Vector\Tests\Unit;

use FerryAI\Vector\MetadataFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetadataFilter::class)]
final class MetadataFilterTest extends TestCase
{
    private MetadataFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new MetadataFilter();
    }

    public function testEqOperator(): void
    {
        $metadata = ['price' => 100];

        self::assertTrue($this->filter->matches($metadata, ['price' => ['eq' => 100]]));
        self::assertFalse($this->filter->matches($metadata, ['price' => ['eq' => 200]]));
    }

    public function testNeqOperator(): void
    {
        $metadata = ['price' => 100];

        self::assertTrue($this->filter->matches($metadata, ['price' => ['neq' => 200]]));
        self::assertFalse($this->filter->matches($metadata, ['price' => ['neq' => 100]]));
    }

    public function testGtGteLtLteOperators(): void
    {
        $metadata = ['price' => 100];

        self::assertTrue($this->filter->matches($metadata, ['price' => ['gt' => 50]]));
        self::assertFalse($this->filter->matches($metadata, ['price' => ['gt' => 100]]));
        self::assertTrue($this->filter->matches($metadata, ['price' => ['gte' => 100]]));
        self::assertTrue($this->filter->matches($metadata, ['price' => ['lt' => 200]]));
        self::assertTrue($this->filter->matches($metadata, ['price' => ['lte' => 100]]));
    }

    public function testInOperator(): void
    {
        $metadata = ['color' => 'red'];

        self::assertTrue($this->filter->matches($metadata, ['color' => ['in' => ['red', 'blue']]]));
        self::assertFalse($this->filter->matches($metadata, ['color' => ['in' => ['green']]]));
    }

    public function testNinOperator(): void
    {
        $metadata = ['color' => 'red'];

        self::assertTrue($this->filter->matches($metadata, ['color' => ['nin' => ['green', 'blue']]]));
        self::assertFalse($this->filter->matches($metadata, ['color' => ['nin' => ['red']]]));
    }

    public function testContainsOperator(): void
    {
        $metadata = ['description' => 'hello world'];

        self::assertTrue($this->filter->matches($metadata, ['description' => ['contains' => 'world']]));
        self::assertFalse($this->filter->matches($metadata, ['description' => ['contains' => 'xyz']]));
    }

    public function testExistsOperator(): void
    {
        $metadata = ['name' => 'test'];

        self::assertTrue($this->filter->matches($metadata, ['name' => ['exists' => true]]));
        self::assertFalse($this->filter->matches($metadata, ['missing' => ['exists' => true]]));
        self::assertTrue($this->filter->matches($metadata, ['missing' => ['exists' => false]]));
    }

    public function testAndLogic(): void
    {
        $metadata = ['price' => 100, 'color' => 'red'];

        self::assertTrue($this->filter->matches($metadata, [
            'and' => [
                ['price' => ['gt' => 50]],
                ['color' => ['eq' => 'red']],
            ],
        ]));
        self::assertFalse($this->filter->matches($metadata, [
            'and' => [
                ['price' => ['gt' => 50]],
                ['color' => ['eq' => 'blue']],
            ],
        ]));
    }

    public function testOrLogic(): void
    {
        $metadata = ['color' => 'red'];

        self::assertTrue($this->filter->matches($metadata, [
            'or' => [
                ['color' => ['eq' => 'blue']],
                ['color' => ['eq' => 'red']],
            ],
        ]));
        self::assertFalse($this->filter->matches($metadata, [
            'or' => [
                ['color' => ['eq' => 'blue']],
                ['color' => ['eq' => 'green']],
            ],
        ]));
    }

    public function testNotLogic(): void
    {
        $metadata = ['price' => 100];

        self::assertTrue($this->filter->matches($metadata, [
            'not' => ['price' => ['gt' => 200]],
        ]));
        self::assertFalse($this->filter->matches($metadata, [
            'not' => ['price' => ['lt' => 200]],
        ]));
    }

    public function testNestedAndOr(): void
    {
        $metadata = ['price' => 100, 'color' => 'red', 'size' => 'M'];

        self::assertTrue($this->filter->matches($metadata, [
            'and' => [
                ['color' => ['eq' => 'red']],
                ['or' => [
                    ['price' => ['lt' => 50]],
                    ['size' => ['eq' => 'M']],
                ]],
            ],
        ]));
    }

    public function testToPhpReturnsClosure(): void
    {
        $predicate = $this->filter->toPhp(['price' => ['lt' => 200]]);

        self::assertInstanceOf(\Closure::class, $predicate);
        self::assertTrue($predicate(['price' => 100]));
        self::assertFalse($predicate(['price' => 300]));
    }
}
