<x-layouts.app :title="__('income.create.title')">
    <p class="text-sm text-gray-500"><a href="{{ route('finance.income-entries.index') }}" class="hover:underline">{{ __('income.create.breadcrumb') }}</a></p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('income.create.heading') }}</h1>

    <form method="POST" action="{{ route('finance.income-entries.store') }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @include('income-entries._form')
        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('income.create.submit') }}</button>
            <a href="{{ route('finance.income-entries.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('income.create.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
