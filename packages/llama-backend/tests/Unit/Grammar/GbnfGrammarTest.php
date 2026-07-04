<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Grammar;

use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GbnfGrammar::class)]
final class GbnfGrammarTest extends TestCase
{
    public function testFromStringAndToString(): void
    {
        $grammar = GbnfGrammar::fromString('root ::= "a"');

        self::assertSame('root ::= "a"', $grammar->toString());
    }

    public function testStringableAlias(): void
    {
        $grammar = GbnfGrammar::fromString('root ::= "x"');

        self::assertSame($grammar->toString(), (string) $grammar);
    }

    public function testFromJsonSchemaProducesGrammar(): void
    {
        $grammar = GbnfGrammar::fromJsonSchema([
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ]);

        self::assertStringContainsString('root ::=', $grammar->toString());
        self::assertStringContainsString('\\"name\\"', $grammar->toString());
    }
}
