<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\UserSetting;
use App\Services\Finance\CategorySuggester;
use App\Services\Finance\FinanceDuplicates;
use App\Services\Finance\FinanceReports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side, READ-ONLY finance analytics (the authoritative source of truth
 * the client now consumes instead of computing in-browser): VAT advance return,
 * revenue KPIs/by-customer/monthly, per-account VAT, read-only duplicate
 * detection, and read-only merchant->category suggestions. Every query is
 * owner-scoped by the models' global scopes and NEVER mutates a finance row.
 */
class FinanceReportController extends Controller
{
    /** VAT return + revenue stats for a year (+ the current-year VAT for the dashboard). */
    public function reports(Request $request, FinanceReports $reports): JsonResponse
    {
        $this->requireUser($request);
        $current = (int) date('Y');
        $year = $request->integer('year') ?: $current;

        return response()->json([
            'year' => $year,
            'years' => $reports->activeYears(),
            'currentVat' => $reports->vatReturn($current),
            'vat' => $reports->vatReturn($year),
            'kpis' => $reports->yearKpis($year),
            'customers' => $reports->revenueByCustomer($year),
            'months' => $reports->monthlyRevenue($year),
            'aging' => $reports->aging(),
        ]);
    }

    /** VAT summary of one payment account's transactions for a year. */
    public function accountVat(Request $request, FinanceReports $reports): JsonResponse
    {
        $this->requireUser($request);
        $accountId = $request->integer('account_id');
        $year = $request->integer('year') ?: (int) date('Y');

        $tx = BankTransaction::query()
            ->where('payment_method_id', $accountId)
            ->get()
            ->filter(fn (BankTransaction $t): bool => (int) substr((string) $t->date, 0, 4) === $year)
            ->values();

        return response()->json($reports->accountVatSummary($tx));
    }

    /** Unified USt-Voranmeldung (output − input VAT = Zahllast) for a year/quarter. */
    public function vatAdvance(Request $request, FinanceReports $reports): JsonResponse
    {
        $uid = (int) $this->requireUser($request)->id;
        $year = $request->integer('year') ?: (int) date('Y');
        $quarter = $request->integer('quarter') ?: null;
        // VAT scheme from the company profile: Ist (cash-basis, default) vs Soll (accrual).
        $ist = (bool) UserSetting::for($uid)->invoice_vat_ist;

        return response()->json($reports->vatAdvanceReturn($year, $quarter, $ist));
    }

    /** Simplified EÜR (income − expenses = profit) for a year. */
    public function euer(Request $request, FinanceReports $reports): JsonResponse
    {
        $this->requireUser($request);
        $year = $request->integer('year') ?: (int) date('Y');

        return response()->json($reports->euer($year));
    }

    /** Read-only suspected-duplicate groups (invoices + transactions). */
    public function duplicates(Request $request, FinanceDuplicates $dupes): JsonResponse
    {
        $this->requireUser($request);

        return response()->json($dupes->detect());
    }

    /** Read-only merchant->category suggestions for uncategorised transactions. */
    public function categorySuggestions(Request $request, CategorySuggester $suggester): JsonResponse
    {
        $this->requireUser($request);

        return response()->json(['suggestions' => $suggester->suggestions()]);
    }
}
