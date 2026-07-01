<x-layouts.app :title="__('invoices.create.title')">
    <p class="text-sm text-gray-500"><a href="{{ route('finance.invoices.index') }}" class="hover:underline">{{ __('invoices.create.breadcrumb') }}</a></p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('invoices.create.heading') }}</h1>

    <form method="POST" action="{{ route('finance.invoices.store') }}" class="mt-6">
        @csrf
        @include('invoices._form', ['showImports' => true])
        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('invoices.create.submit') }}</button>
            <a href="{{ route('finance.invoices.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('invoices.create.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
