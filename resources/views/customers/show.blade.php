<x-layouts.app :title="$customer->name">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $customer->name }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('customers.edit', $customer) }}"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                Edit
            </a>
            <form method="POST" action="{{ route('customers.destroy', $customer) }}"
                onsubmit="return confirm('Delete this customer? This cannot be undone.');">
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
            @foreach ([
                'Email' => $customer->email,
                'Phone' => $customer->phone,
                'VAT ID' => $customer->vat_id,
                'Street' => $customer->street,
                'Postal code' => $customer->postal_code,
                'City' => $customer->city,
                'Country' => $customer->country,
            ] as $label => $value)
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ $label }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach

            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Notes</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $customer->notes ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Back to customers</a>
    </div>
</x-layouts.app>
