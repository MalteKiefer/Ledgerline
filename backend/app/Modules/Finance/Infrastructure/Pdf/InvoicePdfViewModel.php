<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Pdf;

use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class InvoicePdfViewModel
{
    /** @param array<string, mixed> $data */
    private function __construct(
        private array $data,
        private string $documentTitle,
    ) {}

    /** @param array<array-key, mixed> $snapshot */
    public static function fromSnapshot(array $snapshot): self
    {
        if (($snapshot['document_type'] ?? null) !== 'invoice') {
            throw new InvalidArgumentException('The invoice PDF renderer accepts only invoice snapshots.');
        }
        if (($snapshot['schema_version'] ?? null) !== 1) {
            throw new InvalidArgumentException('The invoice PDF snapshot schema is unsupported.');
        }

        $kind = self::requiredString($snapshot, 'invoice_kind');
        if (! in_array($kind, ['invoice', 'credit_note'], true)) {
            throw new InvalidArgumentException('The invoice PDF kind is invalid.');
        }

        $currency = self::requiredString($snapshot, 'currency');
        $company = self::requiredArray($snapshot, 'company');
        $customer = self::requiredArray($snapshot, 'customer');
        $totals = self::requiredArray($snapshot, 'totals');
        $lines = self::requiredArray($snapshot, 'lines');
        $taxBreakdowns = self::requiredArray($totals, 'tax_breakdowns');
        $discount = self::requiredArray($snapshot, 'discount');
        self::assertCanonicalTotals($lines, $discount, $totals, $currency);

        $number = self::requiredString($snapshot, 'document_number');
        $title = ($kind === 'credit_note' ? 'Gutschrift ' : 'Rechnung ').$number;
        $netMinor = self::requiredInt($totals, 'net_minor');
        $discountMinor = self::requiredInt($totals, 'discount_minor');

        return new self([
            'documentNumber' => $number,
            'revisionNumber' => self::requiredPositiveInt($snapshot, 'revision_number'),
            'kindLabel' => $kind === 'credit_note' ? 'Gutschrift' : 'Rechnung',
            'issueDate' => self::date(self::requiredString($snapshot, 'issue_date')),
            'dueDate' => self::date(self::requiredString($snapshot, 'due_date')),
            'currency' => $currency,
            'company' => [
                'name' => self::optionalString($company, 'name'),
                'address' => self::optionalString($company, 'address'),
                'email' => self::optionalString($company, 'email'),
                'phone' => self::optionalString($company, 'phone'),
                'taxId' => self::optionalString($company, 'tax_id'),
                'vatId' => self::optionalString($company, 'vat_id'),
                'iban' => self::optionalString($company, 'iban'),
                'bic' => self::optionalString($company, 'bic'),
                'bankName' => self::optionalString($company, 'bank_name'),
                'website' => self::optionalString($company, 'website'),
            ],
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
        ], $title);
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
     * @return list<array{description: string, quantity: string, unit: string, unitPrice: string, taxRate: string}>
     */
    private static function lines(array $lines, string $currency): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('An invoice PDF requires at least one line.');
        }

        $result = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new InvalidArgumentException('Invoice PDF lines must be arrays.');
            }
            $result[] = [
                'description' => self::requiredString($line, 'description'),
                'quantity' => self::requiredString($line, 'quantity'),
                'unit' => self::requiredString($line, 'unit'),
                'unitPrice' => self::money(self::requiredInt($line, 'unit_price_minor'), $currency),
                'taxRate' => self::percentage(self::requiredInt($line, 'tax_rate_basis_points')),
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
                throw new InvalidArgumentException('Invoice PDF tax breakdowns must be arrays.');
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

    /**
     * @param  array<array-key, mixed>  $lines
     * @param  array<array-key, mixed>  $discount
     * @param  array<array-key, mixed>  $totals
     */
    private static function assertCanonicalTotals(
        array $lines,
        array $discount,
        array $totals,
        string $currency,
    ): void {
        $documentLines = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new InvalidArgumentException('Invoice PDF lines must be arrays.');
            }
            $quantity = self::requiredString($line, 'quantity');
            if (DecimalQuantity::fromString($quantity)->scaled() !== self::requiredInt($line, 'quantity_scaled')) {
                throw new InvalidArgumentException('Invoice PDF quantity metadata is inconsistent.');
            }
            $documentLines[] = new DocumentLine(
                self::requiredString($line, 'description'),
                DecimalQuantity::fromString($quantity),
                Money::fromMinor(self::requiredInt($line, 'unit_price_minor'), $currency),
                self::requiredInt($line, 'tax_rate_basis_points'),
            );
        }

        $basisPoints = self::requiredInt($discount, 'basis_points');
        $fixedMinor = self::requiredInt($discount, 'fixed_minor');
        if (($discount['currency'] ?? null) !== $currency || ($basisPoints !== 0 && $fixedMinor !== 0)) {
            throw new InvalidArgumentException('Invoice PDF discount metadata is inconsistent.');
        }
        $discountValue = $basisPoints !== 0
            ? Discount::percentBasisPoints($basisPoints, $currency)
            : ($fixedMinor !== 0
                ? Discount::fixed(Money::fromMinor($fixedMinor, $currency))
                : Discount::none($currency));
        $calculated = (new DocumentCalculator)->calculate($documentLines, $discountValue);
        $expected = [
            'net_minor' => $calculated->net->minor(),
            'vat_minor' => $calculated->vat->minor(),
            'gross_minor' => $calculated->gross->minor(),
            'discount_minor' => $calculated->discount->minor(),
            'currency' => $currency,
            'tax_breakdowns' => array_map(
                static fn ($item): array => [
                    'tax_rate_basis_points' => $item->taxRateBasisPoints,
                    'net_minor' => $item->net->minor(),
                    'vat_minor' => $item->vat->minor(),
                    'gross_minor' => $item->gross->minor(),
                ],
                $calculated->taxBreakdowns,
            ),
        ];
        if (self::canonicalize($totals) !== self::canonicalize($expected)) {
            throw new InvalidArgumentException('Invoice PDF totals do not match the canonical line calculation.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function canonicalize(array $value): array
    {
        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[$key] = is_array($item) ? self::canonicalize($item) : $item;
        }
        if (! array_is_list($canonical)) {
            ksort($canonical, SORT_STRING);
        }

        return $canonical;
    }

    /** @param array<array-key, mixed> $value */
    private static function requiredString(array $value, string $key): string
    {
        $item = $value[$key] ?? null;
        if (! is_string($item) || trim($item) === '') {
            throw new InvalidArgumentException("Invoice PDF field {$key} must be a non-empty string.");
        }

        return $item;
    }

    /** @param array<array-key, mixed> $value */
    private static function optionalString(array $value, string $key): ?string
    {
        $item = $value[$key] ?? null;
        if ($item !== null && ! is_string($item)) {
            throw new InvalidArgumentException("Invoice PDF field {$key} must be a string or null.");
        }

        return $item;
    }

    /** @param array<array-key, mixed> $value */
    private static function requiredInt(array $value, string $key): int
    {
        $item = $value[$key] ?? null;
        if (! is_int($item)) {
            throw new InvalidArgumentException("Invoice PDF field {$key} must be an integer.");
        }

        return $item;
    }

    /** @param array<array-key, mixed> $value */
    private static function requiredPositiveInt(array $value, string $key): int
    {
        $item = self::requiredInt($value, $key);
        if ($item < 1) {
            throw new InvalidArgumentException("Invoice PDF field {$key} must be positive.");
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
            throw new InvalidArgumentException("Invoice PDF field {$key} must be an array.");
        }

        return $item;
    }

    private static function date(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Invoice PDF dates must use YYYY-MM-DD.');
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
}
