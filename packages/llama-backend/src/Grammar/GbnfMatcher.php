<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Grammar;

use FerryAI\Core\Exception\ValidationException;

/**
 * A pure-PHP GBNF recogniser used to strictly constrain generation.
 *
 * Supports a practical GBNF subset: string literals, character classes ([a-z], [^0-9], escapes),
 * alternation `|`, sequences, grouping `( … )`, repetition `* + ?`, rule references, `#` comments.
 *
 * Two queries drive grammar-constrained sampling:
 *  - {@see isComplete()}: the string is a full sentence of the grammar.
 *  - {@see isViable()}:   the string is a prefix of some sentence (generation may continue).
 */
final class GbnfMatcher
{
    /** @var array<string, GbnfNode> */
    private array $rules;

    private string $root;

    public function __construct(GbnfGrammar $grammar)
    {
        $this->rules = $this->parse($grammar->toString());

        if ($this->rules === []) {
            throw new ValidationException('GBNF grammar defines no rules.');
        }

        $this->root = isset($this->rules['root']) ? 'root' : array_key_first($this->rules);
    }

    public function isComplete(string $s): bool
    {
        return \in_array(\strlen($s), $this->ends($this->rules[$this->root], $s, 0), true);
    }

    public function isViable(string $s): bool
    {
        return $this->viable($this->rules[$this->root], $s, 0);
    }

    /**
     * @return int[] end positions where $node fully matches $s starting at $pos
     */
    private function ends(GbnfNode $node, string $s, int $pos): array
    {
        switch ($node->kind) {
            case GbnfNode::LITERAL:
                $len = \strlen($node->text);

                return $pos + $len <= \strlen($s) && \substr($s, $pos, $len) === $node->text ? [$pos + $len] : [];

            case GbnfNode::CHAR_CLASS:
                return $pos < \strlen($s) && $this->classMatches($s[$pos], $node) ? [$pos + 1] : [];

            case GbnfNode::REFERENCE:
                return $this->ends($this->rules[$node->text], $s, $pos);

            case GbnfNode::SEQUENCE:
                $cur = [$pos];

                foreach ($node->children as $child) {
                    $next = [];

                    foreach ($cur as $p) {
                        foreach ($this->ends($child, $s, $p) as $e) {
                            $next[$e] = true;
                        }
                    }

                    if ($next === []) {
                        return [];
                    }

                    $cur = array_keys($next);
                }

                return $cur;

            case GbnfNode::ALTERNATION:
                $res = [];

                foreach ($node->children as $child) {
                    foreach ($this->ends($child, $s, $pos) as $e) {
                        $res[$e] = true;
                    }
                }

                return array_keys($res);

            case GbnfNode::REPETITION:
                return $this->repetitionEnds($node, $s, $pos);

            default:
                return [];
        }
    }

    /**
     * @return int[]
     */
    private function repetitionEnds(GbnfNode $node, string $s, int $pos): array
    {
        $child = $node->children[0];
        $out = [];

        if ($node->min === 0) {
            $out[$pos] = true;
        }

        /** @var array<int, array<int, true>> $seen position => reps => true */
        $seen = [$pos => [0 => true]];
        $stack = [[$pos, 0]];

        while ($stack !== []) {
            [$p, $count] = array_pop($stack);

            if ($node->max !== null && $count >= $node->max) {
                continue;
            }

            foreach ($this->ends($child, $s, $p) as $e) {
                if ($e === $p) {
                    continue;
                }

                $reps = $count + 1;

                if ($reps >= $node->min) {
                    $out[$e] = true;
                }

                if (!isset($seen[$e][$reps])) {
                    $seen[$e][$reps] = true;
                    $stack[] = [$e, $reps];
                }
            }
        }

        return array_keys($out);
    }

    private function viable(GbnfNode $node, string $s, int $pos): bool
    {
        $len = \strlen($s);

        if ($pos === $len) {
            return true;
        }

        switch ($node->kind) {
            case GbnfNode::LITERAL:
                $tl = \strlen($node->text);
                $rem = $len - $pos;

                if ($rem < $tl) {
                    return \substr($s, $pos) === \substr($node->text, 0, $rem);
                }

                return \substr($s, $pos, $tl) === $node->text && $pos + $tl === $len;

            case GbnfNode::CHAR_CLASS:
                return $this->classMatches($s[$pos], $node) && $pos + 1 === $len;

            case GbnfNode::REFERENCE:
                return $this->viable($this->rules[$node->text], $s, $pos);

            case GbnfNode::SEQUENCE:
                $cur = [$pos];

                foreach ($node->children as $child) {
                    foreach ($cur as $p) {
                        if ($this->viable($child, $s, $p)) {
                            return true;
                        }
                    }

                    $next = [];

                    foreach ($cur as $p) {
                        foreach ($this->ends($child, $s, $p) as $e) {
                            $next[$e] = true;
                        }
                    }

                    if ($next === []) {
                        return false;
                    }

                    $cur = array_keys($next);

                    if (\in_array($len, $cur, true)) {
                        return true;
                    }
                }

                return \in_array($len, $cur, true);

            case GbnfNode::ALTERNATION:
                foreach ($node->children as $child) {
                    if ($this->viable($child, $s, $pos)) {
                        return true;
                    }
                }

                return false;

            case GbnfNode::REPETITION:
                return $this->repetitionViable($node, $s, $pos, $len);

            default:
                return false;
        }
    }

    private function repetitionViable(GbnfNode $node, string $s, int $pos, int $len): bool
    {
        $child = $node->children[0];

        if ($this->viable($child, $s, $pos)) {
            return true;
        }

        $seen = [$pos => true];
        $stack = [$pos];

        while ($stack !== []) {
            $p = array_pop($stack);

            foreach ($this->ends($child, $s, $p) as $e) {
                if ($e === $p) {
                    continue;
                }

                if ($e === $len || $this->viable($child, $s, $e)) {
                    return true;
                }

                if (!isset($seen[$e])) {
                    $seen[$e] = true;
                    $stack[] = $e;
                }
            }
        }

        return false;
    }

    private function classMatches(string $ch, GbnfNode $node): bool
    {
        $code = \ord($ch);
        $inRange = false;

        foreach ($node->ranges as [$lo, $hi]) {
            if ($code >= $lo && $code <= $hi) {
                $inRange = true;

                break;
            }
        }

        return $node->negated ? !$inRange : $inRange;
    }

    // --- Parser ---------------------------------------------------------------

    /**
     * @return array<string, GbnfNode>
     */
    private function parse(string $gbnf): array
    {
        $gbnf = (string) \preg_replace('/#[^\n]*/', '', $gbnf);

        \preg_match_all('/([A-Za-z_][A-Za-z0-9_-]*)\s*::=/', $gbnf, $matches, PREG_OFFSET_CAPTURE);

        $rules = [];
        $count = \count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
            $name = $matches[1][$i][0];
            $start = $matches[0][$i][1] + \strlen($matches[0][$i][0]);
            $end = $i + 1 < $count ? $matches[0][$i + 1][1] : \strlen($gbnf);
            $body = \substr($gbnf, $start, $end - $start);

            $offset = 0;
            $rules[$name] = $this->parseAlternation($body, $offset);
        }

        return $rules;
    }

    private function parseAlternation(string $s, int &$i): GbnfNode
    {
        $options = [$this->parseSequence($s, $i)];

        while (true) {
            $this->skipSpace($s, $i);

            if ($i < \strlen($s) && $s[$i] === '|') {
                $i++;
                $options[] = $this->parseSequence($s, $i);

                continue;
            }

            break;
        }

        return \count($options) === 1 ? $options[0] : new GbnfNode(GbnfNode::ALTERNATION, children: $options);
    }

    private function parseSequence(string $s, int &$i): GbnfNode
    {
        $items = [];

        while (true) {
            $this->skipSpace($s, $i);

            if ($i >= \strlen($s) || $s[$i] === '|' || $s[$i] === ')') {
                break;
            }

            $items[] = $this->parseTerm($s, $i);
        }

        if ($items === []) {
            return new GbnfNode(GbnfNode::LITERAL, text: '');
        }

        return \count($items) === 1 ? $items[0] : new GbnfNode(GbnfNode::SEQUENCE, children: $items);
    }

    private function parseTerm(string $s, int &$i): GbnfNode
    {
        $atom = $this->parseAtom($s, $i);

        if ($i < \strlen($s)) {
            $q = $s[$i];

            if ($q === '*') {
                $i++;

                return new GbnfNode(GbnfNode::REPETITION, children: [$atom], min: 0, max: null);
            }

            if ($q === '+') {
                $i++;

                return new GbnfNode(GbnfNode::REPETITION, children: [$atom], min: 1, max: null);
            }

            if ($q === '?') {
                $i++;

                return new GbnfNode(GbnfNode::REPETITION, children: [$atom], min: 0, max: 1);
            }
        }

        return $atom;
    }

    private function parseAtom(string $s, int &$i): GbnfNode
    {
        $this->skipSpace($s, $i);
        $ch = $s[$i] ?? '';

        if ($ch === '"') {
            return $this->parseLiteral($s, $i);
        }

        if ($ch === '[') {
            return $this->parseCharClass($s, $i);
        }

        if ($ch === '.') {
            $i++;

            // Any single character: an empty negated char class matches every codepoint.
            return new GbnfNode(GbnfNode::CHAR_CLASS, ranges: [], negated: true);
        }

        if ($ch === '(') {
            $i++;
            $node = $this->parseAlternation($s, $i);
            $this->skipSpace($s, $i);

            if (($s[$i] ?? '') === ')') {
                $i++;
            }

            return $node;
        }

        if (\preg_match('/[A-Za-z_][A-Za-z0-9_-]*/A', $s, $m, 0, $i) === 1) {
            $i += \strlen($m[0]);

            return new GbnfNode(GbnfNode::REFERENCE, text: $m[0]);
        }

        // Unknown character — consume it so parsing terminates.
        $i++;

        return new GbnfNode(GbnfNode::LITERAL, text: '');
    }

    private function parseLiteral(string $s, int &$i): GbnfNode
    {
        $i++; // opening quote
        $text = '';

        while ($i < \strlen($s) && $s[$i] !== '"') {
            if ($s[$i] === '\\' && $i + 1 < \strlen($s)) {
                $text .= $this->unescape($s[$i + 1]);
                $i += 2;

                continue;
            }

            $text .= $s[$i];
            $i++;
        }

        $i++; // closing quote

        return new GbnfNode(GbnfNode::LITERAL, text: $text);
    }

    private function parseCharClass(string $s, int &$i): GbnfNode
    {
        $i++; // opening bracket
        $negated = false;

        if (($s[$i] ?? '') === '^') {
            $negated = true;
            $i++;
        }

        /** @var array<int, array{int, int}> $ranges */
        $ranges = [];

        while ($i < \strlen($s) && $s[$i] !== ']') {
            $lo = $s[$i];

            if ($lo === '\\' && $i + 1 < \strlen($s)) {
                $lo = $this->unescape($s[$i + 1]);
                $i += 2;
            } else {
                $i++;
            }

            if (($s[$i] ?? '') === '-' && ($s[$i + 1] ?? ']') !== ']') {
                $i++; // dash
                $hi = $s[$i];

                if ($hi === '\\' && $i + 1 < \strlen($s)) {
                    $hi = $this->unescape($s[$i + 1]);
                    $i += 2;
                } else {
                    $i++;
                }

                $ranges[] = [\ord($lo), \ord($hi)];
            } else {
                $ranges[] = [\ord($lo), \ord($lo)];
            }
        }

        $i++; // closing bracket

        return new GbnfNode(GbnfNode::CHAR_CLASS, ranges: $ranges, negated: $negated);
    }

    private function unescape(string $c): string
    {
        return match ($c) {
            'n' => "\n",
            't' => "\t",
            'r' => "\r",
            default => $c,
        };
    }

    private function skipSpace(string $s, int &$i): void
    {
        while ($i < \strlen($s) && ($s[$i] === ' ' || $s[$i] === "\t" || $s[$i] === "\n" || $s[$i] === "\r")) {
            $i++;
        }
    }
}
