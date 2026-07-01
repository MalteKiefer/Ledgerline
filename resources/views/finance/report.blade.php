<x-layouts.app :title="__('report.title')">
    @php
        $eur = fn (int $cents): string => number_format($cents / 100, 2).' €';
        $maxMonth = max(1, collect($monthly)->flatMap(fn ($m) => [$m['income'], $m['expenses']])->max() ?: 1);
        $maxCat = max(1, collect($byCategory)->max('net') ?: 1);
    @endphp

    <x-finance-nav />

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('report.heading') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ __('report.subtitle', ['from' => $from, 'to' => $to]) }}</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('report.from') }}</label>
                <input type="date" name="from" value="{{ $from }}" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('report.to') }}</label>
                <input type="date" name="to" value="{{ $to }}" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            </div>
            <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('report.apply') }}</button>
        </form>
    </div>

    {{-- Summary --}}
    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <span class="text-xs uppercase tracking-wide text-gray-400">{{ __('report.income') }}</span>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $eur($summary['income']) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <span class="text-xs uppercase tracking-wide text-gray-400">{{ __('report.expenses') }}</span>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $eur($summary['expenses']) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <span class="text-xs uppercase tracking-wide text-gray-400">{{ __('report.profit') }}</span>
            <div class="mt-1 text-lg font-semibold {{ $summary['profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $eur($summary['profit']) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <span class="text-xs uppercase tracking-wide text-gray-400">{{ __('report.outstanding') }}</span>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $eur($summary['outstanding']) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <span class="text-xs uppercase tracking-wide text-gray-400">{{ __('report.unbilled_work') }}</span>
            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $eur($summary['unbilled']) }}</div>
        </div>
    </div>

    {{-- Monthly --}}
    <div class="mt-8 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('report.monthly_heading') }}</h2>
        <div class="mt-4 space-y-3">
            @foreach ($monthly as $m)
                <div>
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>{{ $m['month'] }}</span>
                        <span>{{ $eur($m['income']) }} · <span class="text-gray-400">{{ $eur($m['expenses']) }}</span></span>
                    </div>
                    <div class="mt-1 space-y-1">
                        <div class="h-2 rounded bg-green-500" style="width: {{ round($m['income'] / $maxMonth * 100, 1) }}%"></div>
                        <div class="h-2 rounded bg-gray-400" style="width: {{ round($m['expenses'] / $maxMonth * 100, 1) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-gray-400"><span class="inline-block h-2 w-2 rounded bg-green-500"></span> {{ __('report.legend_income') }} · <span class="inline-block h-2 w-2 rounded bg-gray-400"></span> {{ __('report.legend_expenses') }}</p>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Per customer --}}
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <h2 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-900">{{ __('report.profit_by_customer') }}</h2>
            @if (empty($perCustomer))
                <p class="px-4 py-6 text-center text-sm text-gray-500">{{ __('report.no_data') }}</p>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-2">{{ __('report.col_customer') }}</th><th class="px-4 py-2 text-right">{{ __('report.col_revenue') }}</th><th class="px-4 py-2 text-right">{{ __('report.col_expenses') }}</th><th class="px-4 py-2 text-right">{{ __('report.col_profit') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($perCustomer as $row)
                            <tr>
                                <td class="px-4 py-2 text-gray-900">{{ $row['name'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-600">{{ $eur($row['revenue']) }}</td>
                                <td class="px-4 py-2 text-right text-gray-600">{{ $eur($row['expenses']) }}</td>
                                <td class="px-4 py-2 text-right font-medium {{ $row['profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $eur($row['profit']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- By category --}}
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <h2 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-900">{{ __('report.expenses_by_category') }}</h2>
            @if (empty($byCategory))
                <p class="px-4 py-6 text-center text-sm text-gray-500">{{ __('report.no_expenses') }}</p>
            @else
                <div class="space-y-3 p-4">
                    @foreach ($byCategory as $cat)
                        <div>
                            <div class="flex justify-between text-sm"><span class="text-gray-700">{{ $cat['label'] }}</span><span class="text-gray-600">{{ $eur($cat['net']) }}</span></div>
                            <div class="mt-1 h-2 rounded bg-gray-800" style="width: {{ round($cat['net'] / $maxCat * 100, 1) }}%"></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
