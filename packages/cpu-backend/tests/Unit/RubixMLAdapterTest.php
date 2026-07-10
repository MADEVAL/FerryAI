<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend\Tests\Unit;

use FerryAI\CpuBackend\RubixMLAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RubixMLAdapter::class)]
final class RubixMLAdapterTest extends TestCase
{
    public function testIsAvailableReturnsFalseWhenRubixNotInstalled(): void
    {
        $adapter = new RubixMLAdapter();

        self::assertFalse($adapter->isAvailable());
    }

    public function testLoadModelThrowsWhenNotAvailable(): void
    {
        $adapter = new RubixMLAdapter();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RubixML is not installed');

        $adapter->loadModel('/nonexistent/path.rbm');
    }

    public function testPredictThrowsWhenNotAvailable(): void
    {
        $adapter = new RubixMLAdapter();

        $this->expectException(\RuntimeException::class);

        $adapter->predict(null, [[1.0, 2.0]]);
    }

    public function testProbaThrowsWhenNotAvailable(): void
    {
        $adapter = new RubixMLAdapter();

        $this->expectException(\RuntimeException::class);

        $adapter->proba(null, [[1.0, 2.0]]);
    }
}
