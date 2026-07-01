<x-layouts.app :title="$project->name">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $project->customer_id) }}" class="hover:underline">
            {{ $project->customer->name }}
        </a>
        <span aria-hidden="true">/</span> Projects
    </p>

    <div class="mt-1 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $project->name }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('projects.edit', $project) }}"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                Edit
            </a>
            <form method="POST" action="{{ route('projects.destroy', $project) }}"
                onsubmit="return confirm('Delete this project? This cannot be undone.');">
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
                <dt class="text-sm font-medium text-gray-500">Reference</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $project->reference ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Type</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $project->type->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Status</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $project->status->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Priority</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $project->priority->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Estimated hours</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $project->estimated_hours !== null ? rtrim(rtrim((string) $project->estimated_hours, '0'), '.') : '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Start date</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $project->start_date?->format('Y-m-d') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">End date</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $project->end_date?->format('Y-m-d') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Budget</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $project->budget !== null ? number_format((float) $project->budget, 2) : '—' }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Tags</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @forelse ($project->tags as $tag)
                        <x-tag-chip :tag="$tag" class="mr-1" />
                    @empty
                        —
                    @endforelse
                </dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Description</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $project->description ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <section class="mt-8">
        <h2 class="text-lg font-semibold text-gray-900">Files</h2>
        <div class="mt-3">
            <x-file-panel :files="$project->files"
                :upload-route="route('projects.files.store', $project)"
                :tag-suggestions="$tagSuggestions" />
        </div>
    </section>

    <div class="mt-6">
        <a href="{{ route('customers.show', $project->customer_id) }}"
            class="text-sm text-gray-600 hover:text-gray-900">&larr; Back to customer</a>
    </div>
</x-layouts.app>
