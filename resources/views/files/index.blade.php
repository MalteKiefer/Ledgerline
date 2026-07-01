<x-layouts.app :title="__('files.index_title')">
  <div x-data="{ newFolder: false, uploadHere: false, assign: false }">
    <div class="flex items-start justify-between gap-3">
        <div>
            {{-- Breadcrumb --}}
            <nav class="text-sm text-gray-500">
                <a href="{{ route('files.index') }}" class="hover:underline">{{ __('files.breadcrumb_files') }}</a>
                @foreach ($breadcrumb as $crumb)
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('files.index', ['folder' => $crumb->id]) }}" class="hover:underline">{{ $crumb->name }}</a>
                @endforeach
            </nav>
            <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ $folder->name ?? __('files.heading_files') }}</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="newFolder = ! newFolder; uploadHere = false; assign = false"
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('files.new_folder') }}</button>
            <button type="button" @click="uploadHere = ! uploadHere; newFolder = false; assign = false"
                class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.upload_here') }}</button>
            @if (! empty($targets))
                <button type="button" @click="assign = ! assign; newFolder = false; uploadHere = false"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('files.attach_to_record') }}</button>
            @endif
        </div>
    </div>

    {{-- New folder --}}
    <form x-show="newFolder" x-cloak method="POST" action="{{ route('folders.store') }}"
        class="mt-4 flex items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        @csrf
        <input type="hidden" name="parent_id" value="{{ $folder?->id }}">
        <div class="flex-1">
            <label for="folder-name" class="block text-sm font-medium text-gray-700">{{ __('files.folder_name') }}</label>
            <input type="text" id="folder-name" name="name" required placeholder="{{ __('files.folder_name_placeholder') }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.create') }}</button>
    </form>

    {{-- Upload general file into the current folder --}}
    <form x-show="uploadHere" x-cloak method="POST" action="{{ route('files.store.general') }}" enctype="multipart/form-data"
        class="mt-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        @csrf
        <input type="hidden" name="folder_id" value="{{ $folder?->id }}">
        <label class="block text-sm font-medium text-gray-700">{{ __('files.upload_to', ['name' => $folder->name ?? __('files.heading_files')]) }}</label>
        <div class="mt-1"><x-file-dropzone id="file-general" /></div>
        <div class="mt-3"><x-tag-input name="tags" :suggestions="$tagSuggestions" /></div>
        <button type="submit" class="mt-3 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.upload') }}</button>
    </form>

    {{-- Attach a file to a customer or project --}}
    @if (! empty($targets))
        <form x-show="assign" x-cloak method="POST" action="{{ route('files.store') }}" enctype="multipart/form-data"
            class="mt-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            @csrf
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div x-data="selectCombobox(@js($targets), @js(old('attachable', '')))">
                    <label for="attachable" class="block text-sm font-medium text-gray-700">{{ __('files.assign_to') }}<span class="text-red-600"> *</span></label>
                    <input type="hidden" name="attachable" :value="selected">
                    <input type="text" id="attachable" x-model="query" @focus="open = true" @click="open = true"
                        placeholder="{{ __('files.search_target_placeholder') }}" autocomplete="off"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                    <div x-show="open" x-cloak @click.outside="open = false" class="relative">
                        <ul class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
                            <template x-for="option in filtered" :key="option.value">
                                <li @click="choose(option)" class="cursor-pointer px-3 py-2 hover:bg-gray-50" x-text="option.label"></li>
                            </template>
                        </ul>
                    </div>
                    @error('attachable')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div><x-file-dropzone id="file-assign" /></div>
            </div>
            <div class="mt-3"><x-tag-input name="tags" :suggestions="$tagSuggestions" /></div>
            <button type="submit" class="mt-3 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.upload') }}</button>
        </form>
    @endif

    {{-- Filters --}}
    <form method="GET" class="mt-6 flex flex-wrap items-end gap-3">
        @if ($folder)<input type="hidden" name="folder" value="{{ $folder->id }}">@endif
        <div>
            <label class="block text-xs font-medium text-gray-500">{{ __('files.search') }}</label>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('files.search_placeholder') }}"
                class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500">{{ __('files.type') }}</label>
            <select name="type" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <option value="">{{ __('files.type_all') }}</option>
                @foreach ($types as $t)
                    <option value="{{ $t['value'] }}" @selected($activeType === $t['value'])>{{ $t['label'] }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('files.filter') }}</button>
        @if (request()->hasAny(['q', 'type', 'tag']))
            <a href="{{ route('files.index', ['folder' => $folder?->id]) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('files.clear') }}</a>
        @endif
        @if ($activeTagName)
            <span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">{{ __('files.tag_label', ['name' => $activeTagName]) }}</span>
        @endif
    </form>

    {{-- Subfolders --}}
    @if ($subfolders->isNotEmpty())
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($subfolders as $sub)
                <div x-data="{ rename: false }" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <a href="{{ route('files.index', ['folder' => $sub->id]) }}" class="flex items-center gap-2 font-medium text-gray-900 hover:underline">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                            {{ $sub->name }}
                        </a>
                        <span class="text-xs text-gray-400">{{ $sub->files_count }}</span>
                    </div>
                    <div class="mt-2 flex items-center gap-3 text-xs">
                        <button type="button" @click="rename = ! rename" class="text-gray-500 hover:text-gray-700">{{ __('files.rename') }}</button>
                        <form method="POST" action="{{ route('folders.destroy', $sub) }}" onsubmit="return confirm('{{ __('files.delete_folder_confirm') }}');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800">{{ __('files.delete') }}</button>
                        </form>
                    </div>
                    <form x-show="rename" x-cloak method="POST" action="{{ route('folders.update', $sub) }}" class="mt-2 flex gap-2">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $sub->name }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Files --}}
    <div x-data="{ selected: [], allIds: {{ $files->pluck('id')->toJson() }}, toggleAll(e) { this.selected = e.target.checked ? [...this.allIds] : [] } }">
        {{-- Bulk action bar --}}
        <div x-show="selected.length" x-cloak class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
            <span class="text-sm font-medium text-gray-700"><span x-text="selected.length"></span> {{ __('files.selected_word') }}</span>
            <form method="POST" class="flex flex-wrap items-center gap-2">
                @csrf
                <template x-for="id in selected" :key="id"><input type="hidden" name="file_ids[]" :value="id"></template>
                <select name="folder_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <option value="">{{ __('files.root_folder') }}</option>
                    @foreach ($allFolders as $f)
                        <option value="{{ $f->id }}">{{ $f->name }}</option>
                    @endforeach
                </select>
                <button type="submit" formaction="{{ route('files.bulk.move') }}"
                    class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.move') }}</button>
                <button type="submit" formaction="{{ route('files.bulk.delete') }}"
                    onclick="return confirm('{{ __('files.bulk_delete_confirm') }}');"
                    class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('files.delete') }}</button>
            </form>
        </div>

        <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            @if ($files->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-gray-500">{{ $searching ? __('files.empty_no_match') : __('files.empty_folder') }}</p>
            @else
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3"><input type="checkbox" @change="toggleAll($event)" aria-label="{{ __('files.select_all') }}" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"></th>
                            <th class="px-4 py-3"><x-sortable-header column="name" :label="__('files.col_name')" :sort="$sort" :dir="$dir" /></th>
                            <th class="px-4 py-3"><x-sortable-header column="type" :label="__('files.col_type')" :sort="$sort" :dir="$dir" /></th>
                            <th class="px-4 py-3 text-right"><x-sortable-header column="size" :label="__('files.col_size')" :sort="$sort" :dir="$dir" /></th>
                            <th class="px-4 py-3">{{ __('files.col_location') }}</th>
                            <th class="px-4 py-3">{{ __('files.col_tags') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($files as $file)
                            <tr :class="selected.includes({{ $file->id }}) ? 'bg-gray-50' : ''">
                                <td class="px-4 py-3"><input type="checkbox" value="{{ $file->id }}" x-model.number="selected" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"></td>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    <a href="{{ route('files.show', $file) }}" class="hover:underline">{{ $file->displayTitle }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $file->type->label() }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ number_format($file->size / 1024, 0) }} KB</td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if ($file->attachable instanceof \App\Models\Customer)
                                        {{ __('files.location_customer', ['name' => $file->attachable->name]) }}
                                    @elseif ($file->attachable instanceof \App\Models\Project)
                                        {{ __('files.location_project', ['name' => $file->attachable->name]) }}
                                    @else
                                        {{ __('files.location_general') }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @foreach ($file->tags as $tag)
                                        <x-tag-chip :tag="$tag" :href="route('files.index', ['tag' => $tag->slug])" class="mr-1" />
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="mt-4">{{ $files->links() }}</div>
    </div>
  </div>
</x-layouts.app>
