<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\BankTransaction;
use App\Models\FinanceProject;
use App\Models\Invoice;
use App\Models\UserSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

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
     * Whether the current user invoices as a §19 Kleinunternehmer (no VAT shown).
     * When true, output VAT is zero everywhere (turnover is recorded gross).
     */
    public function smallBusiness(): bool
    {
        $id = Auth::id();

        return is_int($id) && UserSetting::for($id)->small_business;
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

        // Accumulate the raw net per VAT rate, then apply a global invoice-level
        // discount proportionally across the rate buckets (net taxable base). This
        // MUST stay cent-identical to finance-stats.js invoiceTotals().
        $grossNet = 0.0;
        $rawByRate = [];
        $lines = is_array($inv->lines) ? $inv->lines : [];
        foreach ($lines as $l) {
            if (! is_array($l)) {
                continue;
            }
            $qty = is_numeric($l['qty'] ?? null) ? (float) $l['qty'] : 0.0;
            $unit = is_numeric($l['unitPrice'] ?? null) ? (float) $l['unitPrice'] : 0.0;
            $rv = is_numeric($l['vatRate'] ?? null) ? (float) $l['vatRate'] : 0.0;
            $lineNet = $qty * $unit;
            $grossNet += $lineNet;
            $r = (string) $rv;
            $rawByRate[$r] = ($rawByRate[$r] ?? 0.0) + $lineNet;
        }
        $discount = $this->discountAmount($inv, $grossNet);
        $factor = $grossNet != 0.0 ? ($grossNet - $discount) / $grossNet : 1.0;
        $vatByRate = [];
        foreach ($rawByRate as $r => $rawNet) {
            $netR = $rawNet * $factor;
            $vatByRate[$r] = $netR * (float) $r / 100;
        }
        $net = $grossNet - $discount;
        $vat = array_sum($vatByRate);

        return ['net' => $this->r2($net), 'vat' => $this->r2($vat), 'gross' => $this->r2($net + $vat), 'vatByRate' => $vatByRate];
    }

    /**
     * The signed global-discount amount on the net taxable base. Positive on a
     * normal invoice (reduces net); on a credit note the base is negative, so the
     * discount is negated to keep the credit an exact reverse of the original.
     * Cent-identical to finance-stats.js discountAmount().
     */
    private function discountAmount(Invoice $inv, float $grossNet): float
    {
        $type = is_string($inv->discount_type) ? $inv->discount_type : null;
        $val = is_numeric($inv->discount_value) ? (float) $inv->discount_value : 0.0;
        if ($type === null || $val <= 0.0 || $grossNet == 0.0) {
            return 0.0;
        }
        $d = $type === 'percent' ? $grossNet * $val / 100 : ($grossNet < 0 ? -$val : $val);
        // Never exceed the base in magnitude, never flip the base's sign.
        if (abs($d) > abs($grossNet)) {
            $d = $grossNet;
        }

        return $d;
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
        // A §19 Kleinunternehmer shows no VAT: report turnover (net = gross) only.
        $small = $this->smallBusiness();
        $list = $this->realizedInvoices()->filter(fn (Invoice $i): bool => $this->yearOf($i) === $year);
        $qNet = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        $qVat = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        $byRateNet = [];
        $byRateVat = [];
        $net = 0.0;
        $vat = 0.0;
        foreach ($list as $inv) {
            $t = $this->invoiceTotals($inv);
            // KU: turnover is the gross amount, no VAT — everything falls in the 0% bucket.
            $rowNet = $small ? $t['gross'] : $t['net'];
            $net += $rowNet;
            $vat += $small ? 0.0 : $t['vat'];
            if ($small) {
                $byRateVat['0'] = $byRateVat['0'] ?? 0.0;
                $byRateNet['0'] = ($byRateNet['0'] ?? 0.0) + $rowNet;
            } else {
                foreach ($t['vatByRate'] as $r => $v) {
                    $rate = (float) $r;
                    $key = (string) $rate;
                    $byRateVat[$key] = ($byRateVat[$key] ?? 0.0) + (float) $v;
                    $byRateNet[$key] = ($byRateNet[$key] ?? 0.0) + ($rate > 0 ? (float) $v / ($rate / 100) : $t['net']);
                }
            }
            $q = (int) ceil($this->monthOf($inv) / 3);
            if ($q >= 1 && $q <= 4) {
                $qNet[$q] += $rowNet;
                $qVat[$q] += $small ? 0.0 : $t['vat'];
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
     * Aging of OPEN invoices (issued but unpaid, i.e. status='sent', not trashed),
     * bucketed by how many days each is past its due date vs today: current (not
     * yet due / no due date), 1-30, 31-60, 60+. Each bucket carries its count,
     * gross sum, and the invoice list (id/number/customer/gross/due_date/days).
     * Owner-scoped + cent-exact via {@see invoiceTotals()}. Read-only.
     *
     * @return array<string, mixed>
     */
    public function aging(): array
    {
        $today = Carbon::today();
        $keys = ['current', '1_30', '31_60', '60_plus'];
        $buckets = [];
        foreach ($keys as $k) {
            $buckets[$k] = ['count' => 0, 'gross' => 0.0, 'invoices' => []];
        }
        $openCount = 0;
        $openGross = 0.0;

        foreach (Invoice::query()->where('status', 'sent')->get() as $inv) {
            $gross = $this->invoiceTotals($inv)['gross'];
            $due = $inv->due_date;
            if (! $due instanceof Carbon) {
                $key = 'current';
                $days = 0;
            } else {
                $days = $due->lt($today) ? (int) $due->diffInDays($today) : 0;
                $key = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : '60_plus'));
            }
            $customer = is_array($inv->customer) ? $inv->customer : [];
            $name = is_string($customer['name'] ?? null) && $customer['name'] !== '' ? $customer['name'] : '—';

            $buckets[$key]['count']++;
            $buckets[$key]['gross'] = $this->r2($buckets[$key]['gross'] + $gross);
            $buckets[$key]['invoices'][] = [
                'id' => $inv->id,
                'number' => $inv->number,
                'customer' => $name,
                'gross' => $gross,
                'due_date' => $due instanceof Carbon ? $due->format('Y-m-d') : null,
                'days_overdue' => $days,
            ];
            $openCount++;
            $openGross = $this->r2($openGross + $gross);
        }

        return ['buckets' => $buckets, 'openCount' => $openCount, 'openGross' => $openGross];
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
     * Unified USt-Voranmeldung for a year (optionally one quarter): output VAT
     * from realized invoices + input VAT (Vorsteuer) from ALL of the user's bank
     * transactions, combined into a single Zahllast (payable = output − input).
     *
     * §19 Kleinunternehmer: outputVat is zero (turnover reported as net = gross),
     * so payable = −inputVat (a KU typically has no Vorsteuer claim — the figure
     * is kept for completeness). Cent-exact; owner-scoped via the global scopes.
     *
     * @return array<string, mixed>
     */
    public function vatAdvanceReturn(int $year, ?int $quarter = null): array
    {
        $q = ($quarter !== null && $quarter >= 1 && $quarter <= 4) ? $quarter : null;
        $small = $this->smallBusiness();
        $inQuarter = fn (int $month): bool => $q === null || (int) ceil($month / 3) === $q;

        // ---- Output side: realized invoices (year + optional quarter) ----
        $outNet = 0.0;
        $outVat = 0.0;
        /** @var array<string, array{net: float, vat: float}> $outByRate */
        $outByRate = [];
        foreach ($this->realizedInvoices()->filter(fn (Invoice $i): bool => $this->yearOf($i) === $year) as $inv) {
            if (! $inQuarter($this->monthOf($inv))) {
                continue;
            }
            $t = $this->invoiceTotals($inv);
            if ($small) {
                $outNet += $t['gross']; // turnover = gross, no VAT
                $outByRate['0'] ??= ['net' => 0.0, 'vat' => 0.0];
                $outByRate['0']['net'] += $t['gross'];

                continue;
            }
            $outNet += $t['net'];
            $outVat += $t['vat'];
            foreach ($t['vatByRate'] as $r => $v) {
                $rate = (float) $r;
                $key = (string) $rate;
                $outByRate[$key] ??= ['net' => 0.0, 'vat' => 0.0];
                $outByRate[$key]['vat'] += (float) $v;
                $outByRate[$key]['net'] += $rate > 0 ? (float) $v / ($rate / 100) : $t['net'];
            }
        }

        // ---- Input side: Vorsteuer on expense bank transactions (all accounts) ----
        $inNet = 0.0;
        $inVat = 0.0;
        /** @var array<string, array{net: float, vat: float}> $inByRate */
        $inByRate = [];
        foreach ($this->expenseTransactions($year) as $tx) {
            $month = (int) substr((string) $tx->date?->format('Y-m-d'), 5, 2);
            if (! $inQuarter($month)) {
                continue;
            }
            $cat = (string) ($tx->vat_cat ?? '');
            if ($cat === '' || $cat === 'private') {
                continue; // undecided / owner private movements carry no Vorsteuer
            }
            ['net' => $net, 'vat' => $vat] = $this->grossToNetVat(abs((float) $tx->amount), (float) $cat);
            $inNet += $net;
            $inVat += $vat;
            $inByRate[$cat] ??= ['net' => 0.0, 'vat' => 0.0];
            $inByRate[$cat]['net'] += $net;
            $inByRate[$cat]['vat'] += $vat;
        }

        $outVatR = $this->r2($outVat);
        $inVatR = $this->r2($inVat);

        $rateKeys = array_unique([...array_keys($outByRate), ...array_keys($inByRate)]);
        $byRate = [];
        foreach ($rateKeys as $key) {
            $byRate[] = [
                'rate' => (float) $key,
                'outputNet' => $this->r2($outByRate[$key]['net'] ?? 0.0),
                'outputVat' => $this->r2($outByRate[$key]['vat'] ?? 0.0),
                'inputNet' => $this->r2($inByRate[$key]['net'] ?? 0.0),
                'inputVat' => $this->r2($inByRate[$key]['vat'] ?? 0.0),
            ];
        }
        usort($byRate, fn (array $a, array $b): int => $a['rate'] <=> $b['rate']);

        return [
            'year' => $year,
            'quarter' => $q,
            'net' => $this->r2($outNet),
            'outputVat' => $outVatR,
            'inputVat' => $inVatR,
            'payable' => $this->r2($outVatR - $inVatR),
            'byRate' => $byRate,
            'small_business' => $small,
        ];
    }

    /**
     * A simplified EÜR (Einnahmenüberschussrechnung): income − expenses = profit
     * for a year. NOT the full official Anlage-EÜR form.
     *
     * Income = realized (sent|paid) invoices by issue_date year, grouped by
     * customer. We key income on issue_date (not paid_at) because issue_date is
     * always populated whereas paid_at is not reliably set (imported invoices are
     * created "paid" without it) — this keeps EÜR income consistent with the
     * revenue/VAT reporting. Expenses ARE cash-basis: bank transactions
     * (amount < 0, excluding "private" owner movements) by their booking date,
     * plus FinanceProject manual expenses, grouped by VAT category / expense
     * category.
     *
     * Amounts: NET (ex-VAT) for a VAT-liable business; GROSS for a §19 KU.
     *
     * @return array<string, mixed>
     */
    public function euer(int $year): array
    {
        $small = $this->smallBusiness();

        // ---- Income: realized invoices by issue_date year, grouped by customer ----
        /** @var array<string, float> $incomeMap */
        $incomeMap = [];
        $incomeTotal = 0.0;
        foreach ($this->realizedInvoices()->filter(fn (Invoice $i): bool => $this->yearOf($i) === $year) as $inv) {
            $t = $this->invoiceTotals($inv);
            $amount = $small ? $t['gross'] : $t['net'];
            $customer = is_array($inv->customer) ? $inv->customer : [];
            $name = is_string($customer['name'] ?? null) && $customer['name'] !== '' ? $customer['name'] : '—';
            $incomeMap[$name] = ($incomeMap[$name] ?? 0.0) + $amount;
            $incomeTotal += $amount;
        }

        // ---- Expenses: bank transactions (amount<0) by VAT category ----
        /** @var array<string, float> $expenseMap */
        $expenseMap = [];
        $expenseTotal = 0.0;
        foreach ($this->expenseTransactions($year) as $tx) {
            $cat = (string) ($tx->vat_cat ?? '');
            if ($cat === 'private') {
                continue; // owner withdrawals are not a business expense
            }
            $gross = abs((float) $tx->amount);
            if ($small || $cat === '' || (float) $cat <= 0) {
                $amount = $gross;
            } else {
                $amount = $this->grossToNetVat($gross, (float) $cat)['net'];
            }
            $label = $cat === '' ? '—' : $cat;
            $expenseMap[$label] = ($expenseMap[$label] ?? 0.0) + $amount;
            $expenseTotal += $amount;
        }

        // ---- Plus FinanceProject manual (hand-entered) expenses for the year ----
        foreach (FinanceProject::query()->get() as $project) {
            foreach (is_array($project->expenses) ? $project->expenses : [] as $exp) {
                if (! is_array($exp)) {
                    continue;
                }
                $date = is_string($exp['date'] ?? null) ? $exp['date'] : '';
                if ((int) substr($date, 0, 4) !== $year) {
                    continue;
                }
                $amount = is_numeric($exp['amount'] ?? null) ? abs((float) $exp['amount']) : 0.0;
                if ($amount === 0.0) {
                    continue;
                }
                $label = is_string($exp['category'] ?? null) && $exp['category'] !== '' ? $exp['category'] : $project->name;
                $expenseMap[$label] = ($expenseMap[$label] ?? 0.0) + $amount;
                $expenseTotal += $amount;
            }
        }

        $incomeTotalR = $this->r2($incomeTotal);
        $expenseTotalR = $this->r2($expenseTotal);

        return [
            'year' => $year,
            'income' => [
                'total' => $incomeTotalR,
                'byCategory' => $this->categoryRows($incomeMap),
            ],
            'expenses' => [
                'total' => $expenseTotalR,
                'byCategory' => $this->categoryRows($expenseMap),
            ],
            'profit' => $this->r2($incomeTotalR - $expenseTotalR),
            'small_business' => $small,
        ];
    }

    /**
     * Expense bank transactions (amount < 0) for a year, owner-scoped.
     *
     * @return Collection<int, BankTransaction>
     */
    private function expenseTransactions(int $year): Collection
    {
        return BankTransaction::query()->where('amount', '<', 0)->get()
            ->filter(fn (BankTransaction $t): bool => (int) substr((string) $t->date?->format('Y-m-d'), 0, 4) === $year)
            ->values();
    }

    /**
     * Sort a {label => amount} map into a descending rows list.
     *
     * @param  array<string, float>  $map
     * @return list<array{name: string, amount: float}>
     */
    private function categoryRows(array $map): array
    {
        $out = [];
        foreach ($map as $name => $amount) {
            $out[] = ['name' => (string) $name, 'amount' => $this->r2($amount)];
        }
        usort($out, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

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
