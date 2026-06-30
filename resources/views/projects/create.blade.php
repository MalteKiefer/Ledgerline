<x-layouts.app title="New project">
    <p class="text-sm text-gray-500">
        @if ($lockedCustomer)
            <a href="{{ route('customers.show', $lockedCustomer) }}" class="hover:underline">{{ $lockedCustomer->name }}</a>
            <span aria-hidden="true">/</span> Projects
        @else
            <a href="{{ route('projects.overview') }}" class="hover:underline">Projects</a>
        @endif
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">New project</h1>

    <form method="POST" action="{{ route('projects.store') }}"
        class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf

        @include('projects._form')

        <div class="mt-6 flex items-center gap-3">
            <button type="submit"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                Create project
            </button>
            <a href="{{ $lockedCustomer ? route('customers.show', $lockedCustomer) : route('projects.overview') }}"
                class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
