<x-layouts.app :title="__('branches.index.title')">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $customer) }}" class="hover:underline">{{ $customer->name }}</a>
    </p>
    <div class="mt-1 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('branches.index.heading') }}</h1>
        <a href="{{ route('customers.branches.create', $customer) }}"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            {{ __('branches.index.add') }}
        </a>
    </div>

    <div class="mt-4">
        <x-table-search :placeholder="__('branches.index.search_placeholder')" />
    </div>

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($branches->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">{{ __('branches.index.empty') }}</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="name" :label="__('branches.index.col_name')" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="city" :label="__('branches.index.col_city')" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="country" :label="__('branches.index.col_country')" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3">{{ __('branches.index.col_manager') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('branches.index.actions') }}</span></th>
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
                                <a href="{{ route('branches.edit', $branch) }}" class="text-gray-600 hover:text-gray-900">{{ __('branches.index.edit') }}</a>
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
