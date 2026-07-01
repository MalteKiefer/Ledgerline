<x-layouts.app title="Tags">
    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">Settings</a> <span aria-hidden="true">/</span> Tags
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">Tags</h1>
    <p class="mt-1 text-sm text-gray-600">Tags for your active team, used on projects and files.</p>

    {{-- Add tag --}}
    <form method="POST" action="{{ route('settings.tags.store') }}"
        class="mt-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        @csrf
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="name" class="block text-sm font-medium text-gray-700">New tag</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Invoice" required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-700">Colour</span>
                <div class="mt-2">
                    @include("settings.tags._swatches", ["selected" => old("color")])
                </div>
            </div>
            <button type="submit"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Add tag</button>
        </div>
    </form>

    {{-- Existing tags --}}
    <div class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($tags->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No tags yet.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($tags as $tag)
                    <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center">
                        <form method="POST" action="{{ route('settings.tags.update', $tag) }}"
                            class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                            @csrf
                            @method('PUT')
                            <input type="text" name="name" value="{{ $tag->name }}"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:max-w-xs">
                            @include("settings.tags._swatches", ["selected" => $tag->color])
                            <span class="text-xs text-gray-400">used {{ $tag->projects_count + $tag->files_count }}×</span>
                            <button type="submit"
                                class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 sm:ml-auto">Save</button>
                        </form>
                        <form method="POST" action="{{ route('settings.tags.destroy', $tag) }}"
                            onsubmit="return confirm('Delete this tag? It will be removed from all projects and files.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>
