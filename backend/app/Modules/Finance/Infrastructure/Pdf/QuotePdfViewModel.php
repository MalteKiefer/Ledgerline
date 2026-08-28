<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Pdf;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class QuotePdfViewModel
{
    /** @param array<string, mixed> $data */
    private function __construct(
        private array $data,
        private string $documentTitle,
    ) {}

    /** @param array<array-key, mixed> $snapshot */
    public static function fromSnapshot(array $snapshot): self
    {
        if (($snapshot['document_type'] ?? null) !== 'quote') {
            throw new InvalidArgumentException('The PDF renderer accepts only quote snapshots.');
        }

        $currency = self::requiredString($snapshot, 'currency');
        $customer = self::requiredArray($snapshot, 'customer');
        $totals = self::requiredArray($snapshot, 'totals');
        $lines = self::requiredArray($snapshot, 'lines');
        $taxBreakdowns = self::requiredArray($totals, 'tax_breakdowns');
        $netMinor = self::requiredInt($totals, 'net_minor');
        $discountMinor = self::requiredInt($totals, 'discount_minor');

        $revisionLabel = self::requiredString($snapshot, 'revision_label');

        return new self([
            'documentNumber' => self::requiredString($snapshot, 'document_number'),
            'revisionLabel' => $revisionLabel,
            'title' => self::requiredString($snapshot, 'title'),
            'issueDate' => self::date(self::requiredString($snapshot, 'issue_date')),
            'validUntil' => self::date(self::requiredString($snapshot, 'valid_until')),
            'currency' => $currency,
            'customer' => [
                'name' => self::requiredString($customer, 'name'),
                'street' => self::optionalString($customer, 'street'),
                'postalCode' => self::optionalString($customer, 'postal_code'),
                'city' => self::optionalString($customer, 'city'),
                'country' => self::optionalString($customer, 'country'),
                'email' => self::optionalString($customer, 'email'),
            ],
            'lines' => self::lines($lines, $currency),
            'taxBreakdowns' => self::taxBreakdowns($taxBreakdowns, $currency),
            'subtotal' => self::money($netMinor + $discountMinor, $currency),
            'net' => self::money($netMinor, $currency),
            'vat' => self::money(self::requiredInt($totals, 'vat_minor'), $currency),
            'gross' => self::money(self::requiredInt($totals, 'gross_minor'), $currency),
            'discount' => self::money($discountMinor, $currency),
            'introText' => self::optionalString($snapshot, 'intro_text'),
            'outroText' => self::optionalString($snapshot, 'outro_text'),
            'customerNote' => self::optionalString($snapshot, 'customer_note'),
        ], 'Angebot '.$revisionLabel);
    }

    /** @return array<string, mixed> */
    public function viewData(): array
    {
        return $this->data;
    }

    public function documentTitle(): string
    {
        return $this->documentTitle;
    }

    /**
     * @param  array<array-key, mixed>  $lines
     * @return list<array{description: string, quantity: string, unit: string, unitPrice: string, taxRate: string, net: string}>
     */
    private static function lines(array $lines, string $currency): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('A quote PDF requires at least one line.');
        }

        $result = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new InvalidArgumentException('Quote PDF lines must be arrays.');
            }
            $quantityScaled = self::requiredInt($line, 'quantity_scaled');
            $unitPriceMinor = self::requiredInt($line, 'unit_price_minor');
            $lineNetMinor = self::multiplyAndRound($quantityScaled, $unitPriceMinor);
            $result[] = [
                'description' => self::requiredString($line, 'description'),
                'quantity' => self::requiredString($line, 'quantity'),
                'unit' => self::requiredString($line, 'unit'),
                'unitPrice' => self::money($unitPriceMinor, $currency),
                'taxRate' => self::percentage(self::requiredInt($line, 'tax_rate_basis_points')),
                'net' => self::money($lineNetMinor, $currency),
            ];
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $breakdowns
     * @return list<array{rate: string, net: string, vat: string, gross: string}>
     */
    private static function taxBreakdowns(array $breakdowns, string $currency): array
    {
        $result = [];
        foreach ($breakdowns as $breakdown) {
            if (! is_array($breakdown)) {
                throw new InvalidArgumentException('Quote PDF tax breakdowns must be arrays.');
            }
            $result[] = [
                'rate' => self::percentage(self::requiredInt($breakdown, 'tax_rate_basis_points')),
                'net' => self::money(self::requiredInt($breakdown, 'net_minor'), $currency),
                'vat' => self::money(self::requiredInt($breakdown, 'vat_minor'), $currency),
                'gross' => self::money(self::requiredInt($breakdown, 'gross_minor'), $currency),
            ];
        }

        return $result;
    }

    /** @param array<array-key, mixed> $value */
    private static function requiredString(array $value, string $key): string
    {
        $item = $value[$key] ?? null;
        if (! is_string($item) || trim($item) === '') {
            throw new InvalidArgumentException("Quote PDF field {$key} must be a non-empty string.");
        }

        return $item;
    }

    /** @param array<array-key, mixed> $value */
    private static function optionalString(array $value, string $key): ?string
    {
        $item = $value[$key] ?? null;
        if ($item !== null && ! is_string($item)) {
            throw new InvalidArgumentException("Quote PDF field {$key} must be a string or null.");
        }

        return $item;
    }

    /** @param array<array-key, mixed> $value */
    private static function requiredInt(array $value, string $key): int
    {
        $item = $value[$key] ?? null;
        if (! is_int($item)) {
            throw new InvalidArgumentException("Quote PDF field {$key} must be an integer.");
        }

        return $item;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function requiredArray(array $value, string $key): array
    {
        $item = $value[$key] ?? null;
        if (! is_array($item)) {
            throw new InvalidArgumentException("Quote PDF field {$key} must be an array.");
        }

        return $item;
    }

    private static function date(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Quote PDF dates must use YYYY-MM-DD.');
        }

        return $date->format('d.m.Y');
    }

    private static function percentage(int $basisPoints): string
    {
        return self::decimal($basisPoints, 2);
    }

    private static function money(int $minor, string $currency): string
    {
        return self::decimal($minor, 2).' '.$currency;
    }

    private static function decimal(int $scaled, int $scale): string
    {
        $negative = $scaled < 0;
        $digits = ltrim((string) $scaled, '-');
        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$scale);
        $fraction = substr($digits, -$scale);

        return ($negative ? '-' : '').$whole.','.$fraction;
    }

    private static function multiplyAndRound(int $quantityScaled, int $unitPriceMinor): int
    {
        $product = $quantityScaled * $unitPriceMinor;
        $absolute = abs($product);
        $rounded = intdiv($absolute + 5_000, 10_000);

        return $product < 0 ? -$rounded : $rounded;
    }
}
