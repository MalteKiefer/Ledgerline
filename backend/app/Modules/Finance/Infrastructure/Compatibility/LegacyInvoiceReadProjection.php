<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\Invoice;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Read-only projection of a finance-v2 invoice back into the legacy
 * snapshot shape (id, number, status, customer, net/vat/gross, dates) that
 * still-legacy Home/report screens expect, computed from the new integer
 * minor-unit source of truth. Marked for deletion by the global frontend
 * cutover; this class never writes anything.
 *
 * Task 16 only introduces this projection; wiring it into Home/report
 * controllers so they stop reading the legacy `invoices` table directly is
 * Task 17's responsibility (it happens together with removing the legacy
 * writers, per the plan's cutover dependency list).
 */
final readonly class LegacyInvoiceReadProjection
{
    /** @return list<array<string, mixed>> */
    public function forOwner(int $ownerId): array
    {
        $rows = DB::table('finance_invoices as i')
            ->join('finance_document_revisions as r', function (JoinClause $join): void {
                $join->on('r.id', '=', 'i.current_revision_id')
                    ->on('r.document_series_id', '=', 'i.document_series_id')
                    ->on('r.user_id', '=', 'i.user_id');
            })
            ->where('i.user_id', $ownerId)
            ->orderByDesc('i.id')
            ->get([
                'i.id', 'i.uuid', 'i.kind', 'i.number', 'i.year', 'i.workflow_status',
                'i.issue_date', 'i.due_date', 'i.allocated_minor', 'i.open_minor',
                'i.cancels_invoice_id', 'i.source_type', 'i.source_key', 'i.partner_id',
                'i.version', 'i.created_at',
                'r.snapshot', 'r.net_minor', 'r.vat_minor', 'r.gross_minor', 'r.currency',
            ]);

        $paidAt = $this->paidAtByInvoiceId($ownerId);

        /** @var list<array<string, mixed>> $projected */
        $projected = $rows->map(fn (object $row): array => $this->projectRow($row, $paidAt))->all();

        return $projected;
    }

    /**
     * The same rows as {@see forOwner()}, materialized as non-persisted
     * `Invoice` model instances so `FinanceReports`' existing per-line
     * totals/discount/VAT-rate computation (already proven correct for
     * genuine legacy rows) runs unmodified against finance-v2 data too —
     * one computation, two sources, instead of a second parallel one that
     * could quietly drift from it.
     *
     * Numeric ids are negated (finance-v2 id 7 becomes -7) so a finance-v2
     * invoice can never collide with a genuine legacy `invoices.id` once
     * both are merged into one report collection; `uuid` is always present
     * for any consumer that needs a real, stable, positive-space identity
     * (routing to the finance-v2 invoice page).
     *
     * @return Collection<int, Invoice>
     */
    public function asInvoiceModels(int $ownerId): Collection
    {
        return collect($this->forOwner($ownerId))->map(function (array $row): Invoice {
            $id = $row['id'] ?? null;
            if (! is_int($id)) {
                throw new LogicException('Projected invoice row is missing its id.');
            }
            $invoice = new Invoice;
            $invoice->forceFill([
                'id' => -1 * $id,
                'uuid' => $row['uuid'],
                'number' => $row['number'],
                'year' => $row['year'],
                'status' => $row['status'],
                'type' => $row['type'],
                'issue_date' => $row['issue_date'],
                'due_date' => $row['due_date'],
                'currency' => $row['currency'],
                'vat_rate' => null,
                'gross' => $row['gross'],
                'net' => $row['net'],
                'vat' => $row['vat'],
                // Always false regardless of provenance: every finance-v2 row
                // carries full line data, so invoiceTotals() must always take
                // the per-line path, never the single-flat-rate "imported"
                // shortcut meant for a legacy row that only has one gross figure.
                'imported' => false,
                'paid_at' => $row['paid_at'],
                'payment_account' => null,
                'partner_id' => $row['partner_id'],
                'customer' => $row['customer'],
                'lines' => $row['lines'],
                'note' => null,
                'discount_type' => $row['discount_type'],
                'discount_value' => $row['discount_value'],
                'skonto_percent' => null,
                'skonto_days' => null,
                'pdf_path' => null,
                'cancels_invoice_id' => $row['cancels_invoice_id'],
                'version' => $row['version'],
                'created_at' => $row['created_at'],
            ]);
            $invoice->exists = false;

            return $invoice;
        });
    }

    /**
     * Latest payment receipt date for every invoice of this owner — the
     * closest finance-v2 analogue of the legacy `invoices.paid_at`
     * timestamp, which finance-v2 has no single-column equivalent for
     * (payments are their own aggregate, potentially several per invoice).
     *
     * @return array<int, string>
     */
    private function paidAtByInvoiceId(int $ownerId): array
    {
        $rows = DB::table('finance_payment_allocations as a')
            ->join('finance_payments as p', 'p.id', '=', 'a.payment_id')
            ->where('a.user_id', $ownerId)
            ->whereNull('a.reverses_allocation_id')
            ->groupBy('a.invoice_id')
            ->get(['a.invoice_id', DB::raw('MAX(p.received_at) as received_at')]);

        $map = [];
        foreach ($rows as $row) {
            $invoiceId = $row->invoice_id ?? null;
            $receivedAt = $row->received_at ?? null;
            if (is_numeric($invoiceId) && is_string($receivedAt)) {
                $map[(int) $invoiceId] = $receivedAt;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $paidAt
     * @return array<string, mixed>
     */
    private function projectRow(object $row, array $paidAt): array
    {
        $snapshotJson = $row->snapshot ?? null;
        $snapshot = is_string($snapshotJson) ? json_decode($snapshotJson, true) : null;
        $customer = is_array($snapshot) ? ($snapshot['customer'] ?? null) : null;
        $rawLines = is_array($snapshot) && is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [];
        $discount = is_array($snapshot) && is_array($snapshot['discount'] ?? null) ? $snapshot['discount'] : [];

        $openMinor = $this->intValue($row, 'open_minor');
        $allocatedMinor = $this->intValue($row, 'allocated_minor');
        $id = $this->intValue($row, 'id');
        [$discountType, $discountValue] = $this->legacyDiscount($discount);

        return [
            'id' => $id,
            'uuid' => $this->stringValue($row, 'uuid'),
            'kind' => $this->stringValue($row, 'kind'),
            'type' => $this->stringValue($row, 'kind'),
            'number' => $this->nullableStringValue($row, 'number'),
            'year' => $this->nullableIntValue($row, 'year'),
            'status' => $this->legacyStatus($this->stringValue($row, 'workflow_status'), $openMinor, $allocatedMinor),
            'issue_date' => $this->nullableStringValue($row, 'issue_date'),
            'due_date' => $this->nullableStringValue($row, 'due_date'),
            'currency' => $this->stringValue($row, 'currency'),
            'net' => $this->decimal($this->intValue($row, 'net_minor')),
            'vat' => $this->decimal($this->intValue($row, 'vat_minor')),
            'gross' => $this->decimal($this->intValue($row, 'gross_minor')),
            'open' => $this->decimal($openMinor),
            'customer' => is_array($customer) ? $customer : [],
            'partner_id' => $this->nullableIntValue($row, 'partner_id'),
            'lines' => $this->legacyLines($rawLines),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'paid_at' => $paidAt[$id] ?? null,
            'cancels_invoice_id' => $this->nullableIntValue($row, 'cancels_invoice_id'),
            'imported' => $this->nullableStringValue($row, 'source_type') === 'legacy_invoice',
            'legacy_source_key' => $this->nullableStringValue($row, 'source_key'),
            'version' => $this->intValue($row, 'version'),
            'created_at' => $this->nullableStringValue($row, 'created_at'),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $rawLines
     * @return list<array{desc: string, qty: string, unit: string, unitPrice: string, vatRate: string, kind: string|null, productId: int|null}>
     */
    private function legacyLines(array $rawLines): array
    {
        $lines = [];
        foreach ($rawLines as $rawLine) {
            if (! is_array($rawLine)) {
                continue;
            }
            $unitPriceMinor = $rawLine['unit_price_minor'] ?? null;
            $taxRateBasisPoints = $rawLine['tax_rate_basis_points'] ?? null;
            $productId = $rawLine['product_id'] ?? null;
            $lines[] = [
                'desc' => is_string($rawLine['description'] ?? null) ? $rawLine['description'] : '',
                'qty' => is_string($rawLine['quantity'] ?? null) ? $rawLine['quantity'] : '0.0000',
                'unit' => is_string($rawLine['unit'] ?? null) ? $rawLine['unit'] : '',
                'unitPrice' => is_int($unitPriceMinor) ? $this->decimal($unitPriceMinor) : '0.00',
                'vatRate' => is_int($taxRateBasisPoints) ? $this->decimal($taxRateBasisPoints) : '0.00',
                'kind' => is_string($rawLine['kind'] ?? null) ? $rawLine['kind'] : null,
                'productId' => is_int($productId) ? $productId : null,
            ];
        }

        return $lines;
    }

    /**
     * @param  array<array-key, mixed>  $discount
     * @return array{string|null, string|null}
     */
    private function legacyDiscount(array $discount): array
    {
        $basisPoints = $discount['basis_points'] ?? null;
        $fixedMinor = $discount['fixed_minor'] ?? null;
        if (is_int($basisPoints) && $basisPoints > 0) {
            return ['percent', $this->decimal($basisPoints)];
        }
        if (is_int($fixedMinor) && $fixedMinor > 0) {
            return ['amount', $this->decimal($fixedMinor)];
        }

        return [null, null];
    }

    /**
     * Mirrors the authoritative status derivation in
     * `App\Modules\Finance\Domain\Invoices\InvoiceBalance::effectiveStatus()`:
     * a full allocation makes an invoice "paid" as soon as its open balance
     * hits zero, regardless of whether a delivery was ever sent — payment
     * and delivery are independent concerns in finance-v2, unlike the
     * legacy model where "paid" only ever followed "sent".
     */
    private function legacyStatus(string $workflowStatus, int $openMinor, int $allocatedMinor): string
    {
        if ($workflowStatus === 'draft') {
            return 'draft';
        }
        if ($allocatedMinor !== 0 && $openMinor === 0) {
            return 'paid';
        }

        return $workflowStatus === 'sent' ? 'sent' : 'final';
    }

    private function decimal(int $minor): string
    {
        $negative = $minor < 0;
        $digits = str_pad(ltrim((string) abs($minor), '-'), 3, '0', STR_PAD_LEFT);
        $decimal = substr($digits, 0, -2).'.'.substr($digits, -2);

        return $negative ? '-'.$decimal : $decimal;
    }

    private function intValue(object $row, string $key): int
    {
        $value = $row->{$key} ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new LogicException("Expected {$key} to be numeric.");
    }

    private function nullableIntValue(object $row, string $key): ?int
    {
        $value = $row->{$key} ?? null;

        return $value === null ? null : $this->intValue($row, $key);
    }

    private function stringValue(object $row, string $key): string
    {
        $value = $row->{$key} ?? null;
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        throw new LogicException("Expected {$key} to be string-like.");
    }

    private function nullableStringValue(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;

        return $value === null ? null : $this->stringValue($row, $key);
    }
}
