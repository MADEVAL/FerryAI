<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Grammar;

/**
 * Converts a (subset of) JSON Schema into a GBNF grammar.
 *
 * Supports: string, number, integer, boolean, null, array (items), object (properties/required),
 * plus enum and const. Each composite schema becomes a named rule; JSON primitives share reusable
 * terminal rules that are emitted only when referenced.
 */
final class JsonSchemaConverter
{
    private const array TERMINALS = [
        'ws' => 'ws ::= [ \t\n]*',
        'string' => 'string ::= "\\"" ("\\\\" . | [^"\\\\])* "\\""',
        'integer' => 'integer ::= "-"? ("0" | [1-9] [0-9]*)',
        'number' => 'number ::= "-"? ("0" | [1-9] [0-9]*) ("." [0-9]+)? ([eE] [-+]? [0-9]+)?',
        'boolean' => 'boolean ::= "true" | "false"',
        'null' => 'null ::= "null"',
    ];

    /**
     * @param array<string, mixed> $jsonSchema
     */
    public function convert(array $jsonSchema): GbnfGrammar
    {
        /** @var array<string, string> $rules */
        $rules = [];
        /** @var array<string, true> $terminals */
        $terminals = [];
        $counter = 0;

        $root = $this->build($jsonSchema, $rules, $terminals, $counter);

        $lines = ['root ::= ' . $root];

        foreach ($rules as $name => $definition) {
            $lines[] = $name . ' ::= ' . $definition;
        }

        foreach (array_keys($terminals) as $terminal) {
            $lines[] = self::TERMINALS[$terminal];
        }

        return GbnfGrammar::fromString(implode("\n", $lines));
    }

    /**
     * @param array<string, mixed>  $schema
     * @param array<string, string> $rules
     * @param array<string, true>   $terminals
     */
    private function build(array $schema, array &$rules, array &$terminals, int &$counter): string
    {
        if (\array_key_exists('const', $schema)) {
            return $this->literal($this->jsonText($schema['const']));
        }

        if (isset($schema['enum']) && \is_array($schema['enum'])) {
            $alternatives = array_map(fn(mixed $value): string => $this->literal($this->jsonText($value)), $schema['enum']);

            return '(' . implode(' | ', $alternatives) . ')';
        }

        $type = \is_string($schema['type'] ?? null) ? $schema['type'] : 'object';

        return match ($type) {
            'string', 'integer', 'number', 'boolean', 'null' => $this->terminal($type, $terminals),
            'array' => $this->buildArray($schema, $rules, $terminals, $counter),
            default => $this->buildObject($schema, $rules, $terminals, $counter),
        };
    }

    /**
     * @param array<string, mixed>  $schema
     * @param array<string, string> $rules
     * @param array<string, true>   $terminals
     */
    private function buildArray(array $schema, array &$rules, array &$terminals, int &$counter): string
    {
        $this->terminal('ws', $terminals);
        /** @var array<string, mixed> $itemsSchema */
        $itemsSchema = \is_array($schema['items'] ?? null) ? $schema['items'] : [];
        $item = $this->build($itemsSchema, $rules, $terminals, $counter);
        $name = 'array-' . $counter++;
        $rules[$name] = '"[" ws (' . $item . ' (ws "," ws ' . $item . ')*)? ws "]"';

        return $name;
    }

    /**
     * @param array<string, mixed>  $schema
     * @param array<string, string> $rules
     * @param array<string, true>   $terminals
     */
    private function buildObject(array $schema, array &$rules, array &$terminals, int &$counter): string
    {
        $this->terminal('ws', $terminals);
        $properties = \is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = \is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $name = 'object-' . $counter++;

        /** @var list<array{bool, string}> $pieces */
        $pieces = [];

        foreach ($properties as $key => $propertySchema) {
            if (!\is_string($key) || !\is_array($propertySchema)) {
                continue;
            }

            /** @var array<string, mixed> $propertySchema */
            $piece = $this->literal('"' . $key . '"') . ' ws ":" ws '
                . $this->build($propertySchema, $rules, $terminals, $counter);
            $pieces[] = [\in_array($key, $required, true), $piece];
        }

        $rules[$name] = $pieces === []
            ? '"{" ws "}"'
            : '"{" ws ' . $this->objectHead($pieces, 0) . ' ws "}"';

        return $name;
    }

    /**
     * Grammar for properties[$i..] where the first present property carries no leading comma.
     * Optional properties may be skipped so any in-order subset is accepted.
     *
     * @param list<array{bool, string}> $pieces
     */
    private function objectHead(array $pieces, int $i): string
    {
        if ($i >= \count($pieces)) {
            return '""';
        }

        [$isRequired, $piece] = $pieces[$i];
        $tail = $this->objectTail($pieces, $i + 1);
        $present = '(' . $piece . ($tail === '""' ? '' : ' ' . $tail) . ')';

        if ($isRequired) {
            return $present;
        }

        return '(' . $present . ' | ' . $this->objectHead($pieces, $i + 1) . ')';
    }

    /**
     * Grammar for properties[$i..] where every present property carries a leading comma.
     *
     * @param list<array{bool, string}> $pieces
     */
    private function objectTail(array $pieces, int $i): string
    {
        if ($i >= \count($pieces)) {
            return '""';
        }

        [$isRequired, $piece] = $pieces[$i];
        $sub = $this->objectTail($pieces, $i + 1);
        $segment = 'ws "," ws ' . $piece . ($sub === '""' ? '' : ' ' . $sub);

        if ($isRequired) {
            return '(' . $segment . ')';
        }

        return '((' . $segment . ') | ' . $sub . ')';
    }

    /**
     * @param array<string, true> $terminals
     */
    private function terminal(string $name, array &$terminals): string
    {
        $terminals[$name] = true;

        return $name;
    }

    /**
     * Wraps raw characters in a GBNF double-quoted literal, escaping backslashes and quotes.
     */
    private function literal(string $raw): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $raw) . '"';
    }

    private function jsonText(mixed $value): string
    {
        $encoded = json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        return $encoded === false ? 'null' : $encoded;
    }
}
