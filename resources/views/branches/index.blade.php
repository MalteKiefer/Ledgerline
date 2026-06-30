<x-layouts.app title="Branches">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $customer) }}" class="hover:underline">{{ $customer->name }}</a>
    </p>
    <div class="mt-1 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">Branches</h1>
        <a href="{{ route('customers.branches.create', $customer) }}"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            Add branch
        </a>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($branches->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No branches yet.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">Name</th>
                        <th scope="col" class="px-4 py-3">City</th>
                        <th scope="col" class="px-4 py-3">Country</th>
                        <th scope="col" class="px-4 py-3">Manager</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($branches as $branch)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('branches.show', $branch) }}" class="hover:underline">{{ $branch->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $branch->city ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($name = \App\Support\Countries::name($branch->country))
                                    {{ \App\Support\Countries::flag($branch->country) }} {{ $name }}
                                @else — @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $branch->manager?->name ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('branches.edit', $branch) }}" class="text-gray-600 hover:text-gray-900">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">
        {{ $branches->links() }}
    </div>
</x-layouts.app>
