<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentStatus;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD for company expenses (money out). Expenses are global (not team-scoped);
 * amounts are stored as integer cents.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $category = ExpenseCategory::tryFrom((string) $request->query('category'));
        $status = PaymentStatus::tryFrom((string) $request->query('status'));
        [$sort, $dir] = $this->sortFor($request, ['date', 'amount_cents', 'description', 'category'], 'date');

        if ($request->query('sort') === null && $request->query('dir') === null) {
            $dir = 'desc';
        }

        $base = Expense::query()
            ->when($category, fn ($q) => $q->where('category', $category->value))
            ->when($status, fn ($q) => $q->where('payment_status', $status->value))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('date', '<=', $to))
            ->when($request->query('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->query('q'), function ($q, $term): void {
                $like = '%'.mb_strtolower((string) $term).'%';
                $q->where(function ($w) use ($like): void {
                    $w->orWhereRaw('LOWER(description) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(vendor) LIKE ?', [$like]);
                });
            });

        $totals = (clone $base)
            ->selectRaw('currency, SUM(amount_cents) AS total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $expenses = $base->with(['customer', 'project'])
            ->orderBy($sort, $dir)
            ->paginate(20)
            ->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'totals' => $totals,
            'categories' => ExpenseCategory::options(),
            'statuses' => PaymentStatus::options(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'activeCategory' => $category?->value,
            'activeStatus' => $status?->value,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        return view('expenses.create', $this->formData(new Expense(['currency' => 'EUR', 'tax_rate' => 19])));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = new Expense($this->attributes($request->validated()));
        $expense->created_by = $request->user()->id;
        $expense->customer_id = $this->scopedId(Customer::class, $request->validated()['customer_id'] ?? null);
        $expense->project_id = $this->scopedId(Project::class, $request->validated()['project_id'] ?? null);
        $expense->save();

        return redirect()->route('finance.expenses.show', $expense)->with('status', __('flash.expense_added'));
    }

    public function show(Expense $expense): View
    {
        $expense->load(['customer', 'project', 'files.tags']);

        return view('expenses.show', ['expense' => $expense]);
    }

    public function edit(Expense $expense): View
    {
        return view('expenses.edit', $this->formData($expense));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->fill($this->attributes($request->validated()));
        $expense->customer_id = $this->scopedId(Customer::class, $request->validated()['customer_id'] ?? null);
        $expense->project_id = $this->scopedId(Project::class, $request->validated()['project_id'] ?? null);
        $expense->save();

        return redirect()->route('finance.expenses.show', $expense)->with('status', __('flash.expense_updated'));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return redirect()->route('finance.expenses.index')->with('status', __('flash.expense_deleted'));
    }

    /**
     * Map validated input to expense attributes (amount -> cents + derived tax).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $amountCents = (int) round(((float) $data['amount']) * 100);
        $rate = (int) $data['tax_rate'];
        $taxCents = $rate > 0 ? (int) round($amountCents * $rate / (100 + $rate)) : 0;

        return [
            'date' => $data['date'],
            'description' => $data['description'],
            'vendor' => $data['vendor'] ?? null,
            'category' => $data['category'],
            'category_custom' => $data['category_custom'] ?? null,
            'amount_cents' => $amountCents,
            'currency' => $data['currency'],
            'tax_rate' => $rate,
            'tax_cents' => $taxCents,
            'payment_status' => $data['payment_status'],
            'paid_on' => $data['paid_on'] ?? null,
            'billable' => $data['billable'] ?? false,
            'labels' => $data['labels'] ?? [],
        ];
    }

    /**
     * Only accept a customer/project id the current user can actually see.
     *
     * @param  class-string<Model>  $model
     */
    private function scopedId(string $model, int|string|null $id): ?int
    {
        if ($id === null) {
            return null;
        }

        return $model::query()->whereKey($id)->value('id');
    }

    /**
     * Shared data for the create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function formData(Expense $expense): array
    {
        return [
            'expense' => $expense,
            'categories' => ExpenseCategory::options(),
            'statuses' => PaymentStatus::options(),
            'currencies' => config('finance.currencies'),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->with('customer')->orderBy('name')->get(),
        ];
    }
}
