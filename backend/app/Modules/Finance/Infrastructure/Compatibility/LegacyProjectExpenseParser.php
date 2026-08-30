<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Compatibility\Exception\LegacyProjectExpenseMalformed;

/**
 * Parses the raw JSON text of a legacy `finance_projects.expenses` column
 * into exact {@see LegacyProjectExpenseRow} values.
 *
 * The column has no fixed shape — it was always hand-typed rows through a
 * generic array field — so every row is tolerant of missing keys, but the
 * amount itself is never trusted to `json_decode()`'s float cast. This class
 * hand-tokenizes the raw text instead: every numeric lexeme is captured as
 * its exact source substring and handed to {@see Money::fromDecimal}, so a
 * value with more than two fraction digits, exponent notation, or leading/
 * trailing noise fails loudly instead of silently losing precision.
 */
final class LegacyProjectExpenseParser
{
    private const int MAX_ROWS = 5000;

    /**
     * @return list<LegacyProjectExpenseRow>
     *
     * @throws LegacyProjectExpenseMalformed
     */
    public function parse(string $rawJson, string $currency): array
    {
        $trimmed = trim($rawJson);
        if ($trimmed === '' || $trimmed === 'null') {
            return [];
        }

        $cursor = new LegacyJsonCursor($trimmed);
        $value = $cursor->parseValue();
        $cursor->skipWhitespace();
        if (! $cursor->atEnd()) {
            throw new LegacyProjectExpenseMalformed('expenses_json_malformed', 'Trailing data after the top-level JSON value.');
        }
        if (! is_array($value) || ! array_is_list($value)) {
            throw new LegacyProjectExpenseMalformed('expenses_json_malformed', 'Legacy expenses must be a JSON array.');
        }
        if (count($value) > self::MAX_ROWS) {
            throw new LegacyProjectExpenseMalformed('expenses_row_cap_exceeded', 'Legacy expenses exceed the 5000-row cap.');
        }

        $rows = [];
        foreach ($value as $index => $row) {
            $rows[] = $this->mapRow($row, $currency, $index);
        }

        return $rows;
    }

    private function mapRow(mixed $rawRow, string $currency, int $index): LegacyProjectExpenseRow
    {
        $row = $this->expectObjectRow($rawRow, $index);

        $amountValue = $row['amount'] ?? null;
        if ($amountValue instanceof LegacyJsonNumber) {
            $amountLexeme = $amountValue->lexeme;
            $hasExponent = $amountValue->hasExponent;
        } elseif (is_string($amountValue) && $amountValue !== '') {
            // A numeric-shaped decimal typed as a JSON string is just as exact
            // as a bare number lexeme — both are handed to Money::fromDecimal
            // as raw text, never through a float cast.
            $amountLexeme = $amountValue;
            $hasExponent = stripos($amountValue, 'e') !== false;
        } else {
            throw new LegacyProjectExpenseMalformed('expenses_amount_missing', "Expense row #{$index} has no numeric amount.");
        }
        if ($hasExponent) {
            throw new LegacyProjectExpenseMalformed('expenses_amount_exponent', "Expense row #{$index} amount uses exponent notation.");
        }

        $rowCurrency = $this->scalarString($row['currency'] ?? null) ?? $currency;
        if (strtoupper($rowCurrency) !== strtoupper($currency)) {
            throw new LegacyProjectExpenseMalformed('expenses_currency_ambiguous', "Expense row #{$index} currency does not match the project currency.");
        }

        try {
            $money = Money::fromDecimal($amountLexeme, $currency);
        } catch (InvalidMoney $exception) {
            throw new LegacyProjectExpenseMalformed('expenses_amount_invalid', "Expense row #{$index}: {$exception->getMessage()}");
        }

        $direction = $this->direction($row, $money->minor(), $index);
        $amountMinor = abs($money->minor());
        if ($amountMinor === 0) {
            throw new LegacyProjectExpenseMalformed('expenses_amount_zero', "Expense row #{$index} amount must be nonzero.");
        }

        $known = ['amount', 'currency', 'direction', 'type', 'title', 'name', 'note', 'description', 'date', 'occurred_on', 'category', 'category_id', 'payment_method', 'payment_method_id', 'id'];
        $legacyMetadata = array_diff_key($row, array_flip($known));
        $legacyMetadata = $this->normalizeLeaves($legacyMetadata);

        return new LegacyProjectExpenseRow(
            direction: $direction,
            amountMinor: $amountMinor,
            currency: strtoupper($currency),
            occurredOn: $this->scalarString($row['occurred_on'] ?? $row['date'] ?? null),
            title: $this->scalarString($row['title'] ?? $row['name'] ?? null),
            note: $this->scalarString($row['note'] ?? $row['description'] ?? null),
            categoryReference: $this->reference('legacy-category', $row['category_id'] ?? $row['category'] ?? null),
            paymentMethodReference: $this->reference('legacy-payment-method', $row['payment_method_id'] ?? $row['payment_method'] ?? null),
            legacyMetadata: $legacyMetadata,
        );
    }

    /** @param  array<string, mixed>  $row */
    private function direction(array $row, int $signedMinor, int $index): string
    {
        $declared = $this->scalarString($row['direction'] ?? $row['type'] ?? null);
        if ($declared !== null) {
            $normalized = strtolower($declared);
            $map = ['in' => 'in', 'income' => 'in', 'out' => 'out', 'expense' => 'out', 'outgoing' => 'out'];
            if (! isset($map[$normalized])) {
                throw new LegacyProjectExpenseMalformed('expenses_direction_invalid', "Expense row #{$index} has an unrecognized direction.");
            }

            return $map[$normalized];
        }

        // No declared direction: a manual "expenses" list is overwhelmingly
        // spend, so a plain positive amount is `out`; only an explicit
        // negative lexeme is treated as income.
        return $signedMinor < 0 ? 'in' : 'out';
    }

    private function reference(string $prefix, mixed $value): ?string
    {
        if ($value instanceof LegacyJsonNumber) {
            return "{$prefix}:{$value->lexeme}";
        }
        $string = $this->scalarString($value);

        return $string === null ? null : "{$prefix}:{$string}";
    }

    private function scalarString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value === '' ? null : $value;
        }
        if ($value instanceof LegacyJsonNumber) {
            return $value->lexeme;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $leaves
     * @return array<string, mixed>
     */
    private function normalizeLeaves(array $leaves): array
    {
        $normalized = [];
        foreach ($leaves as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof LegacyJsonNumber) {
            return $value->lexeme;
        }
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function expectObjectRow(mixed $row, int $index): array
    {
        if (! is_array($row) || array_is_list($row)) {
            throw new LegacyProjectExpenseMalformed('expenses_row_not_object', "Expense row #{$index} must be a JSON object.");
        }

        $object = [];
        foreach ($row as $key => $value) {
            // A JSON object key is always a string, but PHP silently casts a
            // numeric string key (e.g. `{"2": ...}`) to an integer array key.
            // Reject that shape explicitly rather than losing the distinction.
            if (! is_string($key)) {
                throw new LegacyProjectExpenseMalformed('expenses_row_not_object', "Expense row #{$index} must be a JSON object.");
            }
            $object[$key] = $value;
        }

        return $object;
    }
}
