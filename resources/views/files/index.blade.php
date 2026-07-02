<x-layouts.app :title="__('messages.nav.files')">
  <div x-data="filesExplorer({{ $files->pluck('id')->toJson() }}, {
        uploadUrl: '{{ route('files.store.general') }}',
        conflictsUrl: '{{ route('files.conflicts') }}',
        token: '{{ csrf_token() }}',
        folderId: {{ $folder?->id ?? 'null' }},
        customerId: {{ (int) request('customer') ?: 'null' }},
        projectId: {{ (int) request('project') ?: 'null' }},
     })" x-init="initDropzone()">

    {{-- Whole-window drop zone (folders with subfolders supported) --}}
    <div x-show="dragging" x-cloak @drop.prevent="drop($event)" @dragover.prevent
        class="fixed inset-0 z-[900] flex items-center justify-center bg-gray-900/50 p-8">
        <div class="rounded-2xl border-4 border-dashed border-white/80 px-16 py-24 text-center text-lg font-medium text-white">{{ __('files.drop_hint') }}</div>
    </div>

    {{-- Upload tray (Google/Immich style) --}}
    <div x-show="uploading" x-cloak class="fixed bottom-5 right-5 z-[950] w-80 rounded-lg border border-gray-200 bg-white shadow-xl">
        <div class="border-b border-gray-100 px-4 py-2 text-sm font-medium text-gray-700">
            {{ __('files.uploading') }} (<span x-text="progress.done"></span>/<span x-text="progress.total"></span>)
        </div>
        <div class="max-h-64 space-y-1 overflow-y-auto p-3">
            <template x-for="(item, i) in uploadItems" :key="i">
                <div class="flex items-center justify-between gap-2 text-xs">
                    <span class="truncate text-gray-700" x-text="item.name"></span>
                    <span class="shrink-0" :class="item.done ? 'text-green-600' : 'text-gray-400'" x-text="item.done ? '✓' : '…'"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- Floating upload button on mobile --}}
    <label class="fixed bottom-6 right-5 z-30 flex h-14 w-14 cursor-pointer items-center justify-center rounded-full bg-gray-800 text-3xl text-white shadow-lg hover:bg-gray-700 sm:hidden" aria-label="{{ __('files.upload') }}">
        +
        <input type="file" multiple class="hidden"
            @change="startUpload([...$event.target.files].map(f => ({ file: f, path: f.name })))">
    </label>

    {{-- Same-name conflict dialog: overwrite / rename / skip --}}
    <div x-show="conflictOpen" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="conflictOpen = false">
        <div class="absolute inset-0 bg-gray-900/40" @click="conflictOpen = false"></div>
        <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="text-base font-semibold text-gray-900">{{ __('files.conflict_title') }}</h3>
            <p class="mt-2 text-sm text-gray-600"><span x-text="conflictCount"></span> {{ __('files.conflict_message') }}</p>
            <div class="mt-5 flex flex-col gap-2">
                <button type="button" @click="resolveConflict('overwrite')" class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('files.conflict_overwrite') }}</button>
                <button type="button" @click="resolveConflict('rename')" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.conflict_rename') }}</button>
                <button type="button" @click="resolveConflict('skip')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('files.conflict_skip') }}</button>
            </div>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <nav class="text-sm text-gray-500">
                <a href="{{ route('files.index') }}" class="hover:underline">{{ __('files.all_files') }}</a>
                @foreach ($breadcrumb as $crumb)
                    <span aria-hidden="true">/</span>
                    @if ($crumb->enc_name)
                        <a href="{{ route('files.index', ['folder' => $crumb->id]) }}" class="hover:underline" x-data="encName(@js($crumb->enc_name))" x-text="label"></a>
                    @else
                        <a href="{{ route('files.index', ['folder' => $crumb->id]) }}" class="hover:underline">{{ $crumb->name }}</a>
                    @endif
                @endforeach
            </nav>
            @if ($folder?->enc_name)
                <h1 class="mt-1 text-2xl font-semibold text-gray-900" x-data="encName(@js($folder->enc_name))" x-text="label"></h1>
            @else
                <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ $folder->name ?? __('messages.nav.files') }}</h1>
            @endif
            @if ($recordFilter)
                <span class="mt-1 inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs text-blue-800">
                    {{ __('files.filtered_by') }}: {{ $recordFilter }}
                    <a href="{{ route('files.index') }}" class="text-blue-500 hover:text-blue-700">✕</a>
                </span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @include('vault._panel')
            {{-- New folder --}}
            <form method="POST" action="{{ route('folders.store') }}" class="flex items-center gap-1" @submit="window.encryptFolderSubmit($event)">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $folder?->id }}">
                <input type="text" name="name" required placeholder="{{ __('files.new_folder') }}"
                    class="w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <button type="submit" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">+</button>
            </form>
            {{-- Upload (files or folders); progress shows in the tray below --}}
            <label class="hidden cursor-pointer rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 sm:inline-flex">
                {{ __('files.upload') }}
                <input type="file" multiple class="hidden"
                    @change="startUpload([...$event.target.files].map(f => ({ file: f, path: f.name })))">
            </label>
        </div>
    </div>

    <div class="mt-6">
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

            {{-- Browser: folders first, then files (all alphabetical) --}}
            <div class="mt-4 overflow-visible rounded-lg border border-gray-200 bg-white shadow-sm">
                @if ($files->isEmpty() && $subfolders->isEmpty())
                    <p class="px-4 py-10 text-center text-sm text-gray-500">{{ $filtering ? __('files.empty_no_match') : __('files.empty_explorer') }}</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3"><input type="checkbox" @change="toggleAll($event)" aria-label="{{ __('files.select_all') }}" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"></th>
                                <th class="px-4 py-3"><x-sortable-header column="name" :label="__('files.col_name')" :sort="$sort" :dir="$dir" /></th>
                                <th class="hidden px-4 py-3 sm:table-cell"><x-sortable-header column="type" :label="__('files.col_type')" :sort="$sort" :dir="$dir" /></th>
                                <th class="hidden px-4 py-3 text-right sm:table-cell"><x-sortable-header column="size" :label="__('files.col_size')" :sort="$sort" :dir="$dir" /></th>
                                <th class="hidden px-4 py-3 md:table-cell">{{ __('files.col_location') }}</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" x-data="folderSort(@js($sort), @js($dir))" x-init="run()">
                            {{-- Folders first --}}
                            @foreach ($subfolders as $sub)
                                <tr x-data="{{ $sub->enc_name ? 'encFolderRow('.\Illuminate\Support\Js::from($sub->enc_name).')' : '{ rename: false, menu: false }' }}" class="hover:bg-gray-50"
                                    data-kind="folder" data-name="{{ $sub->enc_name ? '' : $sub->name }}" @if ($sub->enc_name) data-enc="{{ $sub->enc_name }}" @endif>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        <a x-show="! rename" href="{{ route('files.index', ['folder' => $sub->id]) }}" class="flex items-center gap-2 hover:underline">
                                            <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                                            @if ($sub->enc_name)<span x-text="folderName"></span>@else{{ $sub->name }}@endif
                                        </a>
                                        <form x-show="rename" x-cloak method="POST" action="{{ route('folders.update', $sub) }}" class="flex gap-2" @submit="window.encryptFolderSubmit($event)">
                                            @csrf @method('PUT')
                                            <input type="text" name="name" @if ($sub->enc_name) :value="folderName" @else value="{{ $sub->name }}" @endif class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                            <button type="submit" class="rounded-md bg-gray-800 px-3 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                                            <button type="button" @click="rename = false" class="text-sm text-gray-500">✕</button>
                                        </form>
                                    </td>
                                    <td class="hidden px-4 py-3 text-gray-600 sm:table-cell">{{ __('files.folder') }}</td>
                                    <td class="hidden px-4 py-3 text-right text-gray-400 sm:table-cell">{{ $sub->files_count }}</td>
                                    <td class="hidden px-4 py-3 md:table-cell"></td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="relative inline-block text-left">
                                            <button type="button" @click="menu = ! menu" @keydown.escape="menu = false" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="{{ __('files.actions') }}">⋯</button>
                                            <div x-show="menu" x-cloak @click.outside="menu = false" class="absolute right-0 z-20 mt-1 w-40 rounded-md border border-gray-200 bg-white py-1 text-left text-sm shadow-lg">
                                                <button type="button" @click="rename = true; menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.rename') }}</button>
                                                <x-confirm-action :action="route('folders.destroy', $sub)" method="DELETE"
                                                    :trigger="__('common.delete')" trigger-class="block w-full px-3 py-1.5 text-left text-red-600 hover:bg-gray-50"
                                                    :message="__('files.delete_folder_confirm')" />
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            @foreach ($files as $file)
                                <tr x-data="{ menu: false }" :class="selected.includes({{ $file->id }}) ? 'bg-gray-50' : ''"
                                    data-kind="file" data-name="{{ $file->is_encrypted ? '' : $file->displayTitle }}" @if ($file->is_encrypted) data-enc="{{ $file->enc_metadata }}" @endif>
                                    <td class="px-4 py-3"><input type="checkbox" value="{{ $file->id }}" x-model.number="selected" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"></td>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        @if ($file->is_encrypted)
                                            <a href="{{ route('files.show', $file) }}" class="flex items-center gap-1 hover:underline"
                                                x-data="encName(@js($file->enc_metadata), @js('🔒 '.__('files.encrypted')))" x-text="label"></a>
                                        @else
                                            <a x-show="renaming !== {{ $file->id }}" href="{{ route('files.show', $file) }}" class="hover:underline">{{ $file->displayTitle }}</a>
                                            <form x-show="renaming === {{ $file->id }}" x-cloak method="POST" action="{{ route('files.rename', $file) }}" class="flex gap-2">
                                                @csrf @method('PUT')
                                                <input type="text" name="title" value="{{ $file->displayTitle }}" x-ref="rename-{{ $file->id }}"
                                                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                                <button type="submit" class="rounded-md bg-gray-800 px-3 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                                                <button type="button" @click="renaming = null" class="text-sm text-gray-500">✕</button>
                                            </form>
                                        @endif
                                    </td>
                                    <td class="hidden px-4 py-3 text-gray-600 sm:table-cell">{{ $file->type->label() }}</td>
                                    <td class="hidden px-4 py-3 text-right text-gray-600 sm:table-cell">{{ number_format($file->size / 1024, 0) }} KB</td>
                                    <td class="hidden px-4 py-3 text-gray-600 md:table-cell">
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
                                                <a href="{{ route('files.edit', $file) }}" class="block px-3 py-1.5 text-gray-700 hover:bg-gray-50">{{ __('files.edit') }}</a>
                                                @unless ($file->is_encrypted)
                                                    <button type="button" @click="startRename({{ $file->id }}); menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.rename') }}</button>
                                                @endunless
                                                <button type="button" @click="openMove({{ $file->id }}); menu = false" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.move') }}</button>
                                                @unless ($file->is_encrypted)
                                                    <button type="button" x-show="$store.vault.configured" @click="window.vaultEncrypt(@js(route('files.download', $file)), @js(route('files.encrypt', $file)), @js($file->name), @js($file->mime_type), @js(csrf_token())); menu = false"
                                                        class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.encrypt_file') }}</button>
                                                @endunless
                                                @if ($file->type === \App\Enums\FileType::ARCHIVE)
                                                    <form method="POST" action="{{ route('files.extract', $file) }}">
                                                        @csrf
                                                        <button type="submit" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50">{{ __('files.extract') }}</button>
                                                    </form>
                                                @endif
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
