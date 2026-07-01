<x-layouts.app :title="__('invoices.trash.title')">
    <x-finance-nav />
    <p class="text-sm text-gray-500"><a href="{{ route('finance.invoices.index') }}" class="hover:underline">{{ __('invoices.trash.back') }}</a></p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('invoices.trash.heading') }}</h1>

    <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($invoices->isEmpty())
            <p class="px-4 py-10 text-center text-sm text-gray-500">{{ __('invoices.trash.empty') }}</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('invoices.index.col_number') }}</th>
                        <th class="px-4 py-3">{{ __('invoices.index.col_customer') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('invoices.index.col_gross') }}</th>
                        <th class="px-4 py-3">{{ __('invoices.trash.deleted_at') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $invoice->number ?? ('#'.$invoice->id) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $invoice->customer?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-900">{{ $invoice->gross()->format() }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $invoice->deleted_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('finance.invoices.restore', $invoice->id) }}">
                                        @csrf
                                        <button type="submit" class="text-sm text-gray-700 hover:text-gray-900">{{ __('invoices.trash.restore') }}</button>
                                    </form>
                                    <x-confirm-action :action="route('finance.invoices.force-destroy', $invoice->id)" method="DELETE"
                                        :trigger="__('invoices.trash.delete_forever')" trigger-class="text-sm text-red-600 hover:text-red-800"
                                        :message="__('invoices.trash.delete_forever_confirm')" />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-layouts.app>
