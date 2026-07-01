@php
    $tabs = [
        ['label' => 'Expenses', 'route' => 'finance.expenses.index', 'pattern' => 'finance.expenses.*'],
        ['label' => 'Time', 'route' => 'finance.time-entries.index', 'pattern' => 'finance.time-entries.*'],
        ['label' => 'Income', 'route' => 'finance.income-entries.index', 'pattern' => 'finance.income-entries.*'],
        ['label' => 'Invoices', 'route' => 'finance.invoices.index', 'pattern' => 'finance.invoices.*'],
        ['label' => 'Report', 'route' => 'finance.report', 'pattern' => 'finance.report'],
    ];
@endphp

<nav class="mb-6 flex gap-1 border-b border-gray-200 text-sm">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route']) }}"
            @class([
                '-mb-px border-b-2 px-4 py-2 font-medium',
                'border-gray-800 text-gray-900' => request()->routeIs($tab['pattern']),
                'border-transparent text-gray-500 hover:text-gray-700' => ! request()->routeIs($tab['pattern']),
            ])>{{ $tab['label'] }}</a>
    @endforeach
</nav>
