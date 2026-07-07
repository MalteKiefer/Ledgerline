<x-layouts.app :title="__('messages.nav.files')">
  @php
      $typeLabels = collect(\App\Enums\FileType::cases())
          ->mapWithKeys(fn (\App\Enums\FileType $c): array => [$c->value => $c->label()]);
  @endphp
  <div x-data="vaultFiles({
        dataUrl: '{{ url('/files/data') }}',
        uploadUrl: '{{ url('/files/upload') }}',
        rawBase: '{{ url('/files/raw') }}',
        thumbBase: '{{ url('/files/thumb') }}',
        searchContentUrl: '{{ url('/files/search-content') }}',
        blobBase: '{{ url('/files/blob') }}',
        versionsBase: '{{ url('/files') }}',
        archiveUrl: '{{ url('/files/archive') }}',
        trashUrl: '{{ url('/files/trash') }}',
        restoreUrl: '{{ url('/files/restore') }}',
        duplicateUrl: '{{ url('/files/duplicate') }}',
        bulkRenameUrl: '{{ url('/files/bulk-rename') }}',
        favoriteUrl: '{{ url('/files/favorite') }}',
        publicLinkBase: '{{ url('/files/public-link') }}',
        token: '{{ csrf_token() }}',
     }, {
        archivedToast: @js(__('files.archived_toast')),
        extractedToast: @js(__('files.extracted_toast')),
        archiveFailed: @js(__('files.archive_failed')),
        extractFailed: @js(__('files.extract_failed')),
        linkCopied: @js(__('files.link_copied')),
        types: @js($typeLabels),
        stale: @js(__('files.vault_stale')),
        saveFailed: @js(__('files.save_failed')),
        uploadFailed: @js(__('files.upload_failed')),
        downloadFailed: @js(__('files.download_failed')),
        largeZip: @js(__('files.large_zip_confirm')),
        exportUrl: '{{ url('/files/export') }}',
        exportQueued: @js(__('downloads.queued_toast')),
        downloadsUrl: '{{ url('/downloads') }}',
        rootFolder: @js(__('files.all_files')),
        migrateFailed: @js(__('files.migrate_failed')),
        restoreConfirm: @js(__('files.version_restore_confirm')),
        quotaExceeded: @js(__('files.quota_exceeded')),
        purgeConfirm: @js(__('files.purge_confirm')),
        emptyTrashConfirm: @js(__('files.empty_trash_confirm')),
     })">

    {{-- Whole-window drop zone (folders with subfolders supported) --}}
    <div x-show="dragging && state === 'ready'" x-cloak @drop.prevent="drop($event)" @dragover.prevent
        class="fixed inset-0 z-[900] flex items-center justify-center bg-gray-900/50 p-8">
        <div class="rounded-2xl border-4 border-dashed border-white/80 px-16 py-24 text-center text-lg font-medium text-white">{{ __('files.drop_hint') }}</div>
    </div>

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-6 text-center text-sm text-red-700 dark:text-red-300">{{ __('files.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div class="flex flex-col gap-4 md:flex-row">
        {{-- Sidebar: mobile trigger + desktop rail + slide-over (like calendar/contacts) --}}
        <div class="md:hidden">
            <button type="button" @click="$store.nav.toggleSidebar()"
                class="flex min-h-11 w-full items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm">
                <x-icon name="bars-3" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                <span x-text="({files:@js(__('files.all_files')),favorites:@js(__('files.favorites')),recent:@js(__('files.recent')),trash:@js(__('files.trash'))})[view]"></span>
            </button>
        </div>
        <aside class="hidden w-full shrink-0 space-y-4 self-start rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-sm md:block md:w-56">
            @include('files._sidebar_content')
        </aside>
        <x-sheet side="left" store="sidebarOpen" :title="__('messages.nav.files')">
            <div class="space-y-4">@include('files._sidebar_content')</div>
        </x-sheet>

        {{-- Main --}}
        <div class="min-w-0 flex-1">
        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <nav class="text-sm text-gray-500 dark:text-gray-400" x-show="view === 'files'">
                    <button type="button" @click="cwd = null" class="hover:underline">{{ __('files.all_files') }}</button>
                    <template x-for="crumb in breadcrumb" :key="crumb.id">
                        <span>
                            <span aria-hidden="true">/</span>
                            <button type="button" @click="cwd = crumb.id" class="hover:underline" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100" x-text="view === 'files' ? (currentFolderName ?? @js(__('messages.nav.files'))) : ({favorites:@js(__('files.favorites')),recent:@js(__('files.recent')),trash:@js(__('files.trash'))})[view]"></h1>
            </div>
            {{-- Browser actions (hidden in the trash view); empty-trash shown there --}}
            <div class="flex flex-wrap items-center gap-2">
                <template x-if="view === 'files'">
                    <div class="flex flex-wrap items-center gap-2">
                        {{-- New folder --}}
                        <form class="flex items-center gap-1" @submit.prevent="mkdir($refs.newFolder.value); $refs.newFolder.value = ''">
                            <input type="text" x-ref="newFolder" required placeholder="{{ __('files.new_folder') }}"
                                class="w-full sm:w-40 rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            <button type="submit" title="{{ __('files.new_folder') }}" aria-label="{{ __('files.new_folder') }}"
                                class="rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="folder-plus" class="h-5 w-5" /></button>
                        </form>
                        {{-- Upload --}}
                        <label title="{{ __('files.upload') }}" aria-label="{{ __('files.upload') }}"
                            class="cursor-pointer rounded-md bg-gray-800 p-2 text-white hover:bg-gray-700">
                            <x-icon name="arrow-up-tray" class="h-5 w-5" />
                            <input type="file" multiple class="hidden" @change="upload($event.target.files); $event.target.value = ''">
                        </label>
                    </div>
                </template>
                <template x-if="trashView && trashCount > 0">
                    <button type="button" @click="emptyTrash()" class="inline-flex items-center gap-1.5 rounded-md border border-red-300 dark:border-red-800 px-3 py-2 text-sm font-medium text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950">
                        <x-icon name="trash" class="h-4 w-4" />{{ __('files.empty_trash') }}
                    </button>
                </template>
            </div>
        </div>

        {{-- Search (client-side, over the decrypted manifest) + sort --}}
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <input type="search" x-model="query" placeholder="{{ __('files.search') }}"
                class="w-full sm:w-64 rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            <div class="flex items-center gap-1 text-sm">
                <select x-model="sortKey" aria-label="{{ __('files.sort_by') }}" class="rounded-md border-gray-300 dark:border-gray-700 py-1.5 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <option value="name">{{ __('files.sort_name') }}</option>
                    <option value="size">{{ __('files.sort_size') }}</option>
                    <option value="date">{{ __('files.sort_date') }}</option>
                </select>
                <button type="button" @click="sortDir = sortDir === 'asc' ? 'desc' : 'asc'" :title="sortDir === 'asc' ? @js(__('files.sort_asc')) : @js(__('files.sort_desc'))" class="rounded-md border border-gray-300 dark:border-gray-700 p-1.5 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <span x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                </button>
            </div>
            {{-- List / grid toggle --}}
            <div class="flex items-center gap-0.5 rounded-md border border-gray-300 dark:border-gray-700 p-0.5">
                <button type="button" @click="setLayout('list')" :class="layout === 'list' ? 'bg-gray-800 text-white' : 'text-gray-500 dark:text-gray-400'" title="{{ __('files.view_list') }}" aria-label="{{ __('files.view_list') }}" class="rounded p-1.5"><x-icon name="bars-3" class="h-4 w-4" /></button>
                <button type="button" @click="setLayout('grid')" :class="layout === 'grid' ? 'bg-gray-800 text-white' : 'text-gray-500 dark:text-gray-400'" title="{{ __('files.view_grid') }}" aria-label="{{ __('files.view_grid') }}" class="rounded p-1.5"><x-icon name="squares-2x2" class="h-4 w-4" /></button>
            </div>
            <span x-show="activeTag" x-cloak class="inline-flex items-center gap-2 rounded-full bg-blue-50 dark:bg-blue-950 px-3 py-1 text-xs text-blue-800 dark:text-blue-300">
                {{ __('files.filtered_by') }}: <span x-text="activeTag"></span>
                <button type="button" @click="activeTag = ''" class="text-blue-500 hover:text-blue-700"><x-icon name="x-mark" class="h-3 w-3" /></button>
            </span>
        </div>

        <p x-show="error" x-cloak class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800" x-text="error"></p>

        {{-- Browser --}}
        <div class="mt-4 overflow-visible rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
            <template x-if="rows.length === 0">
                <p class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400" x-text="trashView ? @js(__('files.trash_empty')) : @js(__('files.empty_explorer'))"></p>
            </template>
            {{-- Grid view: image thumbnails, icon fallback --}}
            <template x-if="layout === 'grid' && rows.length > 0">
                <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    <template x-for="row in rows" :key="row.kind + row.id">
                        <div class="group relative flex flex-col overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700"
                            :draggable="row.kind !== 'folder' || view === 'files' ? 'true' : 'false'"
                            @dragstart="dragItem = { kind: row.kind, id: row.id }" @dragend="dragItem = null"
                            @dragover.prevent="row.kind === 'folder' && dragItem && $event.currentTarget.classList.add('ring-2','ring-gray-400')"
                            @dragleave="$event.currentTarget.classList.remove('ring-2','ring-gray-400')"
                            @drop.prevent="$event.currentTarget.classList.remove('ring-2','ring-gray-400'); if (row.kind === 'folder' && dragItem) { dropInto(row.id); dragItem = null; }">
                            <button type="button" @click="row.kind === 'folder' ? (cwd = row.id) : openFile(row)" class="flex aspect-square items-center justify-center bg-gray-50 dark:bg-gray-800">
                                <template x-if="isImage(row)">
                                    <img :src="thumbUrl(row)" loading="lazy" alt="" class="h-full w-full object-cover" x-on:error="$event.target.style.display='none'">
                                </template>
                                <template x-if="! isImage(row)">
                                    <span class="text-gray-400 dark:text-gray-500">
                                        <template x-if="row.kind === 'folder'"><span><x-icon name="folder" class="h-10 w-10" /></span></template>
                                        <template x-if="row.kind !== 'folder'"><span><x-icon name="document-text" class="h-10 w-10" /></span></template>
                                    </span>
                                </template>
                            </button>
                            <div class="flex items-center gap-1 px-2 py-1.5">
                                <button type="button" x-show="row.kind === 'file'" @click="toggleFavorite(row)" class="shrink-0" :class="row.favorite ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600 hover:text-gray-500'" :aria-label="row.favorite ? @js(__('files.unfavorite')) : @js(__('files.favorite'))">
                                    <span x-show="row.favorite"><x-icon name="star-solid" class="h-3.5 w-3.5" /></span>
                                    <span x-show="! row.favorite"><x-icon name="star" class="h-3.5 w-3.5" /></span>
                                </button>
                                <span class="min-w-0 flex-1 truncate text-xs text-gray-700 dark:text-gray-300" :title="row.name" x-text="row.name"></span>
                                <div class="relative shrink-0" x-data="{ menu: false, menuStyle: '', toggleMenu(e) { this.menu = ! this.menu; if (this.menu) { const r = e.currentTarget.getBoundingClientRect(); this.menuStyle = `top: ${r.bottom + 4}px; left: ${Math.max(8, r.right - 176)}px;`; } } }">
                                    <button type="button" @click="toggleMenu($event)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" :aria-label="@js(__('files.actions'))"><x-icon name="ellipsis" class="h-4 w-4" /></button>
                                    <template x-teleport="body">
                                        <div x-show="menu" x-cloak @click.outside="menu = false" @scroll.window="menu = false" :style="menuStyle" class="fixed z-[60] w-44 rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-1 text-left text-sm shadow-lg">
                                            <button type="button" x-show="row.kind === 'file'" @click="download(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="arrow-down-tray" />{{ __('files.download') }}</button>
                                            <button type="button" @click="openInfo(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="info" />{{ __('files.info') }}</button>
                                            <button type="button" @click="startRename(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="pencil" />{{ __('files.rename') }}</button>
                                            <button type="button" @click="openMove(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="arrows-right-left" />{{ __('files.move') }}</button>
                                            <button type="button" @click="duplicate(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="document-duplicate" />{{ __('files.duplicate') }}</button>
                                            <button type="button" x-show="isZip(row)" @click="extractArchive(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="folder-plus" />{{ __('files.extract_here') }}</button>
                                            <button type="button" @click="confirmDelete(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="trash" />{{ __('common.delete') }}</button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <div x-show="layout === 'list'" class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <table x-show="rows.length > 0" class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3"><input type="checkbox" @change="toggleAll($event)" aria-label="{{ __('files.select_all') }}" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"></th>
                        <th class="px-4 py-3">
                            <button type="button" @click="sortBy('name')" class="uppercase hover:text-gray-700 dark:hover:text-gray-300">
                                {{ __('files.col_name') }} <span x-text="sortArrow('name')"></span>
                            </button>
                        </th>
                        <th class="hidden px-4 py-3 sm:table-cell">{{ __('files.col_type') }}</th>
                        <th class="hidden px-4 py-3 text-right sm:table-cell">
                            <button type="button" @click="sortBy('size')" class="uppercase hover:text-gray-700 dark:hover:text-gray-300">
                                {{ __('files.col_size') }} <span x-text="sortArrow('size')"></span>
                            </button>
                        </th>
                        <th class="hidden px-4 py-3 md:table-cell">{{ __('files.col_tags') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    {{-- Parent-folder shortcut, like "cd .." — virtual row, never
                         part of rows(), so it is excluded from selection, actions
                         and export. Also a drop target to move items up. --}}
                    <template x-if="view === 'files' && cwd !== null && query === '' && activeTag === ''">
                        <tr class="cursor-pointer text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800" @click="cwd = parentFolderId"
                            @dragover="if (dragItem) $event.preventDefault()" @drop.prevent="dropInto(parentFolderId)">
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 font-medium">
                                <span class="flex items-center gap-2">
                                    <svg class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                                    ..
                                </span>
                            </td>
                            <td class="hidden px-4 py-3 sm:table-cell"></td>
                            <td class="hidden px-4 py-3 text-right sm:table-cell"></td>
                            <td class="hidden px-4 py-3 md:table-cell"></td>
                            <td class="px-4 py-3"></td>
                        </tr>
                    </template>
                    <template x-for="row in rows" :key="row.kind + row.id">
                        <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800" x-data="{ menu: false, menuStyle: '', toggleMenu(e) { this.menu = ! this.menu; if (this.menu) { const r = e.currentTarget.getBoundingClientRect(); const left = Math.max(8, r.right - 176); this.menuStyle = `top: ${r.bottom + 4}px; left: ${left}px;`; } } }"
                            :draggable="renaming === row.id ? 'false' : 'true'"
                            @dragstart.stop="onDragStart($event, row)" @dragend="onDragEnd()"
                            @dragover="if (row.kind === 'folder' && dragItem && !(dragItem.kind === 'folder' && dragItem.id === row.id)) $event.preventDefault()"
                            @drop.prevent="row.kind === 'folder' && dropInto(row.id)"
                            @click="if (renaming !== row.id) { row.kind === 'folder' ? cwd = row.id : openFile(row) }">
                            <td class="px-4 py-3" @click.stop><input type="checkbox" :value="rowKey(row)" x-model="selected" class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                <span class="flex min-w-0 items-center gap-2" x-show="renaming !== row.id">
                                    <svg x-show="row.kind === 'folder'" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                                    <svg x-show="row.kind === 'file'" class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="fileIconPath(row)" /></svg>
                                    <span class="truncate" x-text="row.name"></span>
                                </span>
                                <form x-show="renaming === row.id" x-cloak class="flex gap-2" @click.stop @submit.prevent="applyRename(row)">
                                    <input type="text" x-model="renameValue" x-ref="rename"
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                    <button type="submit" class="rounded-md bg-gray-800 px-3 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                                    <button type="button" @click="renaming = null" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="x-mark" /></button>
                                </form>
                            </td>
                            <td class="hidden px-4 py-3 text-gray-600 dark:text-gray-400 sm:table-cell" x-text="row.kind === 'folder' ? @js(__('files.folder')) : typeLabel(row)"></td>
                            <td class="hidden px-4 py-3 text-right text-gray-600 dark:text-gray-400 sm:table-cell" x-text="row.kind === 'folder' ? '—' : fmtSize(row.size)"></td>
                            <td class="hidden px-4 py-3 md:table-cell" @click.stop>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="tag in (row.tags ?? [])" :key="tag">
                                        <button type="button" @click="activeTag = tag"
                                            class="inline-flex items-center rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-200" x-text="tag"></button>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right" @click.stop>
                                {{-- Trash view: restore / delete-forever only --}}
                                <div x-show="trashView" class="flex items-center justify-end gap-1">
                                    <button type="button" @click="restore(row)" title="{{ __('files.restore') }}" aria-label="{{ __('files.restore') }}" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
                                    <button type="button" @click="purge(row)" title="{{ __('files.delete_forever') }}" aria-label="{{ __('files.delete_forever') }}" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950"><x-icon name="trash" class="h-4 w-4" /></button>
                                </div>
                                <div x-show="! trashView" class="flex items-center justify-end gap-1">
                                    {{-- Quick actions (icon-only): favourite, preview, info, download. --}}
                                    <button type="button" x-show="row.kind === 'file'" @click="toggleFavorite(row)" :title="row.favorite ? @js(__('files.unfavorite')) : @js(__('files.favorite'))" :aria-label="row.favorite ? @js(__('files.unfavorite')) : @js(__('files.favorite'))" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 hover:bg-gray-100 dark:hover:bg-gray-800" :class="row.favorite ? 'text-amber-500' : 'text-gray-400 dark:text-gray-500 hover:text-gray-600'">
                                        <span x-show="row.favorite"><x-icon name="star-solid" class="h-4 w-4" /></span>
                                        <span x-show="! row.favorite"><x-icon name="star" class="h-4 w-4" /></span>
                                    </button>
                                    <button type="button" x-show="row.kind === 'file'" @click="openFile(row)" title="{{ __('files.preview') }}" aria-label="{{ __('files.preview') }}" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="eye" class="h-4 w-4" /></button>
                                    <button type="button" @click="openInfo(row)" title="{{ __('files.info') }}" aria-label="{{ __('files.info') }}" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="info" class="h-4 w-4" /></button>
                                    <button type="button" x-show="row.kind === 'file'" @click="download(row)" title="{{ __('files.download') }}" aria-label="{{ __('files.download') }}" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="arrow-down-tray" class="h-4 w-4" /></button>
                                    <div class="relative inline-block text-left">
                                        <button type="button" @click="toggleMenu($event)" @keydown.escape="menu = false" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600" aria-label="{{ __('files.actions') }}"><x-icon name="ellipsis" /></button>
                                        {{-- Teleported to the body so the table's overflow-x-auto wrapper cannot
                                             clip the menu (which would hide it and force a scrollbar). --}}
                                        <template x-teleport="body">
                                        <div x-show="menu" x-cloak @click.outside="menu = false" @keydown.escape.window="menu = false" @scroll.window="menu = false" @resize.window="menu = false" :style="menuStyle" class="fixed z-[60] w-44 rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-1 text-left text-sm shadow-lg">
                                            <button type="button" @click="startRename(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="pencil" />{{ __('files.rename') }}</button>
                                            <button type="button" @click="openMove(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="arrows-right-left" />{{ __('files.move') }}</button>
                                            <button type="button" @click="duplicate(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="document-duplicate" />{{ __('files.duplicate') }}</button>
                                            <button type="button" x-show="row.kind === 'file'" @click="openPublicLink(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="link" />{{ __('files.share_link') }}</button>
                                            <button type="button" @click="openTags(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="tag" />{{ __('files.edit_tags') }}</button>
                                            <button type="button" x-show="row.kind !== 'folder'" @click="openVersions(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="arrow-path" />{{ __('files.versions') }}</button>
                                            <button type="button" x-show="isMarkdown(row)" @click="openMigrate(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="document-text" />{{ __('files.migrate_to_note') }}</button>
                                            <button type="button" x-show="isPdf(row) && $store.paperless.configured" @click="openPaperless(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="share" />{{ __('paperless.send_to_paperless') }}</button>
                                            <button type="button" x-show="isZip(row)" @click="extractArchive(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="folder-plus" />{{ __('files.extract_here') }}</button>
                                            <button type="button" @click="confirmDelete(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="trash" />{{ __('common.delete') }}</button>
                                        </div>
                                        </template>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            </div>
        </div>
        </div>{{-- /main --}}
      </div>{{-- /flex row --}}
    </template>

    {{-- Bulk bar: floats at the bottom so actions are reachable without scrolling. --}}
    <div x-show="selected.length && ! trashView" x-cloak x-transition
        :class="(up.active || dl.active) ? 'bottom-72' : 'bottom-5'"
        class="fixed inset-x-0 z-40 mx-auto flex w-max max-w-[95vw] flex-wrap items-center justify-center gap-3 rounded-full border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-2 shadow-xl">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300"><span x-text="selected.length"></span> {{ __('files.selected_word') }}</span>
        <div class="relative" x-data="{ fmt: false }" @click.outside="fmt = false">
            <button type="button" @click="fmt = ! fmt" title="{{ __('files.export_download') }}" aria-label="{{ __('files.export_download') }}" class="rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="document-arrow-down" class="h-5 w-5" /></button>
            <div x-show="fmt" x-cloak class="absolute bottom-full left-1/2 mb-2 w-40 -translate-x-1/2 overflow-hidden rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-1 text-left text-sm shadow-lg">
                <button type="button" @click="bulkDownload('zip'); fmt = false" class="block w-full px-3 py-2 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">ZIP</button>
                <button type="button" @click="bulkDownload('tar'); fmt = false" class="block w-full px-3 py-2 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">TAR</button>
                <button type="button" @click="bulkDownload('targz'); fmt = false" class="block w-full px-3 py-2 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">TAR.GZ</button>
                <button type="button" @click="bulkDownload('tarbz2'); fmt = false" class="block w-full px-3 py-2 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">TAR.BZ2</button>
            </div>
        </div>
        <button type="button" @click="createArchive()" title="{{ __('files.archive_create') }}" aria-label="{{ __('files.archive_create') }}" class="rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="archive-box" class="h-5 w-5" /></button>
        <button type="button" @click="duplicate(null)" title="{{ __('files.duplicate') }}" aria-label="{{ __('files.duplicate') }}" class="rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="document-duplicate" class="h-5 w-5" /></button>
        <button type="button" @click="openBulkRename()" title="{{ __('files.bulk_rename') }}" aria-label="{{ __('files.bulk_rename') }}" class="rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="pencil" class="h-5 w-5" /></button>
        <button type="button" @click="openMove(null)" title="{{ __('files.move') }}" aria-label="{{ __('files.move') }}" class="rounded-md bg-gray-800 p-2 text-white hover:bg-gray-700"><x-icon name="arrows-right-left" class="h-5 w-5" /></button>
        <button type="button" @click="confirmDelete(null)" title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}" class="rounded-md border border-red-300 p-2 text-red-700 dark:text-red-300 hover:bg-red-50"><x-icon name="trash" class="h-5 w-5" /></button>
    </div>

    {{-- Upload progress --}}
    <div x-show="up.active" x-cloak class="fixed bottom-5 right-5 z-[950] w-80 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xl">
        <div class="flex items-center justify-between px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300">
            <span>{{ __('files.uploading') }}</span>
            <span class="text-gray-500 dark:text-gray-400"><span x-text="up.done"></span>/<span x-text="up.total"></span></span>
        </div>
        <div class="h-2 bg-gray-100 dark:bg-gray-800">
            <div class="h-2 bg-gray-800 transition-all" :style="`width: ${up.total ? Math.round(up.done / up.total * 100) : 0}%`"></div>
        </div>
        <p class="px-4 py-2 text-xs text-amber-700">{{ __('files.encrypting_keep_open') }}</p>
    </div>

    {{-- Download progress --}}
    <div x-show="dl.active" x-cloak class="fixed bottom-5 right-5 z-[950] w-80 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-xl">
        {{ __('files.decrypting') }}
    </div>

    {{-- Move modal --}}
    <template x-teleport="body">
        <div x-show="moveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="moveOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="moveOpen = false"></div>
            <div class="relative flex max-h-[80vh] w-full max-w-md flex-col rounded-lg bg-white dark:bg-gray-900 shadow-xl">
                <h3 class="border-b border-gray-100 dark:border-gray-800 px-6 py-4 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('files.move_title') }} <span class="text-gray-400 dark:text-gray-500">(<span x-text="moveRefs.length"></span>)</span></h3>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                        <input type="radio" name="move_target" value="" x-model="moveTarget" class="border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                        {{ __('files.root_folder') }}
                    </label>
                    <template x-for="opt in moveOptions" :key="opt.id">
                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                            <input type="radio" name="move_target" :value="opt.id" x-model="moveTarget" class="border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                            <span x-text="opt.label"></span>
                        </label>
                    </template>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-100 dark:border-gray-800 px-6 py-4">
                    <button type="button" @click="moveOpen = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyMove()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.move_here') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Viewer / editor: image, PDF or editable text, decrypted in the browser --}}
    <template x-teleport="body">
        <div x-show="viewer.open" x-cloak class="fixed inset-0 z-[1050] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeViewer()"
            @keydown.arrow-left.window="viewerHasGallery && viewerStep(-1)" @keydown.arrow-right.window="viewerHasGallery && viewerStep(1)">
            <div class="absolute inset-0 bg-gray-900/60" @click="closeViewer()"></div>
            <div class="relative flex max-h-[92vh] w-full max-w-4xl flex-col rounded-lg bg-white dark:bg-gray-900 shadow-xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800 px-5 py-3">
                    <h3 class="truncate text-base font-semibold text-gray-900 dark:text-gray-100" x-text="viewer.row?.name"></h3>
                    <div class="flex shrink-0 items-center gap-3">
                        <span x-show="viewerHasGallery" x-cloak class="text-xs tabular-nums text-gray-400 dark:text-gray-500" x-text="`${viewerIndex + 1} / ${viewerImages.length}`"></span>
                        <button type="button" x-show="viewer.kind === 'pdf' && $store.paperless.configured" @click="openPaperless(viewer.row)" title="{{ __('paperless.send_to_paperless') }}" aria-label="{{ __('paperless.send_to_paperless') }}" class="rounded p-1 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="share" class="h-5 w-5" /></button>
                        <button type="button" @click="download(viewer.row)" title="{{ __('files.download') }}" aria-label="{{ __('files.download') }}" class="rounded p-1 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="arrow-down-tray" class="h-5 w-5" /></button>
                        <button type="button" @click="closeViewer()" title="{{ __('common.close') }}" aria-label="{{ __('common.close') }}" class="rounded p-1 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600"><x-icon name="x-mark" class="h-5 w-5" /></button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 overflow-auto p-4">
                    <div x-show="viewer.kind === 'image'" x-cloak class="relative">
                        {{-- Click the image to advance to the next one (slideshow). --}}
                        <img :src="viewer.src" :alt="viewer.row?.name"
                            :class="viewerHasGallery ? 'cursor-pointer' : ''"
                            @click="viewerHasGallery && viewerStep(1)"
                            class="mx-auto max-h-[75vh] rounded object-contain">
                        <button type="button" x-show="viewerHasGallery" @click.stop="viewerStep(-1)"
                            title="{{ __('files.prev_image') }}" aria-label="{{ __('files.prev_image') }}"
                            class="absolute left-1 top-1/2 -translate-y-1/2 rounded-full bg-gray-900/50 p-2 text-white hover:bg-gray-900/70"><x-icon name="chevron-left" class="h-5 w-5" /></button>
                        <button type="button" x-show="viewerHasGallery" @click.stop="viewerStep(1)"
                            title="{{ __('files.next_image') }}" aria-label="{{ __('files.next_image') }}"
                            class="absolute right-1 top-1/2 -translate-y-1/2 rounded-full bg-gray-900/50 p-2 text-white hover:bg-gray-900/70"><x-icon name="chevron-right" class="h-5 w-5" /></button>
                        {{-- Decrypt indicator while paging: inside the viewer so it sits
                             above the overlay. Spinner only — stays readable on mobile. --}}
                        <div x-show="dl.active" x-cloak class="absolute inset-0 flex items-center justify-center rounded bg-white/70">
                            <x-icon name="arrow-path" class="h-8 w-8 animate-spin text-gray-500 dark:text-gray-400" />
                        </div>
                    </div>
                    <template x-if="viewer.kind === 'pdf'">
                        <object :data="viewer.src" type="application/pdf" class="h-[75vh] w-full rounded"></object>
                    </template>
                    <template x-if="viewer.kind === 'video'">
                        <video :src="viewer.src" controls class="mx-auto max-h-[75vh] w-full rounded bg-black"></video>
                    </template>
                    <template x-if="viewer.kind === 'audio'">
                        <div class="py-10">
                            <audio :src="viewer.src" controls class="mx-auto w-full max-w-lg"></audio>
                        </div>
                    </template>
                    <div x-show="viewer.kind === 'text'" x-cloak>
                        <div class="mb-2 flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('files.language') }}</label>
                            <select x-model="editorLang" @change="onEditorLanguageChange()"
                                class="rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <option value="">{{ __('files.plain_text') }}</option>
                                <template x-for="name in languageOptions" :key="name">
                                    <option :value="name" x-text="name"></option>
                                </template>
                            </select>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('files.search_hint') }}</span>
                        </div>
                        <div x-ref="viewerEditor" class="overflow-hidden rounded-lg border border-gray-300 dark:border-gray-700"></div>
                        <div class="mt-3 flex items-center gap-3">
                            <button type="button" @click="saveText()" :disabled="viewer.saving"
                                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('files.save') }}</button>
                            <span x-show="viewer.saved" x-cloak class="text-green-600"><x-icon name="check" class="h-4 w-4" /></span>
                        </div>
                    </div>
                    <p x-show="viewer.kind === 'none'" x-cloak class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('files.encrypted_no_preview') }}</p>
                </div>
            </div>
        </div>
    </template>

    {{-- Tags modal --}}
    <template x-teleport="body">
        <div x-show="tagsOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="tagsOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="tagsOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('files.edit_tags') }}</h3>
                <input type="text" x-model="tagsValue" list="file-tags" placeholder="tag1, tag2"
                    class="mt-4 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <datalist id="file-tags">
                    <template x-for="tag in allTags" :key="tag"><option :value="tag"></option></template>
                </datalist>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="tagsOpen = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyTags()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Versions modal --}}
    <template x-teleport="body">
        <div x-show="versions.open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" @keydown.escape.window="versions.open = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="versions.open = false"></div>
            <div class="relative my-16 w-full max-w-md rounded-lg bg-white dark:bg-gray-900 shadow-xl">
                <h3 class="border-b border-gray-100 dark:border-gray-800 px-6 py-4 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('files.versions') }} <span class="text-gray-400 dark:text-gray-500" x-text="versions.row?.name"></span></h3>
                <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
                    <p x-show="!versions.loading && !versions.list.length" x-cloak class="text-sm text-gray-500 dark:text-gray-400">{{ __('files.versions_none') }}</p>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template x-for="v in versions.list" :key="v.id">
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="min-w-0">
                                    <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="v.created_at ? new Date(v.created_at).toLocaleString() : ''"></span>
                                    <span class="text-gray-700 dark:text-gray-300" x-text="fmtSize(v.size)"></span>
                                </span>
                                <span class="flex shrink-0 items-center gap-2">
                                    <a :href="versionDownloadUrl(v.id)" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 dark:border-gray-700 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('files.version_download') }}</a>
                                    <button type="button" @click="restoreVersion(v)" class="inline-flex min-h-11 items-center rounded-md bg-gray-900 dark:bg-gray-100 dark:text-gray-900 px-3 text-sm font-medium text-white hover:bg-gray-800 dark:hover:bg-white">{{ __('files.version_restore') }}</button>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
                <div class="flex justify-end border-t border-gray-100 dark:border-gray-800 px-6 py-3">
                    <button type="button" @click="versions.open = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.close') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Information modal --}}
    <template x-teleport="body">
        <div x-show="infoOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="infoOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="infoOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl" x-show="infoRow">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('files.info_title') }}</h3>
                <dl class="mt-4 divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('files.info_name') }}</dt>
                        <dd class="min-w-0 break-all text-right font-medium text-gray-900 dark:text-gray-100" x-text="infoRow?.name"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('files.info_type') }}</dt>
                        <dd class="text-right text-gray-900 dark:text-gray-100" x-text="infoRow?.kind === 'folder' ? @js(__('files.folder')) : typeLabel(infoRow)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.kind === 'file'">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('files.info_mime') }}</dt>
                        <dd class="min-w-0 break-all text-right text-gray-900 dark:text-gray-100" x-text="infoRow?.mime"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.kind === 'file'">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('files.info_size') }}</dt>
                        <dd class="text-right text-gray-900 dark:text-gray-100" x-text="fmtSize(infoRow?.size)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.kind === 'folder'">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('files.info_items') }}</dt>
                        <dd class="text-right text-gray-900 dark:text-gray-100" x-text="folderItemCount(infoRow)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.created">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('files.info_uploaded') }}</dt>
                        <dd class="text-right text-gray-900 dark:text-gray-100" x-text="fmtDate(infoRow?.created)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('files.info_folder') }}</dt>
                        <dd class="text-right text-gray-900 dark:text-gray-100" x-text="infoFolderPath(infoRow)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="(infoRow?.tags ?? []).length">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('files.info_tags') }}</dt>
                        <dd class="text-right text-gray-900 dark:text-gray-100" x-text="(infoRow?.tags ?? []).join(', ')"></dd>
                    </div>
                </dl>
                <div x-show="infoRow?.kind === 'file'" class="mt-4">
                    <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('files.note') }}</label>
                    <textarea x-model="infoNote" @blur="saveNote()" rows="3" placeholder="{{ __('files.note_placeholder') }}"
                        class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></textarea>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="button" @click="infoOpen = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.close') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Migrate a Markdown file to a note --}}
    <template x-teleport="body">
        <div x-show="migrateOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="migrateOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="migrateOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('files.migrate_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    <span x-text="migrateRow?.name"></span> — {{ __('files.migrate_intro') }}
                </p>
                <label class="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" x-model="migrateDelete" class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                    {{ __('files.migrate_delete_after') }}
                </label>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="migrateOpen = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyMigrate()" :disabled="migrateBusy" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('files.migrate_confirm') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Delete confirm --}}
    <template x-teleport="body">
        <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="deleteOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('common.confirm_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-medium" x-text="deleteRefs.map(r => r.name).join(', ')"></span>
                </p>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('files.delete_choice_hint') }}</p>
                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button type="button" @click="deleteOpen = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyDelete(true)" class="rounded-md border border-red-300 dark:border-red-800 px-4 py-2 text-sm font-medium text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950">{{ __('files.delete_forever') }}</button>
                    <button type="button" @click="applyDelete(false)" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.move_to_trash') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Bulk rename modal --}}
    <template x-teleport="body">
        <div x-show="renameOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="renameOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="renameOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('files.bulk_rename') }}</h3>
                <div class="mt-4 grid gap-3">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-sm"><span class="text-gray-600 dark:text-gray-400">{{ __('files.rename_find') }}</span>
                            <input type="text" x-model="renameOpts.find" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm"></label>
                        <label class="text-sm"><span class="text-gray-600 dark:text-gray-400">{{ __('files.rename_replace') }}</span>
                            <input type="text" x-model="renameOpts.replace" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm"></label>
                        <label class="text-sm"><span class="text-gray-600 dark:text-gray-400">{{ __('files.rename_prefix') }}</span>
                            <input type="text" x-model="renameOpts.prefix" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm"></label>
                        <label class="text-sm"><span class="text-gray-600 dark:text-gray-400">{{ __('files.rename_suffix') }}</span>
                            <input type="text" x-model="renameOpts.suffix" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm"></label>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="renameOpen = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyBulkRename()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.rename_apply') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Public download link modal --}}
    <template x-teleport="body">
        <div x-show="publicOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="publicOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="publicOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('files.public_link') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="publicRow?.name"></p>

                <div x-show="! publicLink" class="mt-4 space-y-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('files.public_link_desc') }}</p>
                    <label class="block text-sm">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('files.expiry') }}</span>
                        <select x-model="publicOpts.expires_in" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm">
                            <option value="">{{ __('files.no_expiry') }}</option>
                            <option value="3600">{{ __('files.exp_1h') }}</option>
                            <option value="86400">{{ __('files.exp_1d') }}</option>
                            <option value="604800">{{ __('files.exp_1w') }}</option>
                            <option value="2592000">{{ __('files.exp_30d') }}</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-gray-600 dark:text-gray-400">{{ __('files.password_optional') }}</span>
                        <input type="text" x-model="publicOpts.password" autocomplete="off" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm">
                    </label>
                </div>

                <div x-show="publicLink" class="mt-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <input type="text" readonly :value="publicLink?.url" class="w-full rounded-md border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs shadow-sm">
                        <button type="button" @click="copyPublicLink()" class="shrink-0 rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800" title="{{ __('files.copy') }}"><x-icon name="clipboard" class="h-4 w-4" /></button>
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                        <span x-show="publicLink?.hasPassword"><x-icon name="lock-closed" class="inline h-3.5 w-3.5" /></span>
                        <span x-show="publicLink?.expiresAt" x-text="'{{ __('files.expiry') }}: ' + new Date(publicLink?.expiresAt).toLocaleString()"></span>
                        <span x-text="'{{ __('files.downloads_n') }}: ' + (publicLink?.downloads ?? 0)"></span>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <button type="button" @click="publicOpen = false" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('common.close') }}</button>
                    <template x-if="publicLink">
                        <div class="flex gap-3">
                            <button type="button" @click="revokePublic()" class="rounded-md border border-red-300 dark:border-red-800 px-4 py-2 text-sm font-medium text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950">{{ __('files.revoke_link') }}</button>
                            <button type="button" @click="rotatePublic()" class="rounded-md border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('files.rotate_link') }}</button>
                        </div>
                    </template>
                    <template x-if="! publicLink">
                        <button type="button" @click="createPublic()" :disabled="publicBusy" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('files.create_link') }}</button>
                    </template>
                </div>
            </div>
        </div>
    </template>

    @include('_paperless_modal')
  </div>
</x-layouts.app>
