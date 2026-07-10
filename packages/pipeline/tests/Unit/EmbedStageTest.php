<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Tests\Unit;

use FerryAI\Core\Contracts\Embedder;
use FerryAI\Pipeline\Stages\EmbedStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbedStage::class)]
final class EmbedStageTest extends TestCase
{
    public function testName(): void
    {
        $stage = new EmbedStage(new StubEmbedderForStage());

        self::assertSame('embed', $stage->name());
    }

    public function testProcessEmbedsString(): void
    {
        $stage = new EmbedStage(new StubEmbedderForStage());

        $result = $stage->process('hello');

        self::assertIsArray($result);
        self::assertCount(2, $result);
    }

    public function testProcessEmbedsArrayOfStrings(): void
    {
        $stage = new EmbedStage(new StubEmbedderForStage());

        $result = $stage->process(['a', 'b']);

        self::assertIsArray($result);
        self::assertCount(2, $result);
    }

    public function testProcessReturnsInputForNonString(): void
    {
        $stage = new EmbedStage(new StubEmbedderForStage());

        $result = $stage->process(42);

        self::assertSame(42, $result);
    }
}

final class StubEmbedderForStage implements Embedder
{
    public function embed(string $text): array
    {
        return [0.1, 0.2];
    }
    public function embedBatch(array $texts): array
    {
        return [[0.1, 0.2], [0.3, 0.4]];
    }
    public function dimension(): int
    {
        return 2;
    }
    public function normalize(array $vector): array
    {
        return $vector;
    }
    public function cosineSimilarity(array $a, array $b): float
    {
        return 0.5;
    }
    public function modelName(): string
    {
        return 'stub';
    }
}
