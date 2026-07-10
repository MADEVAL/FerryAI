<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Grammar;

/**
 * A GBNF grammar used to constrain llama.cpp generation.
 */
final readonly class GbnfGrammar implements \Stringable
{
    private function __construct(private string $gbnf) {}

    public static function fromString(string $gbnf): self
    {
        return new self($gbnf);
    }

    /**
     * @param array<string, mixed> $schema
     */
    public static function fromJsonSchema(array $schema): self
    {
        return (new JsonSchemaConverter())->convert($schema);
    }

    public function toString(): string
    {
        return $this->gbnf;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->gbnf;
    }
}
