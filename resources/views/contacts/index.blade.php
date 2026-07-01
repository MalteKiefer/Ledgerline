<x-layouts.app :title="__('contacts.index.title')">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $customer) }}" class="hover:underline">{{ $customer->name }}</a>
    </p>
    <div class="mt-1 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('contacts.index.heading') }}</h1>
        <a href="{{ route('customers.contacts.create', $customer) }}"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            {{ __('contacts.index.add') }}
        </a>
    </div>

    <div class="mt-4">
        <x-table-search :placeholder="__('contacts.index.search_placeholder')" />
    </div>

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($contacts->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">{{ __('contacts.index.empty') }}</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="name" :label="__('contacts.index.col_name')" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="function" :label="__('contacts.index.col_function')" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3">{{ __('contacts.index.col_emails') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('contacts.index.col_phones') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('contacts.index.actions') }}</span></th>
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
                            <td class="px-4 py-3 text-gray-600">
                                @forelse ($contact->emails as $email)
                                    <a href="mailto:{{ $email->email }}"
                                        class="block text-gray-700 hover:underline">{{ $email->email }}</a>
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @forelse ($contact->phones as $phone)
                                    <a href="tel:{{ $phone->phone }}"
                                        class="block text-gray-700 hover:underline">{{ $phone->phone }}</a>
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('contacts.edit', $contact) }}"
                                    class="text-gray-600 hover:text-gray-900">{{ __('contacts.index.edit') }}</a>
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
