<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProjectQuoteSource
{
    public string $title;

    public ?int $partnerId;

    public ?string $partnerReference;

    public ?DateTimeImmutable $issuedOn;

    public ?DateTimeImmutable $validUntil;

    public string $currency;

    public int $netMinor;

    public int $vatMinor;

    public int $grossMinor;

    /** @var list<array{description:string,quantity_scaled:int,unit_price_minor:int,currency:string,tax_rate_basis_points:int,quantity:string,unit:string,unit_price:string,tax_rate:string,kind:string,product_id:int|null}> */
    public array $lines;

    /** @param array<string,mixed> $snapshot */
    public function __construct(public string $seriesUuid, public int $revisionId, public string $snapshotSha256, public ?string $number, public ?string $label, public array $snapshot)
    {
        if ($revisionId < 1 || preg_match('/\A[0-9a-f]{64}\z/D', $snapshotSha256) !== 1 || self::containsFloat($snapshot)) {
            throw new InvalidArgumentException('project_quote_source_invalid');
        }
        $title = $snapshot['title'] ?? null;
        $partnerId = $snapshot['partner_id'] ?? null;
        $issued = $snapshot['issue_date'] ?? null;
        $valid = $snapshot['valid_until'] ?? null;
        $currency = $snapshot['currency'] ?? null;
        $totals = $snapshot['totals'] ?? null;
        $lines = $snapshot['lines'] ?? null;
        if (! is_string($title) || trim($title) === '' || ($partnerId !== null && (! is_int($partnerId) || $partnerId < 1)) || ($issued !== null && ! is_string($issued)) || ($valid !== null && ! is_string($valid)) || ! is_string($currency) || ! is_array($totals) || ! is_array($lines)) {
            throw new InvalidArgumentException('project_quote_snapshot_invalid');
        }
        foreach (['net_minor', 'vat_minor', 'gross_minor'] as $key) {
            if (! isset($totals[$key]) || ! is_int($totals[$key])) {
                throw new InvalidArgumentException('project_quote_snapshot_invalid');
            }
        }
        $canonical = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                throw new InvalidArgumentException('project_quote_line_invalid');
            }
            $canonical[] = self::canonicalLine($line, $currency);
        }
        $this->title = trim($title);
        $this->partnerId = $partnerId;
        $this->partnerReference = $partnerId !== null ? 'legacy-partner:'.$partnerId : null;
        $this->issuedOn = $issued !== null ? new DateTimeImmutable($issued) : null;
        $this->validUntil = $valid !== null ? new DateTimeImmutable($valid) : null;
        $this->currency = Money::fromMinor(0, $currency)->currency();
        $this->netMinor = Money::fromMinor($totals['net_minor'], $currency)->minor();
        $this->vatMinor = Money::fromMinor($totals['vat_minor'], $currency)->minor();
        $this->grossMinor = Money::fromMinor($totals['gross_minor'], $currency)->minor();
        $this->lines = $canonical;
    }

    public function withSnapshotSha256(string $hash): self
    {
        return new self($this->seriesUuid, $this->revisionId, $hash, $this->number, $this->label, $this->snapshot);
    }

    /**
     * @param  array<array-key,mixed>  $line
     * @return array{description:string,quantity_scaled:int,unit_price_minor:int,currency:string,tax_rate_basis_points:int,quantity:string,unit:string,unit_price:string,tax_rate:string,kind:string,product_id:int|null}
     */
    private static function canonicalLine(array $line, string $currency): array
    {
        $description = $line['description'] ?? null;
        $quantity = $line['quantity'] ?? null;
        $unit = $line['unit'] ?? null;
        $unitPrice = $line['unit_price'] ?? null;
        $taxRate = $line['tax_rate'] ?? null;
        $kind = $line['kind'] ?? null;
        $lineCurrency = $line['currency'] ?? null;
        $quantityScaled = $line['quantity_scaled'] ?? null;
        $unitPriceMinor = $line['unit_price_minor'] ?? null;
        $taxRateBasisPoints = $line['tax_rate_basis_points'] ?? null;
        $product = $line['product_id'] ?? null;
        if (! is_string($description) || ! is_string($quantity) || ! is_string($unit) || ! is_string($unitPrice) || ! is_string($taxRate) || ! is_string($kind) || ! is_string($lineCurrency) || ! is_int($quantityScaled) || ! is_int($unitPriceMinor) || ! is_int($taxRateBasisPoints) || ($product !== null && (! is_int($product) || $product < 1)) || DecimalQuantity::fromString($quantity)->scaled() !== $quantityScaled || $lineCurrency !== $currency) {
            throw new InvalidArgumentException('project_quote_line_invalid');
        }

        return ['description' => $description, 'quantity_scaled' => $quantityScaled, 'unit_price_minor' => $unitPriceMinor, 'currency' => $lineCurrency, 'tax_rate_basis_points' => $taxRateBasisPoints, 'quantity' => $quantity, 'unit' => $unit, 'unit_price' => $unitPrice, 'tax_rate' => $taxRate, 'kind' => $kind, 'product_id' => $product];
    }

    /** @param array<array-key,mixed> $value */
    private static function containsFloat(array $value): bool
    {
        foreach ($value as $item) {
            if (is_float($item) || (is_array($item) && self::containsFloat($item))) {
                return true;
            }
        }

        return false;
    }
}
