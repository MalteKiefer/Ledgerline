<x-layouts.app :title="__('messages.nav.files')">
  <div x-data="filesExplorer({{ $files->pluck('id')->toJson() }}, {
        uploadUrl: '{{ route('files.store.general') }}',
        token: '{{ csrf_token() }}',
        folderId: {{ $folder?->id ?? 'null' }},
        customerId: {{ request('customer') ?: 'null' }},
        projectId: {{ request('project') ?: 'null' }},
     })" x-init="initDropzone()">

    {{-- Whole-window drop zone (folders with subfolders supported) --}}
    <div x-show="dragging" x-cloak @drop.prevent="drop($event)" @dragover.prevent
        class="fixed inset-0 z-[900] flex items-center justify-center bg-gray-900/50 p-8">
        <div class="rounded-2xl border-4 border-dashed border-white/80 px-16 py-24 text-center text-lg font-medium text-white">{{ __('files.drop_hint') }}</div>
    </div>

    {{-- Upload progress --}}
    <div x-show="uploading" x-cloak class="fixed inset-x-0 top-0 z-[950] bg-gray-800 px-4 py-2 text-center text-sm text-white">
        {{ __('files.uploading') }} <span x-text="progress.done"></span> / <span x-text="progress.total"></span>
    </div>

    {{-- Floating upload button on mobile --}}
    <label class="fixed bottom-6 right-5 z-30 flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-gray-800 text-3xl text-white shadow-lg hover:bg-gray-700 sm:hidden" aria-label="{{ __('files.upload') }}">
        +
        <input type="file" multiple class="hidden"
            @change="uploadFiles([...$event.target.files].map(f => ({ file: f, path: f.name })))">
    </label>

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <nav class="text-sm text-gray-500">
                <a href="{{ route('files.index') }}" class="hover:underline">{{ __('files.all_files') }}</a>
                @foreach ($breadcrumb as $crumb)
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('files.index', ['folder' => $crumb->id]) }}" class="hover:underline">{{ $crumb->name }}</a>
                @endforeach
            </nav>
            <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ $folder->name ?? __('messages.nav.files') }}</h1>
            @if ($recordFilter)
                <span class="mt-1 inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs text-blue-800">
                    {{ __('files.filtered_by') }}: {{ $recordFilter }}
                    <a href="{{ route('files.index') }}" class="text-blue-500 hover:text-blue-700">✕</a>
                </span>
            @endif
        </div>
        <div class="flex items-center gap-2" x-data="{ upload: false }">
            <button type="button" @click="upload = ! upload"
                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.upload') }}</button>

            {{-- Multi-file upload into the current folder --}}
            <div x-show="upload" x-cloak class="absolute right-4 z-30 mt-12 w-96 rounded-lg border border-gray-200 bg-white p-4 shadow-xl">
                <form method="POST" action="{{ route('files.store.general') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="folder_id" value="{{ $folder?->id }}">
                    @if (request('customer'))<input type="hidden" name="customer_id" value="{{ request('customer') }}">@endif
                    @if (request('project'))<input type="hidden" name="project_id" value="{{ request('project') }}">@endif
                    <label class="block text-sm font-medium text-gray-700">{{ __('files.upload_here') }}</label>
                    <input type="file" name="files[]" multiple required
                        class="mt-2 w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-gray-700">
                    <div class="mt-3"><x-tag-input name="tags" :suggestions="$tagSuggestions" /></div>
                    <button type="submit" class="mt-3 w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.upload') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-4">
        {{-- Sidebar: folder tree --}}
        <aside class="lg:col-span-1">
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                <p class="px-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('files.folders') }}</p>
                <ul class="mt-2 space-y-0.5">
                    <li>
                        <a href="{{ route('files.index') }}"
                            @class(['block rounded px-2 py-1 text-sm', 'bg-gray-100 font-medium text-gray-900' => ! $folder, 'text-gray-600 hover:bg-gray-50' => (bool) $folder])>
                            {{ __('files.all_files') }}
                        </a>
                    </li>
                    @include('files._tree', ['nodes' => $tree, 'current' => $folder])
                </ul>

                {{-- New folder --}}
                <form method="POST" action="{{ route('folders.store') }}" class="mt-3 flex gap-2 border-t border-gray-100 pt-3">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $folder?->id }}">
                    <input type="text" name="name" required placeholder="{{ __('files.new_folder') }}"
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <button type="submit" class="rounded-md bg-gray-800 px-3 text-sm font-medium text-white hover:bg-gray-700">+</button>
                </form>
                @error('name')<p class="mt-1 px-2 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </aside>

        {{-- Main --}}
        <div class="lg:col-span-3">
            {{-- Filters --}}
            <form method="GET" class="flex flex-wrap items-end gap-3">
                @if ($folder)<input type="hidden" name="folder" value="{{ $folder->id }}">@endif
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('files.search') ?? 'Search' }}</label>
                    <input type="search" name="q" value="{{ request('q') }}"
                        class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('files.col_type') }}</label>
                    <select name="type" class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <option value="">—</option>
                        @foreach ($types as $t)
                            <option value="{{ $t['value'] }}" @selected($activeType === $t['value'])>{{ $t['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('files.filter') ?? 'Filter' }}</button>
                @if (request()->hasAny(['q', 'type', 'tag', 'customer', 'project']))
                    <a href="{{ route('files.index', ['folder' => $folder?->id]) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('files.clear_filter') }}</a>
                @endif
                @if ($activeTagName)<span class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">#{{ $activeTagName }}</span>@endif
            </form>

            {{-- Bulk bar --}}
            <div x-show="selected.length" x-cloak class="mt-4 flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                <span class="text-sm font-medium text-gray-700"><span x-text="selected.length"></span> {{ __('files.selected_word') }}</span>
                <button type="button" @click="openMove()" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.move') }}</button>
                <button type="button" @click="openBulkDelete()" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('common.delete') }}</button>
            </div>

            {{-- Subfolders (folder browsing only) --}}
            @if ($subfolders->isNotEmpty())
                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($subfolders as $sub)
                        <div x-data="{ rename: false, menu: false }" class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm">
                            <a href="{{ route('files.index', ['folder' => $sub->id]) }}" class="flex min-w-0 items-center gap-2 text-sm font-medium text-gray-900 hover:underline">
                                <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                                <span x-show="! rename" class="truncate">{{ $sub->name }}</span>
                            </a>
                            <form x-show="rename" x-cloak method="POST" action="{{ route('folders.update', $sub) }}" class="flex flex-1 gap-2 px-2">
                                @csrf @method('PUT')
                                <input type="text" name="name" value="{{ $sub->name }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <button type="submit" class="rounded-md bg-gray-800 px-3 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                            </form>
                            <div class="relative shrink-0">
                                <button type="button" @click="menu = ! menu" @keydown.escape="menu = false" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="{{ __('files.actions') }}">⋯</button>
                                <div x-show="menu" x-cloak @click.outside="menu = false" class="absolute right-0 z-20 mt-1 w-40 rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
                                    <button type="button" @click="rename = true; menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.rename') }}</button>
                                    <x-confirm-action :action="route('folders.destroy', $sub)" method="DELETE"
                                        :trigger="__('common.delete')" trigger-class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-gray-50"
                                        :message="__('files.delete_folder_confirm')" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Files --}}
            <div class="mt-4 overflow-visible rounded-lg border border-gray-200 bg-white shadow-sm">
                @if ($files->isEmpty())
                    <p class="px-4 py-10 text-center text-sm text-gray-500">{{ $filtering ? __('files.empty_no_match') : __('files.empty_explorer') }}</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3"><input type="checkbox" @change="toggleAll($event)" aria-label="{{ __('files.select_all') }}" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"></th>
                                <th class="px-4 py-3"><x-sortable-header column="name" :label="__('files.col_name')" :sort="$sort" :dir="$dir" /></th>
                                <th class="px-4 py-3"><x-sortable-header column="type" :label="__('files.col_type')" :sort="$sort" :dir="$dir" /></th>
                                <th class="px-4 py-3 text-right"><x-sortable-header column="size" :label="__('files.col_size')" :sort="$sort" :dir="$dir" /></th>
                                <th class="px-4 py-3">{{ __('files.col_location') }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($files as $file)
                                <tr x-data="{ menu: false }" :class="selected.includes({{ $file->id }}) ? 'bg-gray-50' : ''">
                                    <td class="px-4 py-3"><input type="checkbox" value="{{ $file->id }}" x-model.number="selected" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"></td>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <a x-show="renaming !== {{ $file->id }}" href="{{ route('files.show', $file) }}" class="hover:underline">{{ $file->displayTitle }}</a>
                                        <form x-show="renaming === {{ $file->id }}" x-cloak method="POST" action="{{ route('files.rename', $file) }}" class="flex gap-2">
                                            @csrf @method('PUT')
                                            <input type="text" name="title" value="{{ $file->displayTitle }}" x-ref="rename-{{ $file->id }}"
                                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                            <button type="submit" class="rounded-md bg-gray-800 px-3 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                                            <button type="button" @click="renaming = null" class="text-sm text-gray-500">✕</button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $file->type->label() }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($file->size / 1024, 0) }} KB</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        @if ($file->attachable instanceof \App\Models\Customer)
                                            <a href="{{ route('files.index', ['customer' => $file->attachable->id]) }}" class="hover:underline">{{ __('files.location_customer', ['name' => $file->attachable->name]) }}</a>
                                        @elseif ($file->attachable instanceof \App\Models\Project)
                                            <a href="{{ route('files.index', ['project' => $file->attachable->id]) }}" class="hover:underline">{{ __('files.location_project', ['name' => $file->attachable->name]) }}</a>
                                        @elseif ($file->attachable instanceof \App\Models\Invoice)
                                            <a href="{{ route('finance.invoices.show', $file->attachable) }}" class="hover:underline">{{ __('files.location_invoice', ['number' => $file->attachable->number ?? ('#'.$file->attachable->id)]) }}</a>
                                        @else
                                            {{ __('files.location_general') }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="relative inline-block text-left">
                                            <button type="button" @click="menu = ! menu" @keydown.escape="menu = false" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="{{ __('files.actions') }}">⋯</button>
                                            <div x-show="menu" x-cloak @click.outside="menu = false" class="absolute right-0 z-20 mt-1 w-40 rounded-md border border-gray-200 bg-white py-1 text-left text-sm shadow-lg">
                                                <a href="{{ route('files.show', $file) }}" class="block px-3 py-1.5 text-gray-700 hover:bg-gray-50">{{ __('files.view') }}</a>
                                                <button type="button" @click="startRename({{ $file->id }}); menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.rename') }}</button>
                                                <button type="button" @click="openMove({{ $file->id }}); menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.move') }}</button>
                                                <x-confirm-action :action="route('files.destroy', $file)" method="DELETE"
                                                    :trigger="__('common.delete')" trigger-class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-gray-50"
                                                    :message="$file->attachable instanceof \App\Models\Invoice ? __('files.delete_invoice_warning', ['number' => $file->attachable->number ?? ('#'.$file->attachable->id)]) : __('files.delete_file_confirm')" />
                                            </div>
                                        </div>
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

    {{-- Move modal (shared: single row or selection) --}}
    <template x-teleport="body">
        <div x-show="moveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="moveOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="moveOpen = false"></div>
            <div class="relative flex max-h-[80vh] w-full max-w-md flex-col rounded-lg bg-white shadow-xl">
                <h3 class="border-b border-gray-100 px-6 py-4 text-base font-semibold text-gray-900">{{ __('files.move_title') }} <span class="text-gray-400">(<span x-text="moveIds.length"></span>)</span></h3>
                <form method="POST" action="{{ route('files.bulk.move') }}" class="flex min-h-0 flex-1 flex-col" x-data="{ radioName: 'folder_pick' }">
                    @csrf
                    <template x-for="id in moveIds" :key="id"><input type="hidden" name="file_ids[]" :value="id"></template>
                    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50">
                            <input type="radio" :name="radioName" value="" x-model="target" class="border-gray-300 text-gray-800 focus:ring-gray-500">
                            {{ __('files.root_folder') }}
                        </label>
                        @include('files._tree_select', ['nodes' => $tree, 'depth' => 0])
                    </div>
                    <input type="hidden" name="folder_id" :value="target">
                    <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                        <button type="button" @click="moveOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.move_here') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    {{-- Bulk delete modal --}}
    <template x-teleport="body">
        <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="deleteOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ __('files.bulk_delete_confirm') }}</p>
                <form method="POST" action="{{ route('files.bulk.delete') }}" class="mt-5 flex justify-end gap-3">
                    @csrf
                    <template x-for="id in selected" :key="id"><input type="hidden" name="file_ids[]" :value="id"></template>
                    <button type="button" @click="deleteOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('common.delete') }}</button>
                </form>
            </div>
        </div>
    </template>
  </div>
</x-layouts.app>
