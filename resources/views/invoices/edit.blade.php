<x-layouts.app :title="__('invoices.edit.title')">
    <p class="text-sm text-gray-500">
        <a href="{{ route('finance.invoices.index') }}" class="hover:underline">{{ __('invoices.edit.breadcrumb') }}</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('finance.invoices.show', $invoice) }}" class="hover:underline">{{ __('invoices.edit.draft', ['id' => $invoice->id]) }}</a>
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('invoices.edit.heading') }}</h1>

    <form method="POST" action="{{ route('finance.invoices.update', $invoice) }}" class="mt-6">
        @csrf
        @method('PUT')
        @include('invoices._form', ['showImports' => false])
        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('invoices.edit.submit') }}</button>
            <a href="{{ route('finance.invoices.show', $invoice) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('invoices.edit.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
