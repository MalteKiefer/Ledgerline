<x-layouts.app title="Contacts">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $customer) }}" class="hover:underline">{{ $customer->name }}</a>
    </p>
    <div class="mt-1 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">Contacts</h1>
        <a href="{{ route('customers.contacts.create', $customer) }}"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            Add contact
        </a>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($contacts->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No contacts yet.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">Name</th>
                        <th scope="col" class="px-4 py-3">Function</th>
                        <th scope="col" class="px-4 py-3">Email</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($contacts as $contact)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('contacts.show', $contact) }}" class="hover:underline">
                                    {{ $contact->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $contact->function->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $contact->email }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('contacts.edit', $contact) }}"
                                    class="text-gray-600 hover:text-gray-900">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">
        {{ $contacts->links() }}
    </div>
</x-layouts.app>
