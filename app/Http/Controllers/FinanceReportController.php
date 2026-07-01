<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\IncomeEntry;
use App\Models\Invoice;
use App\Models\TimeEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Finance report: income vs expenses, profit, outstanding and pipeline over a
 * date range, plus per-customer profit, expenses by category and a monthly
 * breakdown. Amounts are summed in the base currency.
 */
class FinanceReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        [$from, $to] = $this->range($request);

        // Invoiced revenue: finalised invoices' net (credit notes subtract).
        $invoiceNet = (int) Invoice::query()
            ->where('status', '!=', 'DRAFT')
            ->whereBetween('issue_date', [$from, $to])
            ->sum('net_cents');

        $manualIncome = (int) IncomeEntry::query()->whereBetween('date', [$from, $to])->sum('amount_cents');

        $expenseNet = (int) Expense::query()
            ->whereBetween('date', [$from, $to])
            ->sum(DB::raw('amount_cents - tax_cents'));

        $outstanding = (int) Invoice::query()
            ->whereNotIn('status', ['DRAFT', 'CANCELLED'])
            ->whereBetween('issue_date', [$from, $to])
            ->sum(DB::raw('gross_cents - paid_cents'));

        $unbilled = (int) TimeEntry::query()
            ->where('billable', true)->where('billed', false)
            ->whereBetween('date', [$from, $to])
            ->sum(DB::raw('minutes * rate_cents / 60'));

        $income = $invoiceNet + $manualIncome;

        return view('finance.report', [
            'from' => $from,
            'to' => $to,
            'summary' => [
                'income' => $income,
                'expenses' => $expenseNet,
                'profit' => $income - $expenseNet,
                'outstanding' => max(0, $outstanding),
                'unbilled' => $unbilled,
            ],
            'perCustomer' => $this->perCustomer($from, $to),
            'byCategory' => $this->byCategory($from, $to),
            'monthly' => $this->monthly($from, $to),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $from = $request->query('from') ?: Carbon::now()->startOfYear()->toDateString();
        $to = $request->query('to') ?: Carbon::now()->endOfYear()->toDateString();

        return [(string) $from, (string) $to];
    }

    /**
     * Revenue minus linked expenses per customer (top 10 by profit).
     *
     * @return list<array{name: string, revenue: int, expenses: int, profit: int}>
     */
    private function perCustomer(string $from, string $to): array
    {
        $revenue = Invoice::query()
            ->where('status', '!=', 'DRAFT')
            ->whereNotNull('customer_id')
            ->whereBetween('issue_date', [$from, $to])
            ->groupBy('customer_id')
            ->pluck(DB::raw('SUM(net_cents)'), 'customer_id');

        $expenses = Expense::query()
            ->whereNotNull('customer_id')
            ->whereBetween('date', [$from, $to])
            ->groupBy('customer_id')
            ->pluck(DB::raw('SUM(amount_cents - tax_cents)'), 'customer_id');

        $ids = $revenue->keys()->merge($expenses->keys())->unique();
        $names = Customer::query()->whereIn('id', $ids)->pluck('name', 'id');

        return $ids
            ->map(function ($id) use ($revenue, $expenses, $names): array {
                $rev = (int) ($revenue[$id] ?? 0);
                $exp = (int) ($expenses[$id] ?? 0);

                return ['name' => $names[$id] ?? '—', 'revenue' => $rev, 'expenses' => $exp, 'profit' => $rev - $exp];
            })
            ->sortByDesc('profit')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, net: int}>
     */
    private function byCategory(string $from, string $to): array
    {
        return Expense::query()
            ->whereBetween('date', [$from, $to])
            ->groupBy('category')
            ->pluck(DB::raw('SUM(amount_cents - tax_cents)'), 'category')
            ->map(fn ($net, $category): array => [
                'label' => ExpenseCategory::tryFrom((string) $category)?->label() ?? (string) $category,
                'net' => (int) $net,
            ])
            ->sortByDesc('net')
            ->values()
            ->all();
    }

    /**
     * Income and expenses bucketed by month across the range.
     *
     * @return list<array{month: string, income: int, expenses: int}>
     */
    private function monthly(string $from, string $to): array
    {
        $buckets = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->startOfMonth();

        while ($cursor->lte($end) && count($buckets) < 24) {
            $buckets[$cursor->format('Y-m')] = ['month' => $cursor->format('Y-m'), 'income' => 0, 'expenses' => 0];
            $cursor->addMonth();
        }

        Invoice::query()->where('status', '!=', 'DRAFT')->whereBetween('issue_date', [$from, $to])
            ->get(['issue_date', 'net_cents'])
            ->each(function (Invoice $i) use (&$buckets): void {
                $key = $i->issue_date->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['income'] += $i->net_cents;
                }
            });

        IncomeEntry::query()->whereBetween('date', [$from, $to])
            ->get(['date', 'amount_cents'])
            ->each(function (IncomeEntry $e) use (&$buckets): void {
                $key = $e->date->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['income'] += $e->amount_cents;
                }
            });

        Expense::query()->whereBetween('date', [$from, $to])
            ->get(['date', 'amount_cents', 'tax_cents'])
            ->each(function (Expense $e) use (&$buckets): void {
                $key = $e->date->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['expenses'] += $e->amount_cents - $e->tax_cents;
                }
            });

        return array_values($buckets);
    }
}
