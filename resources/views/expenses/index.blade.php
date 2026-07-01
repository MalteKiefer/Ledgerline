<x-layouts.app title="Expenses">
    <x-finance-nav />
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Expenses</h1>
            <p class="mt-1 text-sm text-gray-600">Money spent for the company, customers or projects.</p>
        </div>
        <a href="{{ route('finance.expenses.create') }}"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">New expense</a>
    </div>

    {{-- Total(s) for the current filter --}}
    <div class="mt-4 flex flex-wrap gap-3">
        @forelse ($totals as $currency => $cents)
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <span class="text-xs uppercase tracking-wide text-gray-400">Total ({{ $currency }})</span>
                <div class="text-lg font-semibold text-gray-900">{{ number_format($cents / 100, 2) }} {{ $currency }}</div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-500 shadow-sm">No expenses.</div>
        @endforelse
    </div>

    {{-- Filters --}}
    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500">Search</label>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Description / vendor…"
                class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">Category</label>
            <select name="category" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <option value="">All</option>
                @foreach ($categories as $c)
                    <option value="{{ $c['value'] }}" @selected($activeCategory === $c['value'])>{{ $c['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">Status</label>
            <select name="status" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <option value="">All</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s['value'] }}" @selected($activeStatus === $s['value'])>{{ $s['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        </div>
        <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Filter</button>
        @if (request()->hasAny(['q', 'category', 'status', 'from', 'to']))
            <a href="{{ route('finance.expenses.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
        @endif
    </form>

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($expenses->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No expenses found.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="date" label="Date" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="description" label="Description" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="category" label="Category" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3">Linked</th>
                        <th scope="col" class="px-4 py-3 text-right"><x-sortable-header column="amount_cents" label="Gross" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($expenses as $expense)
                        <tr>
                            <td class="px-4 py-3 text-gray-600">{{ $expense->date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('finance.expenses.show', $expense) }}" class="hover:underline">{{ $expense->description }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $expense->categoryLabel() }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $expense->customer?->name }}@if ($expense->customer && $expense->project) · @endif{{ $expense->project?->name }}
                                @if (! $expense->customer && ! $expense->project)—@endif
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900">{{ $expense->gross()->format() }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded px-2 py-0.5 text-xs',
                                    'bg-green-100 text-green-800' => $expense->payment_status->value === 'PAID',
                                    'bg-amber-100 text-amber-800' => $expense->payment_status->value === 'OPEN',
                                ])>{{ $expense->payment_status->label() }}</span>
                                @if ($expense->billable)<span class="ml-1 rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800">Billable</span>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">{{ $expenses->links() }}</div>
</x-layouts.app>
