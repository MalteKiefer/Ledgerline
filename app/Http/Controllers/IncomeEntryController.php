<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncomeEntryRequest;
use App\Http\Requests\UpdateIncomeEntryRequest;
use App\Models\Customer;
use App\Models\IncomeEntry;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD for manual income entries (money in, not derived from time). Global
 * (not team-scoped). Amounts are integer cents.
 */
class IncomeEntryController extends Controller
{
    public function index(Request $request): View
    {
        [$sort, $dir] = $this->sortFor($request, ['date', 'amount_cents', 'description'], 'date');

        if ($request->query('sort') === null && $request->query('dir') === null) {
            $dir = 'desc';
        }

        $base = IncomeEntry::query()
            ->when($request->query('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->query('q'), fn ($q, $term) => $q->whereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower((string) $term).'%']));

        $totals = (clone $base)
            ->selectRaw('currency, SUM(amount_cents) AS total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $entries = $base->with(['customer', 'project'])
            ->orderBy($sort, $dir)
            ->paginate(20)
            ->withQueryString();

        return view('income-entries.index', [
            'entries' => $entries,
            'totals' => $totals,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        return view('income-entries.create', $this->formData(new IncomeEntry(['currency' => 'EUR'])));
    }

    public function store(StoreIncomeEntryRequest $request): RedirectResponse
    {
        $this->persist(new IncomeEntry, $request);

        return redirect()->route('finance.income-entries.index')->with('status', 'Income added.');
    }

    public function edit(IncomeEntry $incomeEntry): View
    {
        return view('income-entries.edit', $this->formData($incomeEntry));
    }

    public function update(UpdateIncomeEntryRequest $request, IncomeEntry $incomeEntry): RedirectResponse
    {
        $this->persist($incomeEntry, $request);

        return redirect()->route('finance.income-entries.index')->with('status', 'Income updated.');
    }

    public function destroy(IncomeEntry $incomeEntry): RedirectResponse
    {
        $incomeEntry->delete();

        return redirect()->route('finance.income-entries.index')->with('status', 'Income deleted.');
    }

    private function persist(IncomeEntry $entry, StoreIncomeEntryRequest $request): void
    {
        $data = $request->validated();

        $entry->fill([
            'date' => $data['date'],
            'description' => $data['description'],
            'amount_cents' => (int) round(((float) $data['amount']) * 100),
            'currency' => $data['currency'],
        ]);
        $entry->customer_id = $this->scopedId(Customer::class, $data['customer_id'] ?? null);
        $entry->project_id = $this->scopedId(Project::class, $data['project_id'] ?? null);

        if ($entry->created_by === null) {
            $entry->created_by = $request->user()->id;
        }

        $entry->save();
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function scopedId(string $model, int|string|null $id): ?int
    {
        return $id === null ? null : $model::query()->whereKey($id)->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(IncomeEntry $entry): array
    {
        return [
            'entry' => $entry,
            'currencies' => config('finance.currencies'),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->with('customer')->orderBy('name')->get(),
        ];
    }
}
