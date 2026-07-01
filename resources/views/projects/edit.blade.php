<x-layouts.app title="Edit project">
    <p class="text-sm text-gray-500">
        <a href="{{ route('customers.show', $project->customer_id) }}" class="hover:underline">
            {{ $project->customer->name }}
        </a>
        <span aria-hidden="true">/</span> {{ __('projects.edit_projects_crumb') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('projects.edit_heading') }}</h1>

    <form method="POST" action="{{ route('projects.update', $project) }}"
        class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        @include('projects._form')

        <div class="mt-6 flex items-center gap-3">
            <button type="submit"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                {{ __('projects.edit_submit') }}
            </button>
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('projects.edit_cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
