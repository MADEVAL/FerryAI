<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\ValueObjects;

use FerryAI\Core\Exception\ValidationException;
use FerryAI\Core\ValueObjects\SamplingParams;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SamplingParams::class)]
final class SamplingParamsTest extends TestCase
{
    public function testDefaults(): void
    {
        $params = new SamplingParams();

        self::assertSame(0.7, $params->temperature);
        self::assertSame(1.0, $params->topP);
        self::assertSame(40, $params->topK);
        self::assertSame(2048, $params->maxTokens);
        self::assertNull($params->stop);
        self::assertNull($params->seed);
    }

    public function testTemperatureOutOfRangeIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new SamplingParams(temperature: 3.0);
    }

    public function testTopPOutOfRangeIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new SamplingParams(topP: 1.5);
    }

    public function testTopKBelowOneIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new SamplingParams(topK: 0);
    }

    public function testMaxTokensBelowOneIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new SamplingParams(maxTokens: 0);
    }

    public function testRepetitionPenaltyZeroIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new SamplingParams(repetitionPenalty: 0.0);
    }

    public function testRepetitionPenaltyNegativeIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new SamplingParams(repetitionPenalty: -0.5);
    }
}
