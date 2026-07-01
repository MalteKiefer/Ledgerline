<x-layouts.app :title="$expense->description">
    <p class="text-sm text-gray-500">
        <a href="{{ route('finance.expenses.index') }}" class="hover:underline">{{ __('expenses.show.breadcrumb') }}</a>
    </p>
    <div class="mt-1 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $expense->description }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('finance.expenses.edit', $expense) }}"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('expenses.show.edit') }}</a>
            <form method="POST" action="{{ route('finance.expenses.destroy', $expense) }}"
                onsubmit="return confirm('{{ __('expenses.show.confirm_delete') }}');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('expenses.show.delete') }}</button>
            </form>
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('expenses.show.date') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $expense->date?->format('Y-m-d') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('expenses.show.vendor') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $expense->vendor ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('expenses.show.category') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $expense->categoryLabel() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('expenses.show.payment') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $expense->payment_status->label() }}@if ($expense->paid_on) · {{ $expense->paid_on->format('Y-m-d') }}@endif
                    @if ($expense->billable)<span class="ml-1 rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800">{{ __('expenses.show.billable') }}</span>@endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('expenses.show.net') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $expense->net()->format() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('expenses.show.vat', ['rate' => $expense->tax_rate]) }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $expense->tax()->format() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('expenses.show.gross') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $expense->gross()->format() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('expenses.show.linked') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($expense->customer)<a href="{{ route('customers.show', $expense->customer) }}" class="hover:underline">{{ $expense->customer->name }}</a>@endif
                    @if ($expense->customer && $expense->project) · @endif
                    @if ($expense->project)<a href="{{ route('projects.show', $expense->project) }}" class="hover:underline">{{ $expense->project->name }}</a>@endif
                    @if (! $expense->customer && ! $expense->project)—@endif
                </dd>
            </div>
            @if (! empty($expense->labels))
                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-500">{{ __('expenses.show.labels') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @foreach ($expense->labels as $label)
                            <span class="mr-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs">{{ $label }}</span>
                        @endforeach
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    <section class="mt-8">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('expenses.show.documents') }}</h2>
        <div class="mt-3">
            <x-file-panel :files="$expense->files" :upload-route="route('finance.expenses.files.store', $expense)" />
        </div>
    </section>
</x-layouts.app>
