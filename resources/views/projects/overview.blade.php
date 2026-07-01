<x-layouts.app title="Projects">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Projects</h1>
            <p class="mt-1 text-sm text-gray-600">All projects across every customer.</p>
        </div>
        <a href="{{ route('projects.create') }}"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
            New project
        </a>
    </div>

    {{-- Type filter --}}
    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('projects.overview') }}"
            @class([
                'rounded-full px-3 py-1',
                'bg-gray-800 text-white' => $activeType === null,
                'bg-gray-100 text-gray-700 hover:bg-gray-200' => $activeType !== null,
            ])>All</a>
        @foreach ($types as $type)
            <a href="{{ route('projects.overview', ['type' => $type['value']]) }}"
                @class([
                    'rounded-full px-3 py-1',
                    'bg-gray-800 text-white' => $activeType === $type['value'],
                    'bg-gray-100 text-gray-700 hover:bg-gray-200' => $activeType !== $type['value'],
                ])>{{ $type['label'] }}</a>
        @endforeach
    </div>

    <div class="mt-4">
        <x-table-search placeholder="Search projects…" />
    </div>

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($projects->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No projects found.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="name" label="Project" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3">Customer</th>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="type" label="Type" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="status" label="Status" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3">Tags</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($projects as $project)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('projects.show', $project) }}" class="hover:underline">{{ $project->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <a href="{{ route('customers.show', $project->customer_id) }}" class="hover:underline">
                                    {{ $project->customer->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $project->type->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $project->status->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @forelse ($project->tags as $tag)
                                    <x-tag-chip :tag="$tag" class="mr-1" />
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
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
