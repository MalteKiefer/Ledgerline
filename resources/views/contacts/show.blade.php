<x-layouts.app :title="$contact->name">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $contact->customer_id) }}" class="hover:underline">
            {{ $contact->customer->name }}
        </a>
        <span aria-hidden="true">/</span> Contacts
    </p>

    <div class="mt-1 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $contact->name }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('contacts.edit', $contact) }}"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                Edit
            </a>
            <form method="POST" action="{{ route('contacts.destroy', $contact) }}"
                onsubmit="return confirm('Delete this contact? This cannot be undone.');">
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
        <dl class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">Function</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $contact->function->label() }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Email addresses</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @forelse ($contact->emails as $email)
                        <div class="flex items-baseline gap-2">
                            <span class="w-24 shrink-0 text-gray-500">{{ $email->label }}</span>
                            <a href="mailto:{{ $email->email }}" class="text-gray-900 hover:underline">{{ $email->email }}</a>
                        </div>
                    @empty
                        —
                    @endforelse
                </dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Phone numbers</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @forelse ($contact->phones as $phone)
                        <div class="flex items-baseline gap-2">
                            <span class="w-24 shrink-0 text-gray-500">{{ $phone->label }}</span>
                            <a href="tel:{{ $phone->phone }}" class="text-gray-900 hover:underline">{{ $phone->phone }}</a>
                        </div>
                    @empty
                        —
                    @endforelse
                </dd>
            </div>
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('customers.show', $contact->customer_id) }}"
            class="text-sm text-gray-600 hover:text-gray-900">&larr; Back to customer</a>
    </div>
</x-layouts.app>
