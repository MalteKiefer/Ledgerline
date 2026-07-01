<x-layouts.app title="Files">
    <h1 class="text-2xl font-semibold text-gray-900">Files</h1>
    <p class="mt-1 text-sm text-gray-600">All files across your team's customers and projects.</p>

    {{-- Upload and assign a file to any customer or project. --}}
    @if (! empty($targets))
        <form method="POST" action="{{ route('files.store') }}" enctype="multipart/form-data"
            class="mt-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            @csrf
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div x-data="selectCombobox(@js($targets), @js(old('attachable', '')))">
                    <label for="attachable" class="block text-sm font-medium text-gray-700">
                        Assign to<span class="text-red-600"> *</span>
                    </label>
                    <input type="hidden" name="attachable" :value="selected">
                    <div class="relative mt-1">
                        <input type="text" id="attachable" role="combobox" autocomplete="off"
                            :aria-expanded="open.toString()" x-model="query" @focus="open = true"
                            @input="syncFromQuery()" @keydown.escape="open = false" @click="open = true"
                            placeholder="Choose a customer or project…"
                            @error('attachable') aria-invalid="true" @enderror
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                        <ul x-show="open" x-cloak @click.outside="open = false" role="listbox"
                            class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
                            <template x-for="option in filtered" :key="option.value">
                                <li role="option" @click="choose(option)"
                                    class="cursor-pointer px-3 py-2 hover:bg-gray-100"
                                    :class="{ 'bg-gray-100 font-medium': String(selected) === String(option.value) }"
                                    x-text="option.label"></li>
                            </template>
                            <template x-if="filtered.length === 0">
                                <li class="px-3 py-2 text-gray-500">No match.</li>
                            </template>
                        </ul>
                    </div>
                    @error('attachable')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">File</label>
                    <div class="mt-1">
                        <x-file-dropzone id="overview-file" />
                    </div>
                </div>
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
    @else
        <p class="mt-4 rounded-md border border-gray-200 bg-white px-4 py-3 text-sm text-gray-500">
            Create a customer or project first to upload files.
        </p>
    @endif

    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('files.index') }}"
            @class([
                'rounded-full px-3 py-1',
                'bg-gray-800 text-white' => $activeType === null,
                'bg-gray-100 text-gray-700 hover:bg-gray-200' => $activeType !== null,
            ])>All</a>
        @foreach ($types as $type)
            <a href="{{ route('files.index', ['type' => $type['value']]) }}"
                @class([
                    'rounded-full px-3 py-1',
                    'bg-gray-800 text-white' => $activeType === $type['value'],
                    'bg-gray-100 text-gray-700 hover:bg-gray-200' => $activeType !== $type['value'],
                ])>{{ $type['label'] }}</a>
        @endforeach
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <x-table-search placeholder="Search files…" />
        @if ($activeTagName)
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-800 px-3 py-1 text-sm text-white">
                Tag: {{ $activeTagName }}
                <a href="{{ route('files.index', array_diff_key(request()->query(), ['tag' => '', 'page' => ''])) }}"
                    class="text-gray-300 hover:text-white" aria-label="Clear tag filter">&times;</a>
            </span>
        @endif
    </div>

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($files->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No files found.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="name" label="File" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3">Attached to</th>
                        <th scope="col" class="px-4 py-3"><x-sortable-header column="type" label="Type" :sort="$sort" :dir="$dir" /></th>
                        <th scope="col" class="px-4 py-3">Tags</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($files as $file)
                        @php $attached = $file->attachable; @endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('files.download', $file) }}" class="hover:underline">{{ $file->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($attached instanceof \App\Models\Customer)
                                    <a href="{{ route('customers.show', $attached) }}" class="hover:underline">{{ $attached->name }}</a>
                                @elseif ($attached instanceof \App\Models\Project)
                                    <a href="{{ route('projects.show', $attached) }}" class="hover:underline">{{ $attached->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $file->type->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @forelse ($file->tags as $tag)
                                    <a href="{{ route('files.index', ['tag' => $tag->slug]) }}"
                                        class="mr-1 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-xs hover:bg-gray-200">{{ $tag->name }}</a>
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
        {{ $files->links() }}
    </div>
</x-layouts.app>
