<x-layouts.app :title="__('expenses.edit.title')">
    <p class="text-sm text-gray-500">
        <a href="{{ route('finance.expenses.index') }}" class="hover:underline">{{ __('expenses.edit.breadcrumb') }}</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('finance.expenses.show', $expense) }}" class="hover:underline">{{ $expense->description }}</a>
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('expenses.edit.heading') }}</h1>

    <form method="POST" action="{{ route('finance.expenses.update', $expense) }}"
        class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('expenses._form')
        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('expenses.edit.submit') }}</button>
            <a href="{{ route('finance.expenses.show', $expense) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('expenses.edit.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
