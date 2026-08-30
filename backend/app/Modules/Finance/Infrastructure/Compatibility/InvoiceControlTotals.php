<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Modules\Finance\Domain\Shared\Money;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Compares exact legacy vs. migrated control totals for one owner: record
 * count, and net/vat/gross/paid/open minor sums, plus the set of numbered
 * invoice numbers. A mismatch in any of these means the migration is not
 * ready to cut over for that owner — `finance:check-invoice-cutover` exits
 * non-zero rather than silently accepting a discrepancy.
 */
final readonly class InvoiceControlTotals
{
    /** @return array{ok: bool, mismatches: list<string>, legacy: array<string, mixed>, migrated: array<string, mixed>} */
    public function compare(int $ownerId): array
    {
        $legacy = $this->legacyTotals($ownerId);
        $migrated = $this->migratedTotals($ownerId);

        $mismatches = [];
        foreach (['record_count', 'net_minor', 'vat_minor', 'gross_minor'] as $key) {
            if ($legacy[$key] !== $migrated[$key]) {
                $mismatches[] = "{$key}: legacy={$legacy[$key]} migrated={$migrated[$key]}";
            }
        }
        $missingNumbers = array_values(array_diff($legacy['numbers'], $migrated['numbers']));
        $extraNumbers = array_values(array_diff($migrated['numbers'], $legacy['numbers']));
        if ($missingNumbers !== []) {
            $mismatches[] = 'numbers missing from migrated set: '.implode(', ', array_slice($missingNumbers, 0, 20));
        }
        if ($extraNumbers !== []) {
            $mismatches[] = 'numbers present in migrated set but not legacy: '.implode(', ', array_slice($extraNumbers, 0, 20));
        }

        return [
            'ok' => $mismatches === [],
            'mismatches' => $mismatches,
            'legacy' => $legacy,
            'migrated' => $migrated,
        ];
    }

    /** @return array{record_count:int, net_minor:int, vat_minor:int, gross_minor:int, numbers: list<string>} */
    private function legacyTotals(int $ownerId): array
    {
        // A raw query-builder call carries no Eloquent soft-delete scope, so
        // this already includes soft-deleted rows — a GoBD-numbered invoice
        // must be counted even after a soft delete.
        $rows = DB::table('invoices')
            ->where('user_id', $ownerId)
            ->select('id', 'number', 'currency', 'net', 'vat', 'gross')
            ->get();

        $netMinor = 0;
        $vatMinor = 0;
        $grossMinor = 0;
        $numbers = [];
        $seenIds = [];
        foreach ($rows as $row) {
            $id = $this->intValue($row, 'id');
            if (in_array($id, $seenIds, true)) {
                continue;
            }
            $seenIds[] = $id;
            $currencyRaw = $this->nullableStringValue($row, 'currency');
            $currency = $currencyRaw !== null && preg_match('/\A[A-Z]{3}\z/D', $currencyRaw) === 1 ? $currencyRaw : 'EUR';
            $net = $this->nullableStringValue($row, 'net');
            $vat = $this->nullableStringValue($row, 'vat');
            $gross = $this->nullableStringValue($row, 'gross');
            if ($net !== null) {
                $netMinor += Money::fromDecimal($net, $currency)->minor();
            }
            if ($vat !== null) {
                $vatMinor += Money::fromDecimal($vat, $currency)->minor();
            }
            if ($gross !== null) {
                $grossMinor += Money::fromDecimal($gross, $currency)->minor();
            }
            $number = $this->nullableStringValue($row, 'number');
            if ($number !== null && trim($number) !== '') {
                $numbers[] = $number;
            }
        }
        sort($numbers);

        return [
            'record_count' => count($seenIds),
            'net_minor' => $netMinor,
            'vat_minor' => $vatMinor,
            'gross_minor' => $grossMinor,
            'numbers' => $numbers,
        ];
    }

    /** @return array{record_count:int, net_minor:int, vat_minor:int, gross_minor:int, numbers: list<string>} */
    private function migratedTotals(int $ownerId): array
    {
        $rows = DB::table('finance_invoices as i')
            ->join('finance_document_revisions as r', function (JoinClause $join): void {
                $join->on('r.id', '=', 'i.current_revision_id')
                    ->on('r.document_series_id', '=', 'i.document_series_id')
                    ->on('r.user_id', '=', 'i.user_id');
            })
            ->where('i.user_id', $ownerId)
            ->where('i.source_type', 'legacy_invoice')
            ->select('i.number', 'r.net_minor', 'r.vat_minor', 'r.gross_minor')
            ->get();

        $numbers = [];
        $netMinor = 0;
        $vatMinor = 0;
        $grossMinor = 0;
        foreach ($rows as $row) {
            $number = $this->nullableStringValue($row, 'number');
            if ($number !== null && trim($number) !== '') {
                $numbers[] = $number;
            }
            $netMinor += $this->intValue($row, 'net_minor');
            $vatMinor += $this->intValue($row, 'vat_minor');
            $grossMinor += $this->intValue($row, 'gross_minor');
        }
        sort($numbers);

        return [
            'record_count' => $rows->count(),
            'net_minor' => $netMinor,
            'vat_minor' => $vatMinor,
            'gross_minor' => $grossMinor,
            'numbers' => $numbers,
        ];
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

    private function nullableStringValue(object $row, string $key): ?string
    {
        $value = $row->{$key} ?? null;
        if ($value === null) {
            return null;
        }
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        throw new LogicException("Expected {$key} to be string-like.");
    }
}
