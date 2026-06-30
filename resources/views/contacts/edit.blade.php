<x-layouts.app title="Edit contact">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $contact->customer_id) }}" class="hover:underline">
            {{ $contact->customer->name }}
        </a>
        <span aria-hidden="true">/</span> Contacts
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">Edit contact</h1>

    <form method="POST" action="{{ route('contacts.update', $contact) }}"
        class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        @include('contacts._form')

        <div class="mt-6 flex items-center gap-3">
            <button type="submit"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                Save changes
            </button>
            <a href="{{ route('contacts.show', $contact) }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
