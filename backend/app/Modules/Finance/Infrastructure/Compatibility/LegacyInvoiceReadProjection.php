<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use Illuminate\Database\Query\JoinClause;
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
                'i.cancels_invoice_id', 'i.source_type', 'i.source_key',
                'r.snapshot', 'r.net_minor', 'r.vat_minor', 'r.gross_minor', 'r.currency',
            ]);

        /** @var list<array<string, mixed>> $projected */
        $projected = $rows->map(fn (object $row): array => $this->projectRow($row))->all();

        return $projected;
    }

    /** @return array<string, mixed> */
    private function projectRow(object $row): array
    {
        $snapshotJson = $row->snapshot ?? null;
        $snapshot = is_string($snapshotJson) ? json_decode($snapshotJson, true) : null;
        $customer = is_array($snapshot) ? ($snapshot['customer'] ?? null) : null;

        $openMinor = $this->intValue($row, 'open_minor');
        $allocatedMinor = $this->intValue($row, 'allocated_minor');

        return [
            'id' => $this->intValue($row, 'id'),
            'uuid' => $this->stringValue($row, 'uuid'),
            'kind' => $this->stringValue($row, 'kind'),
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
            'cancels_invoice_id' => $this->nullableIntValue($row, 'cancels_invoice_id'),
            'imported' => $this->nullableStringValue($row, 'source_type') === 'legacy_invoice',
            'legacy_source_key' => $this->nullableStringValue($row, 'source_key'),
        ];
    }

    private function legacyStatus(string $workflowStatus, int $openMinor, int $allocatedMinor): string
    {
        if ($workflowStatus === 'draft') {
            return 'draft';
        }
        if ($workflowStatus === 'finalized') {
            return 'final';
        }

        // workflow_status === 'sent'
        return $allocatedMinor !== 0 && $openMinor === 0 ? 'paid' : 'sent';
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
