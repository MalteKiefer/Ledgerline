<x-layouts.app :title="__('invoices.index.title')">
    <x-finance-nav />

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('invoices.index.heading') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ __('invoices.index.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.invoices.trash') }}"
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('invoices.index.trash') }}</a>
            <a href="{{ route('finance.invoices.import.create') }}"
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('invoices.index.import') }}</a>
            <a href="{{ route('finance.invoices.create') }}"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('invoices.index.new') }}</a>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        @forelse ($totals as $row)
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <span class="text-xs uppercase tracking-wide text-gray-400">{{ $row->currency }}</span>
                <div class="text-lg font-semibold text-gray-900">{{ number_format($row->gross / 100, 2) }} {{ $row->currency }}</div>
                <div class="text-xs text-gray-500">{{ __('invoices.index.outstanding') }}: {{ number_format($row->outstanding / 100, 2) }}</div>
            </div>
        @empty
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-500 shadow-sm">{{ __('invoices.index.no_invoices_yet') }}</div>
        @endforelse
    </div>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500">{{ __('invoices.index.search') }}</label>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('invoices.index.search_placeholder') }}"
                class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">{{ __('invoices.index.status') }}</label>
            <select name="status" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <option value="">{{ __('invoices.index.all') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s['value'] }}" @selected($activeStatus === $s['value'])>{{ $s['label'] }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('invoices.index.filter') }}</button>
        @if (request()->hasAny(['q', 'status']))<a href="{{ route('finance.invoices.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('invoices.index.clear') }}</a>@endif
    </form>

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($invoices->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">{{ __('invoices.index.empty') }}</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3"><x-sortable-header column="number" :label="__('invoices.index.col_number')" :sort="$sort" :dir="$dir" /></th>
                        <th class="px-4 py-3"><x-sortable-header column="issue_date" :label="__('invoices.index.col_date')" :sort="$sort" :dir="$dir" /></th>
                        <th class="px-4 py-3">{{ __('invoices.index.col_customer') }}</th>
                        <th class="px-4 py-3"><x-sortable-header column="status" :label="__('invoices.index.col_status')" :sort="$sort" :dir="$dir" /></th>
                        <th class="px-4 py-3 text-right"><x-sortable-header column="gross_cents" :label="__('invoices.index.col_gross')" :sort="$sort" :dir="$dir" /></th>
                        <th class="px-4 py-3 text-right">{{ __('invoices.index.col_outstanding') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('finance.invoices.show', $invoice) }}" class="hover:underline">
                                    {{ $invoice->number ?? __('invoices.index.draft', ['id' => $invoice->id]) }}
                                </a>
                                @if ($invoice->type->value === 'CREDIT_NOTE')<span class="ml-1 rounded bg-purple-100 px-2 py-0.5 text-xs text-purple-800">{{ __('invoices.index.credit') }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $invoice->issue_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $invoice->customer?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded px-2 py-0.5 text-xs',
                                    'bg-gray-100 text-gray-700' => $invoice->status->value === 'DRAFT',
                                    'bg-blue-100 text-blue-800' => $invoice->status->value === 'SENT',
                                    'bg-green-100 text-green-800' => $invoice->status->value === 'PAID',
                                    'bg-red-100 text-red-800' => $invoice->status->value === 'OVERDUE',
                                    'bg-gray-200 text-gray-600 line-through' => $invoice->status->value === 'CANCELLED',
                                ])>{{ $invoice->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900">{{ $invoice->gross()->format() }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $invoice->outstanding()->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
