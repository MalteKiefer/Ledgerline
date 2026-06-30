<x-layouts.app title="Edit customer">
    <h1 class="text-2xl font-semibold text-gray-900">Edit customer</h1>

    <form method="POST" action="{{ route('customers.update', $customer) }}"
        class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        @include('customers._form')

        <div class="mt-6 flex items-center gap-3">
            <button type="submit"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                Save changes
            </button>
            <a href="{{ route('customers.show', $customer) }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
