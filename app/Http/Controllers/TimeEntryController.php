<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD for billable time entries (money in). Global (not team-scoped). Duration
 * is stored in minutes and the resolved hourly rate in cents.
 */
class TimeEntryController extends Controller
{
    public function index(Request $request): View
    {
        [$sort, $dir] = $this->sortFor($request, ['date', 'minutes', 'rate_cents', 'description'], 'date');

        if ($request->query('sort') === null && $request->query('dir') === null) {
            $dir = 'desc';
        }

        $base = TimeEntry::query()
            ->when($request->query('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->query('q'), fn ($q, $term) => $q->whereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower((string) $term).'%']));

        // Sum minutes * rate per currency, then divide by 60 for the amount.
        $totals = (clone $base)
            ->selectRaw('currency, SUM(minutes * rate_cents) AS micro')
            ->groupBy('currency')
            ->pluck('micro', 'currency')
            ->map(fn ($micro): int => (int) round(((int) $micro) / 60));

        $entries = $base->with(['customer', 'project'])
            ->orderBy($sort, $dir)
            ->paginate(20)
            ->withQueryString();

        return view('time-entries.index', [
            'entries' => $entries,
            'totals' => $totals,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        return view('time-entries.create', $this->formData(new TimeEntry(['currency' => 'EUR'])));
    }

    public function store(StoreTimeEntryRequest $request): RedirectResponse
    {
        $this->persist(new TimeEntry, $request);

        return redirect()->route('finance.time-entries.index')->with('status', 'Time entry added.');
    }

    public function edit(TimeEntry $timeEntry): View
    {
        return view('time-entries.edit', $this->formData($timeEntry));
    }

    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->persist($timeEntry, $request);

        return redirect()->route('finance.time-entries.index')->with('status', 'Time entry updated.');
    }

    public function destroy(TimeEntry $timeEntry): RedirectResponse
    {
        $timeEntry->delete();

        return redirect()->route('finance.time-entries.index')->with('status', 'Time entry deleted.');
    }

    /**
     * Fill and save a time entry, resolving minutes and the effective rate.
     */
    private function persist(TimeEntry $entry, StoreTimeEntryRequest $request): void
    {
        $data = $request->validated();

        $customer = $this->scoped(Customer::query(), $data['customer_id'] ?? null);
        $project = $this->scoped(Project::query()->with('customer'), $data['project_id'] ?? null);

        $entry->fill([
            'date' => $data['date'],
            'description' => $data['description'],
            'minutes' => (int) round(((float) $data['hours']) * 60),
            'rate_cents' => $this->resolveRate($data['rate'] ?? null, $project, $customer),
            'currency' => $data['currency'],
            'billable' => $data['billable'] ?? true,
        ]);
        $entry->customer_id = $customer?->id;
        $entry->project_id = $project?->id;

        if ($entry->created_by === null) {
            $entry->created_by = $request->user()->id;
        }

        $entry->save();
    }

    /**
     * Resolve the hourly rate: entry, then project, then the project's or the
     * entry's customer default (all in cents).
     */
    private function resolveRate(mixed $rate, ?Project $project, ?Customer $customer): int
    {
        if ($rate !== null && $rate !== '') {
            return (int) round(((float) $rate) * 100);
        }

        return $project?->default_rate_cents
            ?? $project?->customer?->default_rate_cents
            ?? $customer?->default_rate_cents
            ?? 0;
    }

    /**
     * Resolve a scoped model instance from an id (or null).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     */
    private function scoped(Builder $query, int|string|null $id): mixed
    {
        return $id === null ? null : $query->whereKey($id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(TimeEntry $entry): array
    {
        return [
            'entry' => $entry,
            'currencies' => config('finance.currencies'),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'default_rate_cents']),
            'projects' => Project::query()->with('customer')->orderBy('name')->get(),
        ];
    }
}
