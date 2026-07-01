<x-layouts.app :title="__('income.edit.title')">
    <p class="text-sm text-gray-500"><a href="{{ route('finance.income-entries.index') }}" class="hover:underline">{{ __('income.edit.breadcrumb') }}</a></p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('income.edit.heading') }}</h1>

    <form method="POST" action="{{ route('finance.income-entries.update', $entry) }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('income-entries._form')
        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('income.edit.submit') }}</button>
            <a href="{{ route('finance.income-entries.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('income.edit.cancel') }}</a>
        </div>
    </form>

    <form method="POST" action="{{ route('finance.income-entries.destroy', $entry) }}" class="mt-3" onsubmit="return confirm('{{ __('income.edit.confirm_delete') }}');">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-sm text-red-600 hover:text-red-800">{{ __('income.edit.delete') }}</button>
    </form>
</x-layouts.app>
