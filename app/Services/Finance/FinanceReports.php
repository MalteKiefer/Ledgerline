<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\BankTransaction;
use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Server-side finance analytics — the authoritative port of the client's
 * resources/js/shared/finance-stats.js (which stays as the reference + its
 * vitest spec). Every figure must match that logic cent-for-cent so switching
 * the UI to the server never shifts a VAT number.
 *
 * Read-only: computes from the existing owner-scoped rows, never mutates or
 * migrates anything. Invoice queries rely on the SoftDeletes global scope
 * (trashed rows already excluded) + the OwnsUserData owner scope.
 */
class FinanceReports
{
    /** round2 mirror of the JS `Math.round((n + EPSILON) * 100) / 100` for the currency domain. */
    private function r2(float $n): float
    {
        return round($n, 2);
    }

    private function yearOf(Invoice $inv): int
    {
        return (int) substr((string) $inv->issue_date?->format('Y-m-d'), 0, 4);
    }

    private function monthOf(Invoice $inv): int
    {
        return (int) substr((string) $inv->issue_date?->format('Y-m-d'), 5, 2);
    }

    /**
     * Net / VAT (grouped by rate) / gross for one invoice. An imported invoice
     * carries the exact printed gross — trust it and derive net/vat out of it.
     *
     * @return array{net: float, vat: float, gross: float, vatByRate: array<array-key, float>}
     */
    public function invoiceTotals(Invoice $inv): array
    {
        if ($inv->imported && is_numeric($inv->gross)) {
            $rate = (float) ($inv->vat_rate ?? 0);
            $gross = $this->r2((float) $inv->gross);
            $vat = $this->r2($gross * $rate / (100 + $rate));
            $net = $this->r2($gross - $vat);

            return ['net' => $net, 'vat' => $vat, 'gross' => $gross, 'vatByRate' => [(string) $rate => $vat]];
        }

        $net = 0.0;
        $vatByRate = [];
        $lines = is_array($inv->lines) ? $inv->lines : [];
        foreach ($lines as $l) {
            if (! is_array($l)) {
                continue;
            }
            $qty = is_numeric($l['qty'] ?? null) ? (float) $l['qty'] : 0.0;
            $unit = is_numeric($l['unitPrice'] ?? null) ? (float) $l['unitPrice'] : 0.0;
            $rv = is_numeric($l['vatRate'] ?? null) ? (float) $l['vatRate'] : 0.0;
            $lineNet = $qty * $unit;
            $net += $lineNet;
            $r = (string) $rv;
            $vatByRate[$r] = ($vatByRate[$r] ?? 0.0) + $lineNet * $rv / 100;
        }
        $vat = array_sum($vatByRate);

        return ['net' => $this->r2($net), 'vat' => $this->r2($vat), 'gross' => $this->r2($net + $vat), 'vatByRate' => $vatByRate];
    }

    /**
     * Invoices that count as revenue: issued (sent|paid), not trashed.
     *
     * @return Collection<int, Invoice>
     */
    public function realizedInvoices(): Collection
    {
        return Invoice::query()->whereIn('status', ['sent', 'paid'])->get();
    }

    /**
     * VAT advance return figures for a year: net turnover + VAT owed, broken
     * down by rate and by quarter.
     *
     * @return array<string, mixed>
     */
    public function vatReturn(int $year): array
    {
        $list = $this->realizedInvoices()->filter(fn (Invoice $i): bool => $this->yearOf($i) === $year);
        $qNet = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        $qVat = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        $byRateNet = [];
        $byRateVat = [];
        $net = 0.0;
        $vat = 0.0;
        foreach ($list as $inv) {
            $t = $this->invoiceTotals($inv);
            $net += $t['net'];
            $vat += $t['vat'];
            foreach ($t['vatByRate'] as $r => $v) {
                $rate = (float) $r;
                $key = (string) $rate;
                $byRateVat[$key] = ($byRateVat[$key] ?? 0.0) + (float) $v;
                $byRateNet[$key] = ($byRateNet[$key] ?? 0.0) + ($rate > 0 ? (float) $v / ($rate / 100) : $t['net']);
            }
            $q = (int) ceil($this->monthOf($inv) / 3);
            if ($q >= 1 && $q <= 4) {
                $qNet[$q] += $t['net'];
                $qVat[$q] += $t['vat'];
            }
        }
        $byRateOut = [];
        foreach ($byRateVat as $key => $v) {
            $byRateOut[] = ['rate' => (float) $key, 'net' => $this->r2($byRateNet[$key] ?? 0.0), 'vat' => $this->r2($v)];
        }
        usort($byRateOut, fn (array $a, array $b): int => $a['rate'] <=> $b['rate']);

        return [
            'year' => $year,
            'net' => $this->r2($net),
            'vat' => $this->r2($vat),
            'gross' => $this->r2($net + $vat),
            'count' => $list->count(),
            'byRate' => $byRateOut,
            'quarters' => array_map(fn (int $q): array => ['q' => $q, 'net' => $this->r2($qNet[$q]), 'vat' => $this->r2($qVat[$q])], [1, 2, 3, 4]),
        ];
    }

    /**
     * Net/gross revenue per customer for a year, highest net first.
     *
     * @return list<array{name: string, net: float, gross: float, count: int}>
     */
    public function revenueByCustomer(int $year): array
    {
        $map = [];
        foreach ($this->realizedInvoices()->filter(fn (Invoice $i): bool => $this->yearOf($i) === $year) as $inv) {
            $customer = is_array($inv->customer) ? $inv->customer : [];
            $name = is_string($customer['name'] ?? null) && $customer['name'] !== '' ? $customer['name'] : '—';
            $t = $this->invoiceTotals($inv);
            $map[$name] ??= ['name' => $name, 'net' => 0.0, 'gross' => 0.0, 'count' => 0];
            $map[$name]['net'] += $t['net'];
            $map[$name]['gross'] += $t['gross'];
            $map[$name]['count']++;
        }
        $out = array_values(array_map(fn (array $c): array => [
            'name' => $c['name'], 'net' => $this->r2($c['net']), 'gross' => $this->r2($c['gross']), 'count' => $c['count'],
        ], $map));
        usort($out, fn (array $a, array $b): int => $b['net'] <=> $a['net']);

        return $out;
    }

    /**
     * Net revenue per calendar month (month 1..12) for a year.
     *
     * @return list<array{month: int, net: float}>
     */
    public function monthlyRevenue(int $year): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = 0.0;
        }
        foreach ($this->realizedInvoices()->filter(fn (Invoice $i): bool => $this->yearOf($i) === $year) as $inv) {
            $m = $this->monthOf($inv);
            if ($m >= 1 && $m <= 12) {
                $months[$m] += $this->invoiceTotals($inv)['net'];
            }
        }
        $out = [];
        foreach ($months as $m => $net) {
            $out[] = ['month' => $m, 'net' => $this->r2($net)];
        }

        return $out;
    }

    /**
     * Headline KPIs for a year incl. year-over-year net growth.
     *
     * @return array<string, mixed>
     */
    public function yearKpis(int $year): array
    {
        $realized = $this->realizedInvoices();
        $list = $realized->filter(fn (Invoice $i): bool => $this->yearOf($i) === $year);
        $net = $this->r2($list->reduce(fn (float $s, Invoice $i): float => $s + $this->invoiceTotals($i)['net'], 0.0));
        $prevNet = $this->r2($realized->filter(fn (Invoice $i): bool => $this->yearOf($i) === $year - 1)
            ->reduce(fn (float $s, Invoice $i): float => $s + $this->invoiceTotals($i)['net'], 0.0));
        $customers = $list->map(function (Invoice $i): string {
            $c = is_array($i->customer) ? $i->customer : [];

            return is_string($c['name'] ?? null) && $c['name'] !== '' ? $c['name'] : '—';
        })->unique()->count();

        return [
            'year' => $year,
            'net' => $net,
            'count' => $list->count(),
            'avg' => $list->count() ? $this->r2($net / $list->count()) : 0.0,
            'customers' => $customers,
            'prevNet' => $prevNet,
            'growthPct' => $prevNet > 0 ? $this->r2(($net - $prevNet) / $prevNet * 100) : null,
        ];
    }

    /**
     * Split a gross amount into net + VAT for a given rate (percent).
     *
     * @return array{net: float, vat: float}
     */
    public function grossToNetVat(float $gross, float $ratePercent): array
    {
        $net = $ratePercent > 0 ? $gross / (1 + $ratePercent / 100) : $gross;

        return ['net' => $this->r2($net), 'vat' => $this->r2($gross - $net)];
    }

    /**
     * VAT summary of a transaction collection by category (for the USt calc).
     *
     * @param  Collection<int, BankTransaction>  $transactions
     * @return array<string, mixed>
     */
    public function accountVatSummary(Collection $transactions): array
    {
        $income = [];
        $expense = [];
        $privateSum = 0.0;
        $undecided = 0;
        $outputVat = 0.0;
        $inputVat = 0.0;
        foreach ($transactions as $tx) {
            $cat = (string) ($tx->vat_cat ?? '');
            $amount = (float) ($tx->amount ?? 0);
            $gross = abs($amount);
            if ($cat === 'private') {
                $privateSum += $gross;

                continue;
            }
            if ($cat === '') {
                $undecided++;

                continue;
            }
            ['net' => $net, 'vat' => $vat] = $this->grossToNetVat($gross, (float) $cat);
            $isIncome = $amount >= 0;
            $bucket = $isIncome ? 'income' : 'expense';
            if ($bucket === 'income') {
                $income[$cat] ??= ['net' => 0.0, 'vat' => 0.0];
                $income[$cat]['net'] += $net;
                $income[$cat]['vat'] += $vat;
                $outputVat += $vat;
            } else {
                $expense[$cat] ??= ['net' => 0.0, 'vat' => 0.0];
                $expense[$cat]['net'] += $net;
                $expense[$cat]['vat'] += $vat;
                $inputVat += $vat;
            }
        }

        return [
            'income' => $this->vatRows($income),
            'expense' => $this->vatRows($expense),
            'outputVat' => $this->r2($outputVat),
            'inputVat' => $this->r2($inputVat),
            'payable' => $this->r2($outputVat - $inputVat),
            'privateSum' => $this->r2($privateSum),
            'undecided' => $undecided,
        ];
    }

    /**
     * @param  array<array-key, array{net: float, vat: float}>  $map
     * @return list<array{rate: string, net: float, vat: float}>
     */
    private function vatRows(array $map): array
    {
        $out = [];
        foreach ($map as $rate => $v) {
            $out[] = ['rate' => (string) $rate, 'net' => $this->r2($v['net']), 'vat' => $this->r2($v['vat'])];
        }
        usort($out, fn (array $a, array $b): int => (float) $b['rate'] <=> (float) $a['rate']);

        return $out;
    }

    /**
     * Distinct years with realized invoices, most recent first.
     *
     * @return list<int>
     */
    public function activeYears(): array
    {
        $years = $this->realizedInvoices()
            ->map(fn (Invoice $i): int => $this->yearOf($i))
            ->filter(fn (int $y): bool => $y > 0)
            ->unique()
            ->values()
            ->all();
        rsort($years);

        return $years;
    }
}
