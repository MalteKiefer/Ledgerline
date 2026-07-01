<x-layouts.app title="Income">
    <x-finance-nav />

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Income</h1>
            <p class="mt-1 text-sm text-gray-600">Manual income not derived from time.</p>
        </div>
        <a href="{{ route('finance.income-entries.create') }}"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">New income</a>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        @forelse ($totals as $currency => $cents)
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <span class="text-xs uppercase tracking-wide text-gray-400">Total ({{ $currency }})</span>
                <div class="text-lg font-semibold text-gray-900">{{ number_format($cents / 100, 2) }} {{ $currency }}</div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-500 shadow-sm">No income yet.</div>
        @endforelse
    </div>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500">Search</label>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Description…"
                class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        </div>
        <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Filter</button>
        @if (request('q'))<a href="{{ route('finance.income-entries.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>@endif
    </form>

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($entries->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No income entries.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3"><x-sortable-header column="date" label="Date" :sort="$sort" :dir="$dir" /></th>
                        <th class="px-4 py-3"><x-sortable-header column="description" label="Description" :sort="$sort" :dir="$dir" /></th>
                        <th class="px-4 py-3">Linked</th>
                        <th class="px-4 py-3 text-right"><x-sortable-header column="amount_cents" label="Amount" :sort="$sort" :dir="$dir" /></th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($entries as $entry)
                        <tr>
                            <td class="px-4 py-3 text-gray-600">{{ $entry->date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('finance.income-entries.edit', $entry) }}" class="hover:underline">{{ $entry->description }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $entry->customer?->name }}@if ($entry->customer && $entry->project) · @endif{{ $entry->project?->name }}
                                @if (! $entry->customer && ! $entry->project)—@endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900">{{ $entry->amount()->format() }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('finance.income-entries.edit', $entry) }}" class="text-sm text-gray-500 hover:text-gray-900">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.app>
