<x-layouts.app title="Projects">
    <h1 class="text-2xl font-semibold text-gray-900">Projects</h1>
    <p class="mt-1 text-sm text-gray-600">All projects across every customer.</p>

    <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($projects->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No projects yet.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">Project</th>
                        <th scope="col" class="px-4 py-3">Customer</th>
                        <th scope="col" class="px-4 py-3">Reference</th>
                        <th scope="col" class="px-4 py-3">Status</th>
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
                            <td class="px-4 py-3 text-gray-600">
                                <a href="{{ route('customers.show', $project->customer_id) }}" class="hover:underline">
                                    {{ $project->customer->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $project->reference ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $project->status->label() }}</td>
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
