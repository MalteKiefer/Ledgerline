<x-layouts.app :title="$branch->name">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $branch->customer_id) }}" class="hover:underline">
            {{ $branch->customer->name }}
        </a>
        <span aria-hidden="true">/</span> Branches
    </p>

    <div class="mt-1 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $branch->name }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('branches.edit', $branch) }}"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                Edit
            </a>
            <form method="POST" action="{{ route('branches.destroy', $branch) }}"
                onsubmit="return confirm('Delete this branch? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">Branch manager</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($branch->manager)
                        <a href="{{ route('contacts.show', $branch->manager) }}" class="text-gray-900 hover:underline">
                            {{ $branch->manager->name }}
                        </a>
                    @else — @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Email</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($branch->email)
                        <a href="mailto:{{ $branch->email }}" class="text-gray-900 hover:underline">{{ $branch->email }}</a>
                    @else — @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($branch->phone)
                        <a href="tel:{{ $branch->phone }}" class="text-gray-900 hover:underline">{{ $branch->phone }}</a>
                    @else — @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Street</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $branch->street ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Postal code</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $branch->postal_code ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">City</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $branch->city ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Country</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($name = \App\Support\Countries::name($branch->country))
                        {{ \App\Support\Countries::flag($branch->country) }} {{ $name }}
                    @else — @endif
                </dd>
            </div>
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('customers.show', $branch->customer_id) }}"
            class="text-sm text-gray-600 hover:text-gray-900">&larr; Back to customer</a>
    </div>
</x-layouts.app>
