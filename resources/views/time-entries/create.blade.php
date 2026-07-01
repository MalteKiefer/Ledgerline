<x-layouts.app title="New time entry">
    <p class="text-sm text-gray-500"><a href="{{ route('finance.time-entries.index') }}" class="hover:underline">Time</a></p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">New time entry</h1>

    <form method="POST" action="{{ route('finance.time-entries.store') }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @include('time-entries._form')
        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Create</button>
            <a href="{{ route('finance.time-entries.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</x-layouts.app>
