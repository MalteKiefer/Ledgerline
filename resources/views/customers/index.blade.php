<x-layouts.app title="Customers">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">Customers</h1>
        <a href="{{ route('customers.create') }}"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            New customer
        </a>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($customers->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No customers yet.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">Name</th>
                        <th scope="col" class="px-4 py-3">Email</th>
                        <th scope="col" class="px-4 py-3">City</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($customers as $customer)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('customers.show', $customer) }}" class="hover:underline">
                                    {{ $customer->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $customer->email }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $customer->city }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('customers.edit', $customer) }}"
                                    class="text-gray-600 hover:text-gray-900">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
</x-layouts.app>
