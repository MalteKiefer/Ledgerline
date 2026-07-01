<x-layouts.app title="Edit time entry">
    <p class="text-sm text-gray-500"><a href="{{ route('finance.time-entries.index') }}" class="hover:underline">Time</a></p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">Edit time entry</h1>

    <form method="POST" action="{{ route('finance.time-entries.update', $entry) }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('time-entries._form')
        <div class="mt-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Save</button>
                <a href="{{ route('finance.time-entries.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('finance.time-entries.destroy', $entry) }}" class="mt-3" onsubmit="return confirm('Delete this time entry?');">
        @csrf
        @method('DELETE')
        <button type="submit" class="text-sm text-red-600 hover:text-red-800">Delete time entry</button>
    </form>
</x-layouts.app>
