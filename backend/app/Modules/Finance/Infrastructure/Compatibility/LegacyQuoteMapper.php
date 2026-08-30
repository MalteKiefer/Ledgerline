<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\FinancePartner;
use App\Models\FinanceProduct;
use App\Models\FinanceQuote;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\DocumentCalculator;
use App\Modules\Finance\Domain\Shared\DocumentLine;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use App\Modules\Finance\Domain\Shared\Exception\InvalidDocument;
use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;
use App\Modules\Finance\Domain\Shared\Exception\InvalidQuantity;
use App\Modules\Finance\Domain\Shared\Money;
use App\Support\BlobStore;
use LogicException;

/**
 * Deterministic, side-effect-free translation of one legacy `finance_quotes`
 * row into the shape the later `finance-legacy-migration` plan writes as a
 * `source_type=legacy.finance_quote` / `source_id={legacy id}` aggregate.
 *
 * This class performs no writes to any new-module table and does not run a
 * bulk migration; it only reads the legacy row, the owner's partner/product
 * tables for ownership checks, and (for a numbered row) the legacy PDF bytes
 * on the shared blob disk. Every legacy quote maps to exactly one of two
 * outcomes: a mapped row (the `map()` array return) or a
 * `LegacyQuoteDiagnostic` describing why it cannot migrate yet. `map()`
 * called twice on an unchanged row returns byte-identical output — required
 * so the later migration can treat this as a pure, replayable step and
 * produce exact per-owner/year/currency counts.
 *
 * `series_uuid` in the returned snapshot is `null`: a legacy row has no UUID
 * of its own, and minting one is the later migration's job, not this pure
 * mapper's.
 */
final readonly class LegacyQuoteMapper
{
    public const string SOURCE_TYPE = 'legacy.finance_quote';

    public function __construct(private DocumentCalculator $calculator) {}

    /**
     * @return array{
     *     source_type: string,
     *     source_id: int,
     *     owner_id: int,
     *     status: string,
     *     kind: 'draft'|'published',
     *     deleted_at: string|null,
     *     accepted_at: string|null,
     *     declined_at: string|null,
     *     conversions: list<array{target_type: string, target_reference: string, target_id: null, resolved: false}>,
     *     draft?: array{payload: array<string, mixed>, net_minor: int, vat_minor: int, gross_minor: int, currency: string},
     *     revision?: array{
     *         number: string, sequence_year: int, sequence_number: int, snapshot: array<string, mixed>,
     *         net_minor: int, vat_minor: int, gross_minor: int, currency: string,
     *         pdf_path: string, pdf_sha256: string, published_at: string|null, created_at: string|null,
     *     },
     * }|LegacyQuoteDiagnostic
     */
    public function map(FinanceQuote $legacy): array|LegacyQuoteDiagnostic
    {
        $ownerId = (int) $legacy->user_id;
        if ($ownerId < 1) {
            throw new LogicException('A legacy quote must belong to a positive owner ID.');
        }

        try {
            $currency = Money::fromMinor(0, (string) $legacy->currency)->currency();
        } catch (InvalidMoney) {
            return new LegacyQuoteDiagnostic(
                LegacyQuoteDiagnostic::UNKNOWN_CURRENCY,
                'Legacy quote currency is not a supported three-letter code.',
                ['currency' => $legacy->currency],
            );
        }

        if ($legacy->partner_id !== null && ! FinancePartner::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $ownerId)
            ->whereKey($legacy->partner_id)
            ->exists()) {
            return new LegacyQuoteDiagnostic(
                LegacyQuoteDiagnostic::FOREIGN_PARTNER,
                'Legacy quote references a partner the owner does not own.',
                ['partner_id' => $legacy->partner_id],
            );
        }

        $lines = $this->lines($legacy, $currency);
        if ($lines instanceof LegacyQuoteDiagnostic) {
            return $lines;
        }
        [$domainLines, $payloadLines, $productIds] = $lines;

        $foreignProduct = $this->foreignProduct($ownerId, $productIds);
        if ($foreignProduct instanceof LegacyQuoteDiagnostic) {
            return $foreignProduct;
        }

        $discount = $this->discount($legacy, $currency);
        if ($discount instanceof LegacyQuoteDiagnostic) {
            return $discount;
        }
        [$domainDiscount, $discountPayload] = $discount;

        try {
            $totals = $this->calculator->calculate($domainLines, $domainDiscount);
        } catch (InvalidDocument $e) {
            return new LegacyQuoteDiagnostic(
                LegacyQuoteDiagnostic::UNSUPPORTED_NUMERIC_SCALE,
                'Legacy quote lines cannot be recalculated exactly: '.$e->getMessage(),
            );
        }

        $mismatch = $this->controlMismatch($legacy, $totals, $currency);
        if ($mismatch instanceof LegacyQuoteDiagnostic) {
            return $mismatch;
        }

        $totalsPayload = [
            'net_minor' => $totals->net->minor(),
            'vat_minor' => $totals->vat->minor(),
            'gross_minor' => $totals->gross->minor(),
            'discount_minor' => $totals->discount->minor(),
            'currency' => $currency,
            'tax_breakdowns' => array_map(static fn ($breakdown): array => [
                'tax_rate_basis_points' => $breakdown->taxRateBasisPoints,
                'net_minor' => $breakdown->net->minor(),
                'vat_minor' => $breakdown->vat->minor(),
                'gross_minor' => $breakdown->gross->minor(),
            ], $totals->taxBreakdowns),
        ];

        $conversions = [];
        if ($legacy->converted_invoice_id !== null) {
            $conversions[] = [
                'target_type' => 'invoice',
                'target_reference' => 'legacy-invoice:'.$legacy->converted_invoice_id,
                'target_id' => null,
                'resolved' => false,
            ];
        }
        if ($legacy->converted_project_id !== null) {
            $conversions[] = [
                'target_type' => 'project',
                'target_reference' => 'legacy-project:'.$legacy->converted_project_id,
                'target_id' => null,
                'resolved' => false,
            ];
        }

        $result = [
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $legacy->id,
            'owner_id' => $ownerId,
            'status' => (string) $legacy->status,
            'kind' => $legacy->number === null ? 'draft' : 'published',
            'deleted_at' => $legacy->deleted_at?->format(DATE_ATOM),
            'accepted_at' => $legacy->accepted_at?->format(DATE_ATOM),
            'declined_at' => $legacy->declined_at?->format(DATE_ATOM),
            'conversions' => $conversions,
        ];

        if ($legacy->number === null) {
            $result['draft'] = [
                'payload' => [
                    'title' => (string) $legacy->title,
                    'customer' => is_array($legacy->customer) ? $legacy->customer : [],
                    'partner_id' => $legacy->partner_id,
                    'issue_date' => $legacy->issue_date?->format('Y-m-d'),
                    'valid_until' => $legacy->valid_until?->format('Y-m-d'),
                    'currency' => $currency,
                    'lines' => $payloadLines,
                    'discount' => $discountPayload,
                    'totals' => $totalsPayload,
                    'intro_text' => $legacy->intro_text,
                    'outro_text' => $legacy->outro_text,
                    'internal_note' => $legacy->note,
                ],
                'net_minor' => $totals->net->minor(),
                'vat_minor' => $totals->vat->minor(),
                'gross_minor' => $totals->gross->minor(),
                'currency' => $currency,
            ];

            return $result;
        }

        if ($legacy->year === null || $legacy->seq === null || $legacy->year < 1 || $legacy->seq < 1) {
            throw new LogicException('A numbered legacy quote must carry a positive year and sequence.');
        }

        $pdf = $this->pdf($legacy);
        if ($pdf instanceof LegacyQuoteDiagnostic) {
            return $pdf;
        }

        $result['revision'] = [
            'number' => (string) $legacy->number,
            'sequence_year' => (int) $legacy->year,
            'sequence_number' => (int) $legacy->seq,
            'snapshot' => [
                'schema_version' => 1,
                'document_type' => 'quote',
                'series_uuid' => null,
                'document_number' => (string) $legacy->number,
                'revision_number' => 1,
                'revision_label' => (string) $legacy->number,
                'title' => (string) $legacy->title,
                'customer' => is_array($legacy->customer) ? $legacy->customer : [],
                'partner_id' => $legacy->partner_id,
                'issue_date' => $legacy->issue_date?->format('Y-m-d'),
                'valid_until' => $legacy->valid_until?->format('Y-m-d'),
                'currency' => $currency,
                'lines' => $payloadLines,
                'discount' => $discountPayload,
                'totals' => $totalsPayload,
                'intro_text' => $legacy->intro_text,
                'outro_text' => $legacy->outro_text,
                'customer_note' => $legacy->note,
            ],
            'net_minor' => $totals->net->minor(),
            'vat_minor' => $totals->vat->minor(),
            'gross_minor' => $totals->gross->minor(),
            'currency' => $currency,
            'pdf_path' => (string) $legacy->pdf_path,
            'pdf_sha256' => $pdf,
            'published_at' => ($legacy->sent_at ?? $legacy->created_at)?->format(DATE_ATOM),
            'created_at' => $legacy->created_at?->format(DATE_ATOM),
        ];

        return $result;
    }

    /**
     * @return array{0: list<DocumentLine>, 1: list<array<string, int|string|null>>, 2: list<int>}|LegacyQuoteDiagnostic
     */
    private function lines(FinanceQuote $legacy, string $currency): array|LegacyQuoteDiagnostic
    {
        $rawLines = $legacy->lines;
        if (! is_array($rawLines) || $rawLines === []) {
            throw new LogicException('A legacy quote must contain at least one line.');
        }

        $domainLines = [];
        $payloadLines = [];
        $productIds = [];

        foreach ($rawLines as $line) {
            if (! is_array($line)) {
                throw new LogicException('A legacy quote line must be an array.');
            }

            $description = is_string($line['desc'] ?? null) ? $line['desc'] : '';
            $quantity = is_string($line['qty'] ?? null) ? $line['qty'] : '';
            $unit = is_string($line['unit'] ?? null) ? $line['unit'] : '';
            $unitPrice = is_string($line['unitPrice'] ?? null) ? $line['unitPrice'] : '';
            $taxRate = is_string($line['vatRate'] ?? null) ? $line['vatRate'] : '';
            $kindRaw = $line['kind'] ?? null;
            $kind = is_string($kindRaw) && in_array($kindRaw, ['service', 'hardware'], true) ? $kindRaw : 'service';
            $productIdRaw = $line['productId'] ?? null;
            $productId = is_int($productIdRaw) && $productIdRaw > 0 ? $productIdRaw : null;

            try {
                $quantityScaled = DecimalQuantity::fromString($quantity);
                $price = Money::fromDecimal($unitPrice, $currency);
                $taxRateBasisPoints = Money::fromDecimal($taxRate, 'BPS')->minor();
            } catch (InvalidQuantity|InvalidMoney) {
                return new LegacyQuoteDiagnostic(
                    LegacyQuoteDiagnostic::UNSUPPORTED_NUMERIC_SCALE,
                    'A legacy quote line has a quantity, price, or tax rate outside the supported decimal scale.',
                    ['description' => $description],
                );
            }

            if ($taxRateBasisPoints < 0 || $taxRateBasisPoints > 10_000) {
                return new LegacyQuoteDiagnostic(
                    LegacyQuoteDiagnostic::UNSUPPORTED_NUMERIC_SCALE,
                    'A legacy quote line tax rate is outside 0.00-100.00 percent.',
                    ['description' => $description],
                );
            }

            $domainLines[] = new DocumentLine($description, $quantityScaled, $price, $taxRateBasisPoints);
            $payloadLines[] = [
                'description' => $description,
                'quantity' => $quantity,
                'quantity_scaled' => $quantityScaled->scaled(),
                'unit' => $unit,
                'unit_price' => $unitPrice,
                'unit_price_minor' => $price->minor(),
                'currency' => $currency,
                'tax_rate' => $taxRate,
                'tax_rate_basis_points' => $taxRateBasisPoints,
                'kind' => $kind,
                'product_id' => $productId,
            ];

            if ($productId !== null) {
                $productIds[] = $productId;
            }
        }

        return [$domainLines, $payloadLines, array_values(array_unique($productIds))];
    }

    /** @param  list<int>  $productIds */
    private function foreignProduct(int $ownerId, array $productIds): ?LegacyQuoteDiagnostic
    {
        if ($productIds === []) {
            return null;
        }

        $found = FinanceProduct::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $ownerId)
            ->whereKey($productIds)
            ->count();

        if ($found !== count($productIds)) {
            return new LegacyQuoteDiagnostic(
                LegacyQuoteDiagnostic::FOREIGN_PRODUCT,
                'Legacy quote line references a product the owner does not own.',
                ['product_ids' => $productIds],
            );
        }

        return null;
    }

    /**
     * @return array{0: Discount, 1: array{type: string, value: string|null, currency: string, basis_points?: int, minor?: int}}|LegacyQuoteDiagnostic
     */
    private function discount(FinanceQuote $legacy, string $currency): array|LegacyQuoteDiagnostic
    {
        $type = $legacy->discount_type;
        $value = $legacy->discount_value;

        try {
            $domain = match ($type) {
                null => Discount::none($currency),
                'percent' => Discount::percentBasisPoints(
                    Money::fromDecimal((string) $value, 'BPS')->minor(),
                    $currency,
                ),
                'amount' => Discount::fixed(Money::fromDecimal((string) $value, $currency)),
                default => throw new LogicException('Legacy quote discount type is unsupported.'),
            };
        } catch (InvalidMoney) {
            return new LegacyQuoteDiagnostic(
                LegacyQuoteDiagnostic::UNSUPPORTED_NUMERIC_SCALE,
                'Legacy quote discount value is outside the supported decimal scale.',
                ['discount_type' => $type, 'discount_value' => $value],
            );
        }

        $payload = [
            'type' => $type === 'amount' ? 'fixed' : ($type ?? 'none'),
            'value' => $type === null ? null : (string) $value,
            'currency' => $currency,
        ];

        if ($domain->isPercent()) {
            $payload['basis_points'] = $domain->basisPoints();
        }
        if ($type === 'amount') {
            $payload['minor'] = $domain->fixedMinor();
        }

        return [$domain, $payload];
    }

    private function controlMismatch(FinanceQuote $legacy, DocumentTotals $totals, string $currency): ?LegacyQuoteDiagnostic
    {
        try {
            $net = Money::fromDecimal((string) $legacy->net, $currency);
            $vat = Money::fromDecimal((string) $legacy->vat, $currency);
            $gross = Money::fromDecimal((string) $legacy->gross, $currency);
        } catch (InvalidMoney) {
            return new LegacyQuoteDiagnostic(
                LegacyQuoteDiagnostic::UNSUPPORTED_NUMERIC_SCALE,
                'Legacy quote stored totals are outside the supported decimal scale.',
            );
        }

        if (! $totals->matchesControlTotals($net, $vat, $gross)) {
            return new LegacyQuoteDiagnostic(
                LegacyQuoteDiagnostic::SERVER_TOTAL_MISMATCH,
                'Recalculated totals do not match the legacy quote\'s stored totals.',
                [
                    'stored' => ['net' => (string) $legacy->net, 'vat' => (string) $legacy->vat, 'gross' => (string) $legacy->gross],
                    'calculated' => [
                        'net_minor' => $totals->net->minor(),
                        'vat_minor' => $totals->vat->minor(),
                        'gross_minor' => $totals->gross->minor(),
                    ],
                ],
            );
        }

        return null;
    }

    private function pdf(FinanceQuote $legacy): string|LegacyQuoteDiagnostic
    {
        $path = $legacy->pdf_path;
        if (! is_string($path)
            || ! str_starts_with($path, 'invoices/')
            || str_contains($path, '..')
            || str_contains($path, "\0")) {
            return new LegacyQuoteDiagnostic(
                LegacyQuoteDiagnostic::INVALID_PDF_PATH,
                'Legacy quote PDF path is missing or unsafe.',
                ['pdf_path' => $path],
            );
        }

        $disk = BlobStore::disk();

        if (! $disk->exists($path)) {
            return new LegacyQuoteDiagnostic(
                LegacyQuoteDiagnostic::MISSING_PDF,
                'Legacy quote PDF file is missing from storage.',
                ['pdf_path' => $path],
            );
        }

        $bytes = $disk->get($path);

        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-')) {
            return new LegacyQuoteDiagnostic(
                LegacyQuoteDiagnostic::INVALID_PDF_MIME,
                'Legacy quote PDF bytes are not a PDF.',
                ['pdf_path' => $path],
            );
        }

        return hash('sha256', $bytes);
    }
}
