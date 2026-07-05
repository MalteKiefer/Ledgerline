<x-layouts.app :title="__('messages.nav.files')">
  @php
      $typeLabels = collect(\App\Enums\FileType::cases())
          ->mapWithKeys(fn (\App\Enums\FileType $c): array => [$c->value => $c->label()]);
  @endphp
  <div x-data="vaultFiles({
        dataUrl: '{{ url('/files/data') }}',
        uploadUrl: '{{ url('/files/upload') }}',
        rawBase: '{{ url('/files/raw') }}',
        blobBase: '{{ url('/files/blob') }}',
        versionsBase: '{{ url('/files') }}',
        token: '{{ csrf_token() }}',
     }, {
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
     })">

    {{-- Whole-window drop zone (folders with subfolders supported) --}}
    <div x-show="dragging && state === 'ready'" x-cloak @drop.prevent="drop($event)" @dragover.prevent
        class="fixed inset-0 z-[900] flex items-center justify-center bg-gray-900/50 p-8">
        <div class="rounded-2xl border-4 border-dashed border-white/80 px-16 py-24 text-center text-lg font-medium text-white">{{ __('files.drop_hint') }}</div>
    </div>

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">{{ __('files.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div>
        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <nav class="text-sm text-gray-500">
                    <button type="button" @click="cwd = null" class="hover:underline">{{ __('files.all_files') }}</button>
                    <template x-for="crumb in breadcrumb" :key="crumb.id">
                        <span>
                            <span aria-hidden="true">/</span>
                            <button type="button" @click="cwd = crumb.id" class="hover:underline" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900" x-text="currentFolderName ?? @js(__('messages.nav.files'))"></h1>
                <div x-show="usage.quota > 0" x-cloak class="mt-2 max-w-xs">
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                        <div class="h-full bg-gray-700" :style="'width:'+Math.min(100, Math.round((usage.used/usage.quota)*100))+'%'"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500" x-text="'{{ __('files.storage_used', ['used' => '__U__', 'total' => '__T__']) }}'.replace('__U__', fmtSize(usage.used)).replace('__T__', fmtSize(usage.quota))"></p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                {{-- New folder --}}
                <form class="flex items-center gap-1" @submit.prevent="mkdir($refs.newFolder.value); $refs.newFolder.value = ''">
                    <input type="text" x-ref="newFolder" required placeholder="{{ __('files.new_folder') }}"
                        class="w-full sm:w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <button type="submit" title="{{ __('files.new_folder') }}" aria-label="{{ __('files.new_folder') }}"
                        class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="folder-plus" class="h-5 w-5" /></button>
                </form>
                {{-- Upload --}}
                <label title="{{ __('files.upload') }}" aria-label="{{ __('files.upload') }}"
                    class="cursor-pointer rounded-md bg-gray-800 p-2 text-white hover:bg-gray-700">
                    <x-icon name="arrow-up-tray" class="h-5 w-5" />
                    <input type="file" multiple class="hidden" @change="upload($event.target.files); $event.target.value = ''">
                </label>
            </div>
        </div>

        {{-- Search (client-side, over the decrypted manifest) --}}
        <div class="mt-6">
            <input type="search" x-model="query" placeholder="{{ __('files.search') }}"
                class="w-full sm:w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            <span x-show="activeTag" x-cloak class="ml-3 inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs text-blue-800">
                {{ __('files.filtered_by') }}: <span x-text="activeTag"></span>
                <button type="button" @click="activeTag = ''" class="text-blue-500 hover:text-blue-700"><x-icon name="x-mark" class="h-3 w-3" /></button>
            </span>
        </div>

        <p x-show="error" x-cloak class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800" x-text="error"></p>

        {{-- Browser --}}
        <div class="mt-4 overflow-visible rounded-lg border border-gray-200 bg-white shadow-sm">
            <template x-if="rows.length === 0">
                <p class="px-4 py-10 text-center text-sm text-gray-500">{{ __('files.empty_explorer') }}</p>
            </template>
            <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <table x-show="rows.length > 0" class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3"><input type="checkbox" @change="toggleAll($event)" aria-label="{{ __('files.select_all') }}" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"></th>
                        <th class="px-4 py-3">
                            <button type="button" @click="sortDir = sortDir === 'asc' ? 'desc' : 'asc'" class="uppercase hover:text-gray-700">
                                {{ __('files.col_name') }} <span x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        <th class="hidden px-4 py-3 sm:table-cell">{{ __('files.col_type') }}</th>
                        <th class="hidden px-4 py-3 text-right sm:table-cell">{{ __('files.col_size') }}</th>
                        <th class="hidden px-4 py-3 md:table-cell">{{ __('files.col_tags') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    {{-- Parent-folder shortcut, like "cd .." — virtual row, never
                         part of rows(), so it is excluded from selection, actions
                         and export. Also a drop target to move items up. --}}
                    <template x-if="cwd !== null && query === '' && activeTag === ''">
                        <tr class="cursor-pointer text-gray-500 hover:bg-gray-50" @click="cwd = parentFolderId"
                            @dragover="if (dragItem) $event.preventDefault()" @drop.prevent="dropInto(parentFolderId)">
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 font-medium">
                                <span class="flex items-center gap-2">
                                    <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
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
                        <tr class="cursor-pointer hover:bg-gray-50" x-data="{ menu: false }"
                            :draggable="renaming === row.id ? 'false' : 'true'"
                            @dragstart.stop="onDragStart($event, row)" @dragend="onDragEnd()"
                            @dragover="if (row.kind === 'folder' && dragItem && !(dragItem.kind === 'folder' && dragItem.id === row.id)) $event.preventDefault()"
                            @drop.prevent="row.kind === 'folder' && dropInto(row.id)"
                            @click="if (renaming !== row.id) { row.kind === 'folder' ? cwd = row.id : openFile(row) }">
                            <td class="px-4 py-3" @click.stop><input type="checkbox" :value="rowKey(row)" x-model="selected" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"></td>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <span class="flex min-w-0 items-center gap-2" x-show="renaming !== row.id">
                                    <svg x-show="row.kind === 'folder'" class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                                    <svg x-show="row.kind === 'file'" class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="fileIconPath(row)" /></svg>
                                    <span class="truncate" x-text="row.name"></span>
                                </span>
                                <form x-show="renaming === row.id" x-cloak class="flex gap-2" @click.stop @submit.prevent="applyRename(row)">
                                    <input type="text" x-model="renameValue" x-ref="rename"
                                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                    <button type="submit" class="rounded-md bg-gray-800 px-3 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                                    <button type="button" @click="renaming = null" class="text-gray-500 hover:text-gray-700"><x-icon name="x-mark" /></button>
                                </form>
                            </td>
                            <td class="hidden px-4 py-3 text-gray-600 sm:table-cell" x-text="row.kind === 'folder' ? @js(__('files.folder')) : typeLabel(row)"></td>
                            <td class="hidden px-4 py-3 text-right text-gray-600 sm:table-cell" x-text="row.kind === 'folder' ? '—' : fmtSize(row.size)"></td>
                            <td class="hidden px-4 py-3 md:table-cell" @click.stop>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="tag in (row.tags ?? [])" :key="tag">
                                        <button type="button" @click="activeTag = tag"
                                            class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 hover:bg-gray-200" x-text="tag"></button>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right" @click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    {{-- Quick actions (icon-only): preview, info, download. --}}
                                    <button type="button" x-show="row.kind === 'file'" @click="openFile(row)" title="{{ __('files.preview') }}" aria-label="{{ __('files.preview') }}" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"><x-icon name="eye" class="h-4 w-4" /></button>
                                    <button type="button" @click="openInfo(row)" title="{{ __('files.info') }}" aria-label="{{ __('files.info') }}" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"><x-icon name="info" class="h-4 w-4" /></button>
                                    <button type="button" x-show="row.kind === 'file'" @click="download(row)" title="{{ __('files.download') }}" aria-label="{{ __('files.download') }}" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"><x-icon name="arrow-down-tray" class="h-4 w-4" /></button>
                                    <div class="relative inline-block text-left">
                                        <button type="button" @click="menu = ! menu" @keydown.escape="menu = false" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="{{ __('files.actions') }}"><x-icon name="ellipsis" /></button>
                                        <div x-show="menu" x-cloak @click.outside="menu = false" class="absolute right-0 z-20 mt-1 w-44 rounded-md border border-gray-200 bg-white py-1 text-left text-sm shadow-lg">
                                            <button type="button" @click="startRename(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="pencil" />{{ __('files.rename') }}</button>
                                            <button type="button" @click="openMove(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="arrows-right-left" />{{ __('files.move') }}</button>
                                            <button type="button" @click="openTags(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="tag" />{{ __('files.edit_tags') }}</button>
                                            <button type="button" x-show="row.kind !== 'folder'" @click="openVersions(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="arrow-path" />{{ __('files.versions') }}</button>
                                            <button type="button" x-show="isMarkdown(row)" @click="openMigrate(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="document-text" />{{ __('files.migrate_to_note') }}</button>
                                            <button type="button" x-show="isPdf(row) && $store.paperless.configured" @click="openPaperless(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="share" />{{ __('paperless.send_to_paperless') }}</button>
                                            <button type="button" @click="confirmDelete(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 hover:bg-gray-50"><x-icon name="trash" />{{ __('common.delete') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            </div>
        </div>
      </div>
    </template>

    {{-- Bulk bar: floats at the bottom so actions are reachable without scrolling. --}}
    <div x-show="selected.length" x-cloak x-transition
        :class="(up.active || dl.active) ? 'bottom-72' : 'bottom-5'"
        class="fixed inset-x-0 z-40 mx-auto flex w-max max-w-[95vw] flex-wrap items-center justify-center gap-3 rounded-full border border-gray-200 bg-white px-4 py-2 shadow-xl">
        <span class="text-sm font-medium text-gray-700"><span x-text="selected.length"></span> {{ __('files.selected_word') }}</span>
        <button type="button" @click="bulkDownload()" title="{{ __('files.download_zip') }}" aria-label="{{ __('files.download_zip') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="document-arrow-down" class="h-5 w-5" /></button>
        <button type="button" @click="openMove(null)" title="{{ __('files.move') }}" aria-label="{{ __('files.move') }}" class="rounded-md bg-gray-800 p-2 text-white hover:bg-gray-700"><x-icon name="arrows-right-left" class="h-5 w-5" /></button>
        <button type="button" @click="confirmDelete(null)" title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}" class="rounded-md border border-red-300 p-2 text-red-700 hover:bg-red-50"><x-icon name="trash" class="h-5 w-5" /></button>
    </div>

    {{-- Upload progress --}}
    <div x-show="up.active" x-cloak class="fixed bottom-5 right-5 z-[950] w-80 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl">
        <div class="flex items-center justify-between px-4 py-2 text-sm font-medium text-gray-700">
            <span>{{ __('files.uploading') }}</span>
            <span class="text-gray-500"><span x-text="up.done"></span>/<span x-text="up.total"></span></span>
        </div>
        <div class="h-2 bg-gray-100">
            <div class="h-2 bg-gray-800 transition-all" :style="`width: ${up.total ? Math.round(up.done / up.total * 100) : 0}%`"></div>
        </div>
        <p class="px-4 py-2 text-xs text-amber-700">{{ __('files.encrypting_keep_open') }}</p>
    </div>

    {{-- Download progress --}}
    <div x-show="dl.active" x-cloak class="fixed bottom-5 right-5 z-[950] w-80 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-xl">
        {{ __('files.decrypting') }}
    </div>

    {{-- Move modal --}}
    <template x-teleport="body">
        <div x-show="moveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="moveOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="moveOpen = false"></div>
            <div class="relative flex max-h-[80vh] w-full max-w-md flex-col rounded-lg bg-white shadow-xl">
                <h3 class="border-b border-gray-100 px-6 py-4 text-base font-semibold text-gray-900">{{ __('files.move_title') }} <span class="text-gray-400">(<span x-text="moveRefs.length"></span>)</span></h3>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50">
                        <input type="radio" name="move_target" value="" x-model="moveTarget" class="border-gray-300 text-gray-800 focus:ring-gray-500">
                        {{ __('files.root_folder') }}
                    </label>
                    <template x-for="opt in moveOptions" :key="opt.id">
                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50">
                            <input type="radio" name="move_target" :value="opt.id" x-model="moveTarget" class="border-gray-300 text-gray-800 focus:ring-gray-500">
                            <span x-text="opt.label"></span>
                        </label>
                    </template>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="moveOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
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
            <div class="relative flex max-h-[92vh] w-full max-w-4xl flex-col rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
                    <h3 class="truncate text-base font-semibold text-gray-900" x-text="viewer.row?.name"></h3>
                    <div class="flex shrink-0 items-center gap-3">
                        <span x-show="viewerHasGallery" x-cloak class="text-xs tabular-nums text-gray-400" x-text="`${viewerIndex + 1} / ${viewerImages.length}`"></span>
                        <button type="button" x-show="viewer.kind === 'pdf' && $store.paperless.configured" @click="openPaperless(viewer.row)" title="{{ __('paperless.send_to_paperless') }}" aria-label="{{ __('paperless.send_to_paperless') }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700"><x-icon name="share" class="h-5 w-5" /></button>
                        <button type="button" @click="download(viewer.row)" title="{{ __('files.download') }}" aria-label="{{ __('files.download') }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700"><x-icon name="arrow-down-tray" class="h-5 w-5" /></button>
                        <button type="button" @click="closeViewer()" title="{{ __('common.close') }}" aria-label="{{ __('common.close') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><x-icon name="x-mark" class="h-5 w-5" /></button>
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
                            <x-icon name="arrow-path" class="h-8 w-8 animate-spin text-gray-500" />
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
                            <label class="text-xs font-medium text-gray-500">{{ __('files.language') }}</label>
                            <select x-model="editorLang" @change="onEditorLanguageChange()"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <option value="">{{ __('files.plain_text') }}</option>
                                <template x-for="name in languageOptions" :key="name">
                                    <option :value="name" x-text="name"></option>
                                </template>
                            </select>
                            <span class="text-xs text-gray-400">{{ __('files.search_hint') }}</span>
                        </div>
                        <div x-ref="viewerEditor" class="overflow-hidden rounded-lg border border-gray-300"></div>
                        <div class="mt-3 flex items-center gap-3">
                            <button type="button" @click="saveText()" :disabled="viewer.saving"
                                class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('files.save') }}</button>
                            <span x-show="viewer.saved" x-cloak class="text-green-600"><x-icon name="check" class="h-4 w-4" /></span>
                        </div>
                    </div>
                    <p x-show="viewer.kind === 'none'" x-cloak class="py-10 text-center text-sm text-gray-500">{{ __('files.encrypted_no_preview') }}</p>
                </div>
            </div>
        </div>
    </template>

    {{-- Tags modal --}}
    <template x-teleport="body">
        <div x-show="tagsOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="tagsOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="tagsOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('files.edit_tags') }}</h3>
                <input type="text" x-model="tagsValue" list="file-tags" placeholder="tag1, tag2"
                    class="mt-4 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <datalist id="file-tags">
                    <template x-for="tag in allTags" :key="tag"><option :value="tag"></option></template>
                </datalist>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="tagsOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyTags()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Versions modal --}}
    <template x-teleport="body">
        <div x-show="versions.open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" @keydown.escape.window="versions.open = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="versions.open = false"></div>
            <div class="relative my-16 w-full max-w-md rounded-lg bg-white shadow-xl">
                <h3 class="border-b border-gray-100 px-6 py-4 text-base font-semibold text-gray-900">{{ __('files.versions') }} <span class="text-gray-400" x-text="versions.row?.name"></span></h3>
                <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
                    <p x-show="!versions.loading && !versions.list.length" x-cloak class="text-sm text-gray-500">{{ __('files.versions_none') }}</p>
                    <ul class="divide-y divide-gray-100">
                        <template x-for="v in versions.list" :key="v.id">
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="min-w-0">
                                    <span class="block text-xs text-gray-500" x-text="v.created_at ? new Date(v.created_at).toLocaleString() : ''"></span>
                                    <span class="text-gray-700" x-text="fmtSize(v.size)"></span>
                                </span>
                                <span class="flex shrink-0 items-center gap-2">
                                    <a :href="versionDownloadUrl(v.id)" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('files.version_download') }}</a>
                                    <button type="button" @click="restoreVersion(v)" class="inline-flex min-h-11 items-center rounded-md bg-gray-900 px-3 text-sm font-medium text-white hover:bg-gray-800">{{ __('files.version_restore') }}</button>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
                <div class="flex justify-end border-t border-gray-100 px-6 py-3">
                    <button type="button" @click="versions.open = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.close') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Information modal --}}
    <template x-teleport="body">
        <div x-show="infoOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="infoOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="infoOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl" x-show="infoRow">
                <h3 class="text-base font-semibold text-gray-900">{{ __('files.info_title') }}</h3>
                <dl class="mt-4 divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500">{{ __('files.info_name') }}</dt>
                        <dd class="min-w-0 break-all text-right font-medium text-gray-900" x-text="infoRow?.name"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500">{{ __('files.info_type') }}</dt>
                        <dd class="text-right text-gray-900" x-text="infoRow?.kind === 'folder' ? @js(__('files.folder')) : typeLabel(infoRow)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.kind === 'file'">
                        <dt class="text-gray-500">{{ __('files.info_mime') }}</dt>
                        <dd class="min-w-0 break-all text-right text-gray-900" x-text="infoRow?.mime"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.kind === 'file'">
                        <dt class="text-gray-500">{{ __('files.info_size') }}</dt>
                        <dd class="text-right text-gray-900" x-text="fmtSize(infoRow?.size)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.kind === 'folder'">
                        <dt class="text-gray-500">{{ __('files.info_items') }}</dt>
                        <dd class="text-right text-gray-900" x-text="folderItemCount(infoRow)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.created">
                        <dt class="text-gray-500">{{ __('files.info_uploaded') }}</dt>
                        <dd class="text-right text-gray-900" x-text="fmtDate(infoRow?.created)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-gray-500">{{ __('files.info_folder') }}</dt>
                        <dd class="text-right text-gray-900" x-text="infoFolderPath(infoRow)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="(infoRow?.tags ?? []).length">
                        <dt class="text-gray-500">{{ __('files.info_tags') }}</dt>
                        <dd class="text-right text-gray-900" x-text="(infoRow?.tags ?? []).join(', ')"></dd>
                    </div>
                </dl>
                <div class="mt-5 flex justify-end">
                    <button type="button" @click="infoOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.close') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Migrate a Markdown file to a note --}}
    <template x-teleport="body">
        <div x-show="migrateOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="migrateOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="migrateOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('files.migrate_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600">
                    <span x-text="migrateRow?.name"></span> — {{ __('files.migrate_intro') }}
                </p>
                <label class="mt-4 flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" x-model="migrateDelete" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                    {{ __('files.migrate_delete_after') }}
                </label>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="migrateOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyMigrate()" :disabled="migrateBusy" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('files.migrate_confirm') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Delete confirm --}}
    <template x-teleport="body">
        <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="deleteOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600">
                    <span x-text="deleteRefs.map(r => r.name).join(', ')"></span> —
                    <span x-text="deleteRefs.some(r => r.kind === 'folder') ? @js(__('files.delete_folder_confirm')) : @js(__('files.delete_file_confirm'))"></span>
                </p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="deleteOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyDelete()" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('common.delete') }}</button>
                </div>
            </div>
        </div>
    </template>

    @include('_paperless_modal')
  </div>
</x-layouts.app>
