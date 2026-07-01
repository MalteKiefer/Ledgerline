<x-layouts.app title="New expense">
    <p class="text-sm text-gray-500">
        <a href="{{ route('finance.expenses.index') }}" class="hover:underline">Expenses</a>
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">New expense</h1>

    <form method="POST" action="{{ route('finance.expenses.store') }}"
        class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @include('expenses._form')
        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Create expense</button>
            <a href="{{ route('finance.expenses.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
