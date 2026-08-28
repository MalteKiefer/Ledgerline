<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Application\Services\CanonicalDocumentSnapshot;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProjectQuoteSource
{
    private const array SNAPSHOT_KEYS = [
        'currency', 'customer', 'customer_note', 'discount', 'document_number',
        'document_type', 'intro_text', 'issue_date', 'lines', 'outro_text',
        'partner_id', 'revision_label', 'revision_number', 'schema_version',
        'series_uuid', 'title', 'totals', 'valid_until',
    ];

    private const array LINE_KEYS = [
        'currency', 'description', 'kind', 'product_id', 'quantity', 'quantity_scaled',
        'tax_rate', 'tax_rate_basis_points', 'unit', 'unit_price', 'unit_price_minor',
    ];

    public string $seriesUuid;

    public int $revisionId;

    public string $snapshotSha256;

    public string $number;

    public string $label;

    /** @var array<string, mixed> */
    public array $snapshot;

    public int $revisionNumber;

    public string $title;

    public ?int $partnerId;

    public ?string $partnerReference;

    public DateTimeImmutable $issuedOn;

    public DateTimeImmutable $validUntil;

    public string $currency;

    public int $netMinor;

    public int $vatMinor;

    public int $grossMinor;

    /** @var list<array{description:string,quantity_scaled:int,unit_price_minor:int,currency:string,tax_rate_basis_points:int,quantity:string,unit:string,unit_price:string,tax_rate:string,kind:string,product_id:int|null}> */
    public array $lines;

    /** @param array<string,mixed> $snapshot */
    public function __construct(string $seriesUuid, int $revisionId, string $snapshotSha256, ?string $number, ?string $label, array $snapshot)
    {
        if ($revisionId < 1 || preg_match('/\A[0-9a-f]{64}\z/D', $snapshotSha256) !== 1) {
            throw new InvalidArgumentException('project_quote_source_invalid');
        }

        $canonical = self::canonicalSnapshot($snapshot);
        $actualDigest = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
        if (! hash_equals($actualDigest, $snapshotSha256)) {
            throw new InvalidArgumentException('project_quote_snapshot_hash_mismatch');
        }
        if (array_keys($canonical) !== self::SNAPSHOT_KEYS
            || ($canonical['schema_version'] ?? null) !== 1
            || ($canonical['document_type'] ?? null) !== 'quote'
            || ($canonical['series_uuid'] ?? null) !== $seriesUuid
            || ! is_string($number) || trim($number) === ''
            || ($canonical['document_number'] ?? null) !== $number
            || ! is_string($label) || trim($label) === ''
            || ($canonical['revision_label'] ?? null) !== $label) {
            throw new InvalidArgumentException('project_quote_snapshot_invalid');
        }

        $revisionNumber = $canonical['revision_number'];
        $title = $canonical['title'];
        $customer = $canonical['customer'];
        $partnerId = $canonical['partner_id'];
        $currency = $canonical['currency'];
        $totals = $canonical['totals'];
        $lines = $canonical['lines'];
        if (! is_int($revisionNumber) || $revisionNumber < 1
            || ! is_string($title) || trim($title) === ''
            || ! is_array($customer) || ! is_string($customer['name'] ?? null) || trim($customer['name']) === ''
            || ($partnerId !== null && (! is_int($partnerId) || $partnerId < 1))
            || ! is_string($currency) || Money::fromMinor(0, $currency)->currency() !== $currency
            || ! is_array($totals) || ! is_array($lines) || $lines === [] || count($lines) > 200
            || ! self::nullableString($canonical['intro_text'])
            || ! self::nullableString($canonical['outro_text'])
            || ! self::nullableString($canonical['customer_note'])) {
            throw new InvalidArgumentException('project_quote_snapshot_invalid');
        }

        $issuedOn = self::date($canonical['issue_date']);
        $validUntil = self::date($canonical['valid_until']);
        if ($validUntil < $issuedOn) {
            throw new InvalidArgumentException('project_quote_snapshot_invalid');
        }

        $domainLines = [];
        $canonicalLines = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new InvalidArgumentException('project_quote_line_invalid');
            }
            $parsed = self::canonicalLine($line, $currency);
            $canonicalLines[] = $parsed['canonical'];
            $domainLines[] = $parsed['domain'];
        }
        $discount = self::discount($canonical['discount'], $currency);
        $calculated = (new DocumentCalculator)->calculate($domainLines, $discount);
        $expectedTotals = [
            'currency' => $currency,
            'discount_minor' => $calculated->discount->minor(),
            'gross_minor' => $calculated->gross->minor(),
            'net_minor' => $calculated->net->minor(),
            'tax_breakdowns' => array_map(static fn ($breakdown): array => [
                'gross_minor' => $breakdown->gross->minor(),
                'net_minor' => $breakdown->net->minor(),
                'tax_rate_basis_points' => $breakdown->taxRateBasisPoints,
                'vat_minor' => $breakdown->vat->minor(),
            ], $calculated->taxBreakdowns),
            'vat_minor' => $calculated->vat->minor(),
        ];
        if ($totals !== $expectedTotals) {
            throw new InvalidArgumentException('project_quote_totals_invalid');
        }

        $this->seriesUuid = $seriesUuid;
        $this->revisionId = $revisionId;
        $this->snapshotSha256 = $snapshotSha256;
        $this->number = $number;
        $this->label = $label;
        $this->snapshot = $canonical;
        $this->revisionNumber = $revisionNumber;
        $this->title = trim($title);
        $this->partnerId = $partnerId;
        $this->partnerReference = $partnerId !== null ? 'legacy-partner:'.$partnerId : null;
        $this->issuedOn = $issuedOn;
        $this->validUntil = $validUntil;
        $this->currency = $currency;
        $this->netMinor = $calculated->net->minor();
        $this->vatMinor = $calculated->vat->minor();
        $this->grossMinor = $calculated->gross->minor();
        $this->lines = $canonicalLines;
    }

    public function canonicalSnapshotSha256(): string
    {
        return hash('sha256', json_encode($this->snapshot, JSON_THROW_ON_ERROR));
    }

    public function assertSnapshotIntegrity(): void
    {
        if (! hash_equals($this->canonicalSnapshotSha256(), $this->snapshotSha256)) {
            throw new InvalidArgumentException('project_quote_snapshot_hash_mismatch');
        }
    }

    public function withSnapshotSha256(string $hash): self
    {
        return new self($this->seriesUuid, $this->revisionId, $hash, $this->number, $this->label, $this->snapshot);
    }

    /**
     * @param  array<array-key,mixed>  $line
     * @return array{canonical:array{description:string,quantity_scaled:int,unit_price_minor:int,currency:string,tax_rate_basis_points:int,quantity:string,unit:string,unit_price:string,tax_rate:string,kind:string,product_id:int|null},domain:DocumentLine}
     */
    private static function canonicalLine(array $line, string $currency): array
    {
        if (array_keys($line) !== self::LINE_KEYS) {
            throw new InvalidArgumentException('project_quote_line_invalid');
        }
        $description = $line['description'];
        $quantity = $line['quantity'];
        $unit = $line['unit'];
        $unitPrice = $line['unit_price'];
        $taxRate = $line['tax_rate'];
        $kind = $line['kind'];
        $lineCurrency = $line['currency'];
        $quantityScaled = $line['quantity_scaled'];
        $unitPriceMinor = $line['unit_price_minor'];
        $taxRateBasisPoints = $line['tax_rate_basis_points'];
        $product = $line['product_id'];
        if (! is_string($description) || trim($description) === ''
            || ! is_string($quantity) || ! is_string($unit) || trim($unit) === ''
            || ! is_string($unitPrice) || ! is_string($taxRate)
            || ! is_string($kind) || ! in_array($kind, ['service', 'hardware'], true)
            || ! is_string($lineCurrency) || $lineCurrency !== $currency
            || ! is_int($quantityScaled) || ! is_int($unitPriceMinor) || ! is_int($taxRateBasisPoints)
            || ($product !== null && (! is_int($product) || $product < 1))) {
            throw new InvalidArgumentException('project_quote_line_invalid');
        }
        $parsedQuantity = DecimalQuantity::fromString($quantity);
        $parsedUnitPrice = Money::fromDecimal($unitPrice, $currency);
        $parsedTaxRate = Money::fromDecimal($taxRate, 'BPS')->minor();
        if ($parsedQuantity->scaled() !== $quantityScaled
            || $parsedUnitPrice->minor() !== $unitPriceMinor
            || $parsedTaxRate !== $taxRateBasisPoints
            || $parsedTaxRate < 0 || $parsedTaxRate > 10_000) {
            throw new InvalidArgumentException('project_quote_line_invalid');
        }

        return [
            'canonical' => [
                'currency' => $lineCurrency,
                'description' => $description,
                'kind' => $kind,
                'product_id' => $product,
                'quantity' => $quantity,
                'quantity_scaled' => $quantityScaled,
                'tax_rate' => $taxRate,
                'tax_rate_basis_points' => $taxRateBasisPoints,
                'unit' => $unit,
                'unit_price' => $unitPrice,
                'unit_price_minor' => $unitPriceMinor,
            ],
            'domain' => new DocumentLine($description, $parsedQuantity, $parsedUnitPrice, $parsedTaxRate),
        ];
    }

    private static function discount(mixed $value, string $currency): Discount
    {
        if (! is_array($value) || ($value['currency'] ?? null) !== $currency || ! is_string($value['type'] ?? null)) {
            throw new InvalidArgumentException('project_quote_discount_invalid');
        }
        $type = $value['type'];

        return match ($type) {
            'none' => array_keys($value) === ['currency', 'type', 'value']
                && in_array($value['value'], [null, '0', '0.00'], true)
                    ? Discount::none($currency)
                    : throw new InvalidArgumentException('project_quote_discount_invalid'),
            'percent' => self::percentDiscount($value, $currency),
            'fixed' => self::fixedDiscount($value, $currency),
            default => throw new InvalidArgumentException('project_quote_discount_invalid'),
        };
    }

    /** @param array<array-key, mixed> $value */
    private static function percentDiscount(array $value, string $currency): Discount
    {
        $decimal = $value['value'] ?? null;
        $basisPoints = $value['basis_points'] ?? null;
        if (array_keys($value) !== ['basis_points', 'currency', 'type', 'value']
            || ! is_string($decimal) || ! is_int($basisPoints)
            || Money::fromDecimal($decimal, 'BPS')->minor() !== $basisPoints) {
            throw new InvalidArgumentException('project_quote_discount_invalid');
        }

        return Discount::percentBasisPoints($basisPoints, $currency);
    }

    /** @param array<array-key, mixed> $value */
    private static function fixedDiscount(array $value, string $currency): Discount
    {
        $decimal = $value['value'] ?? null;
        $minor = $value['minor'] ?? null;
        if (array_keys($value) !== ['currency', 'minor', 'type', 'value']
            || ! is_string($decimal) || ! is_int($minor)
            || Money::fromDecimal($decimal, $currency)->minor() !== $minor) {
            throw new InvalidArgumentException('project_quote_discount_invalid');
        }

        return Discount::fixed(Money::fromMinor($minor, $currency));
    }

    private static function date(mixed $value): DateTimeImmutable
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('project_quote_snapshot_invalid');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('project_quote_snapshot_invalid');
        }

        return $date;
    }

    private static function nullableString(mixed $value): bool
    {
        return $value === null || is_string($value);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private static function canonicalSnapshot(array $snapshot): array
    {
        $normalized = [];
        foreach ((new CanonicalDocumentSnapshot)->canonicalize($snapshot) as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('project_quote_snapshot_invalid');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
