@props([
    'files',
    'uploadRoute',
    'tagSuggestions' => [],
])

@php
    $formatBytes = static function (int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return number_format($value, $i > 0 ? 1 : 0).' '.$units[$i];
    };
@endphp

<div class="space-y-4">
    <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data"
        class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        @csrf
        <label class="block text-sm font-medium text-gray-700">Add a file</label>
        <div class="mt-1">
            <x-file-dropzone :id="'file-'.md5($uploadRoute)" />
        </div>
        <div class="mt-3">
            <x-tag-input name="tags" :suggestions="$tagSuggestions" />
        </div>
        <div class="mt-3">
            <button type="submit"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                Upload
            </button>
        </div>
    </form>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($files->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-gray-500">No files yet.</p>
        @else
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($files as $file)
                    <li class="flex items-center justify-between gap-4 px-4 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('files.download', $file) }}"
                                class="font-medium text-gray-900 hover:underline">{{ $file->name }}</a>
                            <div class="text-gray-500">
                                {{ $file->type->label() }} · {{ $formatBytes($file->size) }}
                                @foreach ($file->tags as $tag)
                                    <a href="{{ route('files.index', ['tag' => $tag->slug]) }}"
                                        class="ml-1 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-xs hover:bg-gray-200">{{ $tag->name }}</a>
                                @endforeach
                            </div>
                        </div>
                        <form method="POST" action="{{ route('files.destroy', $file) }}"
                            onsubmit="return confirm('Delete this file?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800">Delete</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
