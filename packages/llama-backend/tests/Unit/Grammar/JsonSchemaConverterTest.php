<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Grammar;

use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use FerryAI\LlamaBackend\Grammar\GbnfMatcher;
use FerryAI\LlamaBackend\Grammar\JsonSchemaConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonSchemaConverter::class)]
final class JsonSchemaConverterTest extends TestCase
{
    private JsonSchemaConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new JsonSchemaConverter();
    }

    public function testConvertReturnsGrammar(): void
    {
        self::assertInstanceOf(GbnfGrammar::class, $this->converter->convert(['type' => 'string']));
    }

    public function testObjectSchemaHasRootAndPropertyLiteral(): void
    {
        $gbnf = $this->converter->convert([
            'type' => 'object',
            'properties' => ['name' => ['type' => 'string']],
            'required' => ['name'],
        ])->toString();

        self::assertStringContainsString('root ::=', $gbnf);
        self::assertStringContainsString('\\"name\\"', $gbnf);
        self::assertStringContainsString('string ::=', $gbnf);
    }

    public function testObjectWithoutRequiredAcceptsPropertySubsets(): void
    {
        $grammar = $this->converter->convert([
            'type' => 'object',
            'properties' => [
                'a' => ['type' => 'string'],
                'b' => ['type' => 'integer'],
            ],
            // note: no "required" key — both should be OPTIONAL per JSON Schema
        ]);
        $matcher = new GbnfMatcher($grammar);

        self::assertTrue($matcher->isComplete('{"a":"x"}'));
        self::assertTrue($matcher->isComplete('{"b":42}'));
        self::assertTrue($matcher->isComplete('{}'), 'empty object must be valid when no required');
        self::assertTrue($matcher->isComplete('{"a":"x","b":42}'));
    }

    public function testPrimitiveRules(): void
    {
        self::assertStringContainsString('integer ::=', $this->converter->convert(['type' => 'integer'])->toString());
        self::assertStringContainsString('number ::=', $this->converter->convert(['type' => 'number'])->toString());
        self::assertStringContainsString('boolean ::=', $this->converter->convert(['type' => 'boolean'])->toString());
    }

    public function testEnumProducesLiterals(): void
    {
        $gbnf = $this->converter->convert(['enum' => ['red', 'green']])->toString();

        self::assertStringContainsString('\\"red\\"', $gbnf);
        self::assertStringContainsString('\\"green\\"', $gbnf);
    }

    public function testArraySchema(): void
    {
        $gbnf = $this->converter->convert(['type' => 'array', 'items' => ['type' => 'number']])->toString();

        self::assertStringContainsString('number ::=', $gbnf);
        self::assertStringContainsString('"["', $gbnf);
    }
}
