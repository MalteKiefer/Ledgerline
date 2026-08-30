<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Modules\Finance\Infrastructure\Compatibility\Exception\LegacyProjectExpenseMalformed;

/**
 * A minimal hand-rolled JSON tokenizer/parser used only by
 * {@see LegacyProjectExpenseParser}. It exists solely so a numeric lexeme
 * (an "amount") can be captured as its exact source substring instead of
 * being routed through `json_decode()`'s float cast — every other JSON value
 * (strings, booleans, null, structure) is otherwise standard. Numbers are
 * represented as {@see LegacyJsonNumber} rather than PHP int/float.
 */
final class LegacyJsonCursor
{
    private int $position = 0;

    private readonly int $length;

    public function __construct(private readonly string $source)
    {
        $this->length = strlen($source);
    }

    public function atEnd(): bool
    {
        return $this->position >= $this->length;
    }

    public function skipWhitespace(): void
    {
        while ($this->position < $this->length && strpbrk($this->source[$this->position], " \t\n\r") !== false) {
            $this->position++;
        }
    }

    public function parseValue(): mixed
    {
        $this->skipWhitespace();
        if ($this->atEnd()) {
            throw new LegacyProjectExpenseMalformed('expenses_json_malformed', 'Unexpected end of JSON input.');
        }

        return match ($this->source[$this->position]) {
            '{' => $this->parseObject(),
            '[' => $this->parseArray(),
            '"' => $this->parseString(),
            't' => $this->parseLiteral('true', true),
            'f' => $this->parseLiteral('false', false),
            'n' => $this->parseLiteral('null', null),
            default => $this->parseNumber(),
        };
    }

    /** @return array<string, mixed> */
    private function parseObject(): array
    {
        $this->expect('{');
        $this->skipWhitespace();
        $object = [];
        if ($this->peek() === '}') {
            $this->position++;

            return $object;
        }
        while (true) {
            $this->skipWhitespace();
            $key = $this->parseString();
            $this->skipWhitespace();
            $this->expect(':');
            $object[$key] = $this->parseValue();
            $this->skipWhitespace();
            $next = $this->peek();
            if ($next === ',') {
                $this->position++;

                continue;
            }
            if ($next === '}') {
                $this->position++;
                break;
            }
            throw new LegacyProjectExpenseMalformed('expenses_json_malformed', 'Malformed JSON object.');
        }

        return $object;
    }

    /** @return list<mixed> */
    private function parseArray(): array
    {
        $this->expect('[');
        $this->skipWhitespace();
        $array = [];
        if ($this->peek() === ']') {
            $this->position++;

            return $array;
        }
        while (true) {
            $array[] = $this->parseValue();
            $this->skipWhitespace();
            $next = $this->peek();
            if ($next === ',') {
                $this->position++;

                continue;
            }
            if ($next === ']') {
                $this->position++;
                break;
            }
            throw new LegacyProjectExpenseMalformed('expenses_json_malformed', 'Malformed JSON array.');
        }

        return $array;
    }

    private function parseString(): string
    {
        $this->expect('"');
        $start = $this->position;
        $escaped = false;
        while (true) {
            if ($this->atEnd()) {
                throw new LegacyProjectExpenseMalformed('expenses_json_malformed', 'Unterminated JSON string.');
            }
            $char = $this->source[$this->position];
            if ($char === '\\') {
                $escaped = true;
                $this->position += 2;

                continue;
            }
            if ($char === '"') {
                break;
            }
            $this->position++;
        }
        $raw = substr($this->source, $start, $this->position - $start);
        $this->position++; // closing quote

        if (! $escaped) {
            return $raw;
        }
        $decoded = json_decode('"'.$raw.'"');
        if (! is_string($decoded)) {
            throw new LegacyProjectExpenseMalformed('expenses_json_malformed', 'Malformed JSON string escape.');
        }

        return $decoded;
    }

    private function parseNumber(): LegacyJsonNumber
    {
        $start = $this->position;
        if ($this->peek() === '-') {
            $this->position++;
        }
        while (! $this->atEnd() && ctype_digit($this->source[$this->position])) {
            $this->position++;
        }
        if ($this->peek() === '.') {
            $this->position++;
            while (! $this->atEnd() && ctype_digit($this->source[$this->position])) {
                $this->position++;
            }
        }
        $hasExponent = false;
        if (in_array($this->peek(), ['e', 'E'], true)) {
            $hasExponent = true;
            $this->position++;
            if (in_array($this->peek(), ['+', '-'], true)) {
                $this->position++;
            }
            while (! $this->atEnd() && ctype_digit($this->source[$this->position])) {
                $this->position++;
            }
        }
        $lexeme = substr($this->source, $start, $this->position - $start);
        if ($lexeme === '' || $lexeme === '-') {
            throw new LegacyProjectExpenseMalformed('expenses_json_malformed', 'Malformed JSON number.');
        }

        return new LegacyJsonNumber($lexeme, $hasExponent);
    }

    private function parseLiteral(string $literal, mixed $value): mixed
    {
        if (substr($this->source, $this->position, strlen($literal)) !== $literal) {
            throw new LegacyProjectExpenseMalformed('expenses_json_malformed', 'Malformed JSON literal.');
        }
        $this->position += strlen($literal);

        return $value;
    }

    private function expect(string $char): void
    {
        if ($this->peek() !== $char) {
            throw new LegacyProjectExpenseMalformed('expenses_json_malformed', "Expected '{$char}'.");
        }
        $this->position++;
    }

    private function peek(): ?string
    {
        return $this->position < $this->length ? $this->source[$this->position] : null;
    }
}
