<x-layouts.app title="Projects">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $customer) }}" class="hover:underline">{{ $customer->name }}</a>
    </p>
    <div class="mt-1 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('projects.index_heading') }}</h1>
        <a href="{{ route('projects.create', ['customer' => $customer->id]) }}"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            {{ __('projects.index_add_project') }}
        </a>
    </div>

    <div class="mt-4">
        <x-table-search :placeholder="__('projects.search_placeholder')" />
    </div>

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($projects->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">{{ __('projects.empty') }}</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="name" :label="__('projects.col_name')" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="reference" :label="__('projects.col_reference')" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="status" :label="__('projects.col_status')" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('projects.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($projects as $project)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('projects.show', $project) }}" class="hover:underline">
                                    {{ $project->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $project->reference ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $project->status->label() }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('projects.edit', $project) }}"
                                    class="text-gray-600 hover:text-gray-900">{{ __('projects.edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">
        {{ $projects->links() }}
    </div>
</x-layouts.app>
