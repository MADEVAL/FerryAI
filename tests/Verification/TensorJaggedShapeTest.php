<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Core\Exception\ShapeMismatchException;
use FerryAI\Tensor\ArrayTensor;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: inferShape must reject jagged (non-rectangular) nested arrays instead
 * of silently inferring a shape from the first element, which desynchronises strides()/reshape().
 */
#[CoversNothing]
final class TensorJaggedShapeTest extends TestCase
{
    public function testJaggedRowsAreRejected(): void
    {
        $this->expectException(ShapeMismatchException::class);
        ArrayTensor::fromNested([[1, 2], [3]]);
    }

    public function testJaggedDeepNestingIsRejected(): void
    {
        $this->expectException(ShapeMismatchException::class);
        ArrayTensor::fromNested([[[1], [2]], [[3]]]);
    }

    public function testRegularMatrixIsAccepted(): void
    {
        $tensor = ArrayTensor::fromNested([[1, 2, 3], [4, 5, 6]]);

        self::assertSame([2, 3], $tensor->shape()->toArray());
        self::assertCount(6, $tensor);
    }

    public function testRegularVectorIsAccepted(): void
    {
        $tensor = ArrayTensor::fromNested([1, 2, 3]);

        self::assertSame([3], $tensor->shape()->toArray());
    }
}
