<x-layouts.app :title="__('messages.nav.files')">
  @php
      $typeLabels = collect(\App\Enums\FileType::cases())
          ->mapWithKeys(fn (\App\Enums\FileType $c): array => [$c->value => $c->label()]);
  @endphp
  <div x-data="{ filesTab: 'files' }">
  <div x-show="filesTab === 'files'">
  <div x-data="files({
        token: '{{ csrf_token() }}',
        sharesUrl: '{{ url('/files/rel-shares') }}',
        shareBase: '{{ url('/file-share') }}',
     }, {
        folderLabel: @js(__('files.folder')),
        shareError: @js(__('files.share_error')),
        shareCopied: @js(__('files.share_copied')),
        filetypeLabels: @js(collect(trans('filetype'))->all()),
        uploadUnreadable: @js(__('files.upload_unreadable')),
        types: @js($typeLabels),
        saveFailed: @js(__('files.save_failed')),
        dupesTrashAllConfirm: @js(__('files.dupes_trash_all_confirm')),
        dupesTrashed: @js(__('files.dupes_trashed')),
        dupesTrashFailed: @js(__('files.dupes_trash_failed')),
        folderShareEmail: @js(__('files.folder_recipient')),
        folderShareNotFound: @js(__('files.folder_recipient_not_found')),
        folderShareDone: @js(__('files.folder_shared')),
        uploadFailed: @js(__('files.upload_failed')),
        downloadFailed: @js(__('files.download_failed')),
        rootFolder: @js(__('files.all_files')),
        migrateFailed: @js(__('files.migrate_failed')),
        restoreConfirm: @js(__('files.version_restore_confirm')),
        quotaExceeded: @js(__('files.quota_exceeded')),
        purgeConfirm: @js(__('files.purge_confirm')),
        emptyTrashConfirm: @js(__('files.empty_trash_confirm')),
     }, @js([
        'folders' => $folders ?? [],
        'files' => $files ?? [],
        'usage' => $usage ?? ['used' => 0, 'quota' => 0],
        'maxVersions' => $maxVersions ?? 10,
     ]))">

    {{-- Whole-window drop zone (folders with subfolders supported) --}}
    <div x-show="dragging" x-cloak @drop.prevent="drop($event)" @dragover.prevent
        class="fixed inset-0 z-[900] flex items-center justify-center bg-black/40 p-8">
        <div class="rounded-2xl border-4 border-dashed border-white/80 px-16 py-24 text-center text-lg font-medium text-white">{{ __('files.drop_hint') }}</div>
    </div>

    {{-- Working indicator: a spinner badge (top-right) while any file operation is in flight. --}}
    <div x-show="busy > 0" x-cloak x-transition
        class="fixed right-4 top-20 z-[950] flex items-center gap-2 rounded-full border border-black/[0.06] dark:border-white/10 bg-md-surface px-3 py-1.5 text-sm font-medium text-md-on-surface-var shadow-lg">
        <span class="msym text-base animate-spin">history</span>
        {{ __('files.working') }}
    </div>

    {{-- Mobile sidebar trigger (above the framed container) --}}
    <div class="mb-3 md:hidden">
        <button type="button" @click="$store.nav.toggleSidebar()"
            class="flex min-h-11 w-full items-center gap-2 rounded-xl border border-md-outline-variant bg-md-surface px-3 text-sm font-medium text-md-on-surface-var shadow-sm">
            <x-icon name="bars-3" class="h-4 w-4 text-md-on-surface-var" />
            <span x-text="({files:@js(__('files.all_files')),favorites:@js(__('files.favorites')),recent:@js(__('files.recent')),trash:@js(__('files.trash'))})[view]"></span>
        </button>
    </div>

    <div class="md:flex md:overflow-hidden md:rounded-xl md:border md:border-md-outline-variant md:bg-md-surface md:h-[calc(100vh-8.5rem)]">
        {{-- Sidebar --}}
        <aside class="hidden w-56 shrink-0 flex-col self-stretch overflow-y-auto border-r border-md-outline-variant bg-md-surface-2 p-3 md:flex">
            <label class="mb-3 flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-md-primary px-4 py-2.5 text-sm font-semibold text-md-on-primary shadow-sm m3-state" title="{{ __('files.upload') }}">
                <span class="msym text-lg">upload</span>{{ __('files.upload') }}
                <input type="file" multiple class="hidden" @change="upload($event.target.files); $event.target.value = ''">
            </label>
            <nav class="space-y-1">
                <button type="button" @click="view = 'files'; selected = []; $store.nav.closeAll && $store.nav.closeAll()"
                    :class="view === 'files' ? 'bg-md-selected text-md-primary font-semibold' : 'text-md-on-surface-var hover:bg-md-primary/8'"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
                    <span class="msym text-xl">folder</span>
                    <span>{{ __('files.all_files') }}</span>
                </button>
                <button type="button" @click="view = 'favorites'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
                    :class="view === 'favorites' ? 'bg-md-selected text-md-primary font-semibold' : 'text-md-on-surface-var hover:bg-md-primary/8'"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
                    <span class="msym text-xl">star</span>
                    <span class="flex-1 text-left">{{ __('files.favorites') }}</span>
                    <span x-show="favCount > 0" x-cloak x-text="favCount" class="rounded-full bg-md-outline-variant px-1.5 text-xs text-md-on-surface-var"></span>
                </button>
                <button type="button" @click="view = 'recent'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
                    :class="view === 'recent' ? 'bg-md-selected text-md-primary font-semibold' : 'text-md-on-surface-var hover:bg-md-primary/8'"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
                    <span class="msym text-xl">history</span>
                    <span>{{ __('files.recent') }}</span>
                </button>
                <button type="button" @click="view = 'trash'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
                    @dragover.prevent="if (dragItem) $event.currentTarget.classList.add('ring-2','ring-red-400')"
                    @dragleave="$event.currentTarget.classList.remove('ring-2','ring-red-400')"
                    @drop.prevent="$event.currentTarget.classList.remove('ring-2','ring-red-400'); if (dragItem) { trashItem(dragItem); dragItem = null; }"
                    :class="view === 'trash' ? 'bg-md-selected text-md-primary font-semibold' : 'text-md-on-surface-var hover:bg-md-primary/8'"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
                    <span class="msym text-xl">delete</span>
                    <span class="flex-1 text-left">{{ __('files.trash') }}</span>
                    <span x-show="trashCount > 0" x-cloak x-text="trashCount" class="rounded-full bg-md-outline-variant px-1.5 text-xs text-md-on-surface-var"></span>
                </button>
            </nav>
            <div x-show="usage" x-cloak class="mt-auto border-t border-md-outline-variant pt-3">
                <template x-if="usage.quota > 0">
                    <div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-md-outline-variant">
                            <div class="h-full bg-accent" :style="'width:'+Math.min(100, Math.round((usage.used/usage.quota)*100))+'%'"></div>
                        </div>
                        <p class="mt-1 text-xs text-md-on-surface-var" x-text="'{{ __('files.storage_used', ['used' => '__U__', 'total' => '__T__']) }}'.replace('__U__', fmtSize(usage.used)).replace('__T__', fmtSize(usage.quota))"></p>
                    </div>
                </template>
                <template x-if="! usage.quota">
                    <p class="flex items-center gap-1.5 text-xs text-md-on-surface-var">
                        <span class="msym text-base shrink-0 text-md-on-surface-var">hard_drive</span>
                        <span x-text="'{{ __('files.storage_used_only', ['used' => '__U__']) }}'.replace('__U__', fmtSize(usage.used))"></span>
                    </p>
                </template>
            </div>
        </aside>
        <x-sheet side="left" store="sidebarOpen" :title="__('messages.nav.files')">
            <div class="space-y-4">
            <nav class="space-y-1">
                <button type="button" @click="view = 'files'; selected = []; $store.nav.closeAll && $store.nav.closeAll()"
                    :class="view === 'files' ? 'bg-md-selected text-md-primary font-semibold' : 'text-md-on-surface-var hover:bg-md-primary/8'"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
                    <span class="msym text-xl">folder</span>
                    <span>{{ __('files.all_files') }}</span>
                </button>
                <button type="button" @click="view = 'favorites'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
                    :class="view === 'favorites' ? 'bg-md-selected text-md-primary font-semibold' : 'text-md-on-surface-var hover:bg-md-primary/8'"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
                    <span class="msym text-xl">star</span>
                    <span class="flex-1 text-left">{{ __('files.favorites') }}</span>
                    <span x-show="favCount > 0" x-cloak x-text="favCount" class="rounded-full bg-md-outline-variant px-1.5 text-xs text-md-on-surface-var"></span>
                </button>
                <button type="button" @click="view = 'recent'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
                    :class="view === 'recent' ? 'bg-md-selected text-md-primary font-semibold' : 'text-md-on-surface-var hover:bg-md-primary/8'"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
                    <span class="msym text-xl">history</span>
                    <span>{{ __('files.recent') }}</span>
                </button>
                <button type="button" @click="view = 'trash'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
                    @dragover.prevent="if (dragItem) $event.currentTarget.classList.add('ring-2','ring-red-400')"
                    @dragleave="$event.currentTarget.classList.remove('ring-2','ring-red-400')"
                    @drop.prevent="$event.currentTarget.classList.remove('ring-2','ring-red-400'); if (dragItem) { trashItem(dragItem); dragItem = null; }"
                    :class="view === 'trash' ? 'bg-md-selected text-md-primary font-semibold' : 'text-md-on-surface-var hover:bg-md-primary/8'"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
                    <span class="msym text-xl">delete</span>
                    <span class="flex-1 text-left">{{ __('files.trash') }}</span>
                    <span x-show="trashCount > 0" x-cloak x-text="trashCount" class="rounded-full bg-md-outline-variant px-1.5 text-xs text-md-on-surface-var"></span>
                </button>
            </nav>
            <div x-show="usage" x-cloak class="border-t border-md-outline-variant pt-3">
                <template x-if="usage.quota > 0">
                    <div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-md-outline-variant">
                            <div class="h-full bg-accent" :style="'width:'+Math.min(100, Math.round((usage.used/usage.quota)*100))+'%'"></div>
                        </div>
                        <p class="mt-1 text-xs text-md-on-surface-var" x-text="'{{ __('files.storage_used', ['used' => '__U__', 'total' => '__T__']) }}'.replace('__U__', fmtSize(usage.used)).replace('__T__', fmtSize(usage.quota))"></p>
                    </div>
                </template>
                <template x-if="! usage.quota">
                    <p class="flex items-center gap-1.5 text-xs text-md-on-surface-var">
                        <span class="msym text-base shrink-0 text-md-on-surface-var">hard_drive</span>
                        <span x-text="'{{ __('files.storage_used_only', ['used' => '__U__']) }}'.replace('__U__', fmtSize(usage.used))"></span>
                    </p>
                </template>
            </div>
            </div>
        </x-sheet>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
        {{-- Toolbar --}}
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-md-outline-variant px-4 py-3">
            <div>
                <nav class="text-sm text-md-on-surface-var" x-show="view === 'files'">
                    <button type="button" @click="cwd = null" class="hover:underline">{{ __('files.all_files') }}</button>
                    <template x-for="crumb in breadcrumb" :key="crumb.id">
                        <span>
                            <span aria-hidden="true">/</span>
                            <button type="button" @click="cwd = crumb.id" class="hover:underline" x-text="crumb.name"></button>
                        </span>
                    </template>
                </nav>
                <h1 class="mt-0.5 text-lg font-semibold text-md-on-surface" x-text="view === 'files' ? (currentFolderName ?? @js(__('messages.nav.files'))) : ({favorites:@js(__('files.favorites')),recent:@js(__('files.recent')),trash:@js(__('files.trash'))})[view]"></h1>
            </div>
            {{-- Browser actions (hidden in the trash view); empty-trash shown there --}}
            <div class="flex flex-wrap items-center gap-2">
                <template x-if="view === 'files'">
                    <label title="{{ __('files.upload') }}" aria-label="{{ __('files.upload') }}"
                        class="cursor-pointer m3-state inline-flex h-10 w-10 items-center justify-center rounded-full bg-md-primary text-md-on-primary shadow-sm">
                        <span class="msym text-xl">upload</span>
                        <input type="file" multiple class="hidden" @change="upload($event.target.files); $event.target.value = ''">
                    </label>
                </template>
                <template x-if="view === 'files'">
                    <label title="{{ __('files.upload_folder') }}" aria-label="{{ __('files.upload_folder') }}"
                        class="cursor-pointer m3-state inline-flex h-10 w-10 items-center justify-center rounded-full text-md-on-surface-var">
                        <span class="msym text-xl">create_new_folder</span>
                        <input type="file" webkitdirectory multiple class="hidden" @change="uploadDirectory($event.target.files); $event.target.value = ''">
                    </label>
                </template>
                <x-icon-button name="archive-box-arrow-down" x-show="selected.length" x-cloak @click="downloadSelectionZip()" :aria-label="__('files.download_zip')" title="{{ __('files.download_zip') }}" />
                <x-icon-button name="archive-box" x-show="! selected.length && view === 'files' && cwd !== null" x-cloak @click="downloadFolderZip()" :aria-label="__('files.download_zip')" title="{{ __('files.download_zip') }}" />
                <x-icon-button name="circle-stack" @click="openStats()" :aria-label="__('files.storage')" title="{{ __('files.storage') }}" />
                <template x-if="view === 'files'">
                    <x-icon-button name="folder-plus" variant="solid" @click="openNewFolder()"
                        title="{{ __('files.new_folder') }}" aria-label="{{ __('files.new_folder') }}" />
                </template>
                <template x-if="trashView && trashCount > 0">
                    <x-button variant="danger" icon="trash" @click="emptyTrash()">{{ __('files.empty_trash') }}</x-button>
                </template>
            </div>
        </div>

        {{-- Search (client-side, over the loaded rows) + sort --}}
        <div class="flex flex-wrap items-center gap-3 border-b border-md-outline-variant px-4 py-2.5">
            <input type="search" x-model="query" @input="_debounceSearch()" @search="_debounceSearch()" placeholder="{{ __('files.search') }}"
                class="w-full sm:w-64 m3-field text-sm shadow-sm focus:border-accent focus:ring-accent">
            <div class="flex items-center gap-1 text-sm">
                <select x-model="sortKey" aria-label="{{ __('files.sort_by') }}" class="m3-field py-1.5 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    <option value="name">{{ __('files.sort_name') }}</option>
                    <option value="size">{{ __('files.sort_size') }}</option>
                    <option value="date">{{ __('files.sort_date') }}</option>
                </select>
                <button type="button" @click="sortDir = sortDir === 'asc' ? 'desc' : 'asc'" :title="sortDir === 'asc' ? @js(__('files.sort_asc')) : @js(__('files.sort_desc'))" class="rounded-md border border-md-outline p-1.5 text-md-on-surface-var hover:bg-accent/5">
                    <span x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                </button>
            </div>
            {{-- List / grid toggle --}}
            <div class="inline-flex rounded-xl bg-black/[0.04] dark:bg-white/10 p-0.5">
                <button type="button" @click="setLayout('list')" :class="layout === 'list' ? 'bg-md-surface-variant text-accent shadow-sm' : 'text-md-on-surface-var'" title="{{ __('files.view_list') }}" aria-label="{{ __('files.view_list') }}" class="rounded-lg p-1.5"><x-icon name="bars-3" class="h-4 w-4" /></button>
                <button type="button" @click="setLayout('grid')" :class="layout === 'grid' ? 'bg-md-surface-variant text-accent shadow-sm' : 'text-md-on-surface-var'" title="{{ __('files.view_grid') }}" aria-label="{{ __('files.view_grid') }}" class="rounded-lg p-1.5"><x-icon name="squares-2x2" class="h-4 w-4" /></button>
            </div>
            <span x-show="activeTag" x-cloak class="inline-flex items-center gap-2 rounded-full bg-blue-50 dark:bg-blue-950 px-3 py-1 text-xs text-blue-800 dark:text-blue-300">
                {{ __('files.filtered_by') }}: <span x-text="activeTag"></span>
                <button type="button" @click="activeTag = ''" aria-label="{{ __('common.clear') }}" class="text-blue-500 hover:text-blue-700"><span class="msym text-sm">close</span></button>
            </span>
            <span x-show="contentSearching" x-cloak class="inline-flex items-center gap-1.5 text-xs text-md-on-surface-var"><span class="msym text-sm animate-spin">history</span>{{ __('files.searching') }}</span>
        </div>

        {{-- Label filter bar (coloured taxonomy) --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-md-outline-variant px-4 py-2">
            <template x-for="l in fileLabels" :key="l.id">
                <button type="button" @click="toggleLabelFilter(l.id)"
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition"
                    :class="activeLabel === l.id ? 'text-white' : 'ring-black/10 dark:ring-white/15 text-md-on-surface-var hover:bg-black/[0.03] dark:hover:bg-white/5'"
                    :style="activeLabel === l.id ? ('background:' + l.color + ';box-shadow: inset 0 0 0 1px ' + l.color) : ('')">
                    <span class="h-2.5 w-2.5 rounded-full" :style="'background:' + l.color"></span><span x-text="l.name"></span>
                </button>
            </template>
            <button type="button" @click="openLabelModal()" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium text-accent hover:bg-accent/5">
                <span class="msym text-sm">add</span>{{ __('files.labels_manage') }}
            </button>
            <button type="button" @click="openSharedWithMe()" class="ml-auto inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium text-accent hover:bg-accent/5">
                <span class="msym text-sm">share</span>{{ __('files.shared_with_me') }}
            </button>
        </div>

        {{-- Browser --}}
        <div class="flex-1 overflow-y-auto">
        <x-alert variant="warning" x-show="error" x-cloak class="m-4" x-text="error" />
            <template x-if="rows.length === 0">
                <x-empty-state class="px-4 py-10" x-text="trashView ? '{{ __('files.trash_empty') }}' : '{{ __('files.empty_explorer') }}'" />
            </template>
            {{-- Grid view: tinted type chips (no server thumbnails needed) --}}
            <template x-if="layout === 'grid' && rows.length > 0">
                <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    <template x-for="row in rows" :key="row.kind + row.id">
                        <div class="group relative flex flex-col overflow-hidden rounded-xl border border-black/[0.06] dark:border-white/10 hover:border-accent/30"
                            :draggable="row.kind !== 'folder' || view === 'files' ? 'true' : 'false'"
                            @dragstart="dragItem = { kind: row.kind, id: row.id }" @dragend="dragItem = null"
                            @dragover.prevent="row.kind === 'folder' && dragItem && $event.currentTarget.classList.add('ring-2','ring-md-primary')"
                            @dragleave="$event.currentTarget.classList.remove('ring-2','ring-md-primary')"
                            @drop.prevent="$event.currentTarget.classList.remove('ring-2','ring-md-primary'); if (row.kind === 'folder' && dragItem) { dropInto(row.id); dragItem = null; }">
                            <button type="button" x-data="{ thumbOk: true }" @click="row.kind === 'folder' ? (view = 'files', cwd = row.id) : openFile(row)" class="flex aspect-square items-center justify-center overflow-hidden bg-md-surface-variant">
                                <img x-show="isImageRow(row) && thumbOk" :src="thumbUrl(row)" x-on:error="thumbOk = false" loading="lazy" alt="" class="h-full w-full object-cover">
                                <span x-show="! (isImageRow(row) && thumbOk)" class="relative flex h-11 w-11 items-center justify-center rounded-xl text-white shadow-sm" :style="'background:' + rowTint(row)">
                                    <span class="msym text-2xl" x-text="rowMsym(row)"></span>
                                </span>
                            </button>
                            <div class="flex flex-col gap-0 px-2 py-1.5">
                                <div class="flex items-center gap-1">
                                <button type="button" x-show="row.kind === 'file'" @click="toggleFavorite(row)" class="shrink-0" :class="row.favorite ? 'text-amber-500' : 'text-md-on-surface-var hover:text-md-on-surface-var'" :aria-label="row.favorite ? @js(__('files.unfavorite')) : @js(__('files.favorite'))">
                                    <span x-show="row.favorite"><span class="msym msym-fill text-sm">star</span></span>
                                    <span x-show="! row.favorite"><span class="msym text-sm">star</span></span>
                                </button>
                                <span class="min-w-0 flex-1 truncate text-xs text-md-on-surface-var" :title="row.name" x-text="row.name"></span>
                                <div class="relative shrink-0" x-data="{ menu: false, menuStyle: '', toggleMenu(e) { this.menu = ! this.menu; if (! this.menu) return; const r = e.currentTarget.getBoundingClientRect(); const left = Math.max(8, r.right - 176); this.menuStyle = `top: ${r.bottom + 4}px; left: ${left}px;`; this.$nextTick(() => { const h = this.$refs.menu?.offsetHeight ?? 0; if (r.bottom + 4 + h > window.innerHeight - 8 && r.top - h - 4 > 8) this.menuStyle = `top: ${r.top - h - 4}px; left: ${left}px;`; }); } }">
                                    <button type="button" @click="toggleMenu($event)" class="text-md-on-surface-var hover:text-md-on-surface dark:hover:text-md-on-surface" :aria-label="@js(__('files.actions'))"><span class="msym text-base">more_vert</span></button>
                                    <template x-teleport="body">
                                        <div x-ref="menu" x-show="menu" x-cloak @click.outside="menu = false" @scroll.window="menu = false" :style="menuStyle" class="fixed z-[60] w-44 rounded-xl border border-black/[0.06] dark:border-white/10 bg-md-surface py-1 text-left text-sm shadow-lg">
                                            @php $c = 'flex w-full items-center gap-2 px-3 py-1.5 text-left text-md-on-surface-var hover:bg-accent/5'; @endphp
                                            <button type="button" x-show="row.kind === 'file'" @click="download(row); menu = false" class="{{ $c }}"><span class="msym text-base">download</span>{{ __('files.download') }}</button>
                                            <button type="button" @click="openInfo(row); menu = false" class="{{ $c }}"><span class="msym text-base">info</span>{{ __('files.info') }}</button>
                                            <button type="button" @click="startRename(row); menu = false" class="{{ $c }}"><span class="msym text-base">edit</span>{{ __('files.rename') }}</button>
                                            <button type="button" @click="openMove(row); menu = false" class="{{ $c }}"><span class="msym text-base">drive_file_move</span>{{ __('files.move') }}</button>
                                            <button type="button" @click="openTags(row); menu = false" class="{{ $c }}"><span class="msym text-base">label</span>{{ __('files.edit_tags') }}</button>
                                            <button type="button" @click="openShare(row); menu = false" class="{{ $c }}"><span class="msym text-base">link</span>{{ __('files.share_public') }}</button>
                                            <button type="button" x-show="row.kind === 'folder'" @click="shareFolderWithUser(row); menu = false" class="{{ $c }}"><span class="msym text-base">share</span>{{ __('files.folder_share_add') }}</button>
                                            
                                            <button type="button" x-show="row.kind !== 'folder'" @click="openVersions(row); menu = false" class="{{ $c }}"><span class="msym text-base">history</span>{{ __('files.versions') }}</button>
                                            <button type="button" x-show="isMarkdown(row)" @click="openMigrate(row); menu = false" class="{{ $c }}"><span class="msym text-base">description</span>{{ __('files.migrate_to_note') }}</button>
                                            <button type="button" x-show="isPdf(row) && $store.paperless.configured" @click="openPaperless(row); menu = false" class="{{ $c }}"><span class="msym text-base">share</span>{{ __('paperless.send_to_paperless') }}</button>
                                            <button type="button" @click="confirmDelete(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 dark:text-red-400 hover:bg-accent/5"><span class="msym text-base">delete</span>{{ __('common.delete') }}</button>
                                        </div>
                                    </template>
                                </div>
                                </div>
                                <span class="truncate text-[10px] text-md-on-surface-var" x-text="rowLabel(row)"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <div x-show="layout === 'list'" class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <table x-show="rows.length > 0" class="min-w-full divide-y divide-md-outline-variant text-sm">
                <thead class="bg-md-surface-variant/80/60 text-left text-xs font-medium uppercase tracking-wider text-md-on-surface-var">
                    <tr>
                        <th class="px-4 py-3"><input type="checkbox" @change="toggleAll($event)" aria-label="{{ __('files.select_all') }}" class="rounded border-md-outline text-md-on-surface focus:ring-accent"></th>
                        <th class="px-4 py-3">
                            <button type="button" @click="sortBy('name')" class="uppercase hover:text-md-on-surface">
                                {{ __('files.col_name') }} <span x-text="sortArrow('name')"></span>
                            </button>
                        </th>
                        <th class="hidden px-4 py-3 sm:table-cell">{{ __('files.col_type') }}</th>
                        <th class="hidden px-4 py-3 text-right sm:table-cell">
                            <button type="button" @click="sortBy('size')" class="uppercase hover:text-md-on-surface">
                                {{ __('files.col_size') }} <span x-text="sortArrow('size')"></span>
                            </button>
                        </th>
                        <th class="hidden px-4 py-3 md:table-cell">{{ __('files.col_tags') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-md-outline-variant">
                    {{-- Parent-folder shortcut, like "cd .." — virtual row, drop target to move items up. --}}
                    <template x-if="view === 'files' && cwd !== null && query === '' && activeTag === ''">
                        <tr class="cursor-pointer text-md-on-surface-var hover:bg-accent/5" @click="cwd = parentFolderId"
                            @dragover="if (dragItem) $event.preventDefault()" @drop.prevent="dropInto(parentFolderId)">
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 font-medium">
                                <span class="flex items-center gap-2">
                                    <svg class="h-5 w-5 shrink-0 text-md-on-surface-var" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
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
                        <tr class="cursor-pointer hover:bg-accent/5" x-data="{ menu: false, menuStyle: '', toggleMenu(e) { this.menu = ! this.menu; if (! this.menu) return; const r = e.currentTarget.getBoundingClientRect(); const left = Math.max(8, r.right - 176); this.menuStyle = `top: ${r.bottom + 4}px; left: ${left}px;`; this.$nextTick(() => { const h = this.$refs.menu?.offsetHeight ?? 0; if (r.bottom + 4 + h > window.innerHeight - 8 && r.top - h - 4 > 8) this.menuStyle = `top: ${r.top - h - 4}px; left: ${left}px;`; }); } }"
                            :draggable="renaming === row.id ? 'false' : 'true'"
                            @dragstart.stop="onDragStart($event, row)" @dragend="onDragEnd()"
                            @dragover="if (row.kind === 'folder' && dragItem && !(dragItem.kind === 'folder' && dragItem.id === row.id)) $event.preventDefault()"
                            @drop.prevent="row.kind === 'folder' && dropInto(row.id)"
                            @click="if (renaming !== row.id) { row.kind === 'folder' ? (view = 'files', cwd = row.id) : openFile(row) }">
                            <td class="px-4 py-3" @click.stop><input type="checkbox" :value="rowKey(row)" x-model="selected" class="rounded border-md-outline text-md-on-surface focus:ring-accent"></td>
                            <td class="px-4 py-3 font-medium text-md-on-surface">
                                <span class="flex min-w-0 items-center gap-2.5" x-show="renaming !== row.id">
                                    <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" :style="'background:' + rowTint(row)">
                                        <span class="msym text-xl" x-text="rowMsym(row)"></span>
                                    </span>
                                    <span class="truncate" x-text="row.name"></span>
                                </span>
                                <form x-show="renaming === row.id" x-cloak class="flex gap-2" @click.stop @submit.prevent="applyRename(row)">
                                    <input type="text" x-model="renameValue" x-ref="rename"
                                        class="w-full m3-field text-sm shadow-sm focus:border-accent focus:ring-accent">
                                    <x-button type="submit" variant="primary">{{ __('files.save') }}</x-button>
                                    <x-icon-button name="x-mark" @click="renaming = null" aria-label="{{ __('common.cancel') }}" />
                                </form>
                            </td>
                            <td class="hidden px-4 py-3 text-md-on-surface-var sm:table-cell" x-text="rowLabel(row)"></td>
                            <td class="hidden px-4 py-3 text-right text-md-on-surface-var sm:table-cell" x-text="row.kind === 'folder' ? '—' : fmtSize(row.size)"></td>
                            <td class="hidden px-4 py-3 md:table-cell" @click.stop>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="tag in (row.tags ?? [])" :key="tag">
                                        <button type="button" @click="activeTag = tag"
                                            class="inline-flex items-center rounded bg-md-surface-variant px-1.5 py-0.5 text-xs text-md-on-surface-var hover:bg-md-surface-variant" x-text="tag"></button>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right" @click.stop>
                                {{-- Trash view: restore / delete-forever only --}}
                                <div x-show="trashView" class="flex items-center justify-end gap-1">
                                    <x-icon-button name="arrow-uturn-left" size="lg" @click="restore(row)" title="{{ __('files.restore') }}" aria-label="{{ __('files.restore') }}" />
                                    <x-icon-button name="trash" tone="red" size="lg" @click="purge(row)" title="{{ __('files.delete_forever') }}" aria-label="{{ __('files.delete_forever') }}" />
                                </div>
                                <div x-show="! trashView" class="flex items-center justify-end gap-1">
                                    <button type="button" x-show="row.kind === 'file'" @click="toggleFavorite(row)" :title="row.favorite ? @js(__('files.unfavorite')) : @js(__('files.favorite'))" :aria-label="row.favorite ? @js(__('files.unfavorite')) : @js(__('files.favorite'))" class="min-h-11 min-w-11 inline-flex items-center justify-center rounded p-2.5 hover:bg-accent/5" :class="row.favorite ? 'text-amber-500' : 'text-md-on-surface-var hover:text-md-on-surface'">
                                        <span x-show="row.favorite"><span class="msym msym-fill text-base">star</span></span>
                                        <span x-show="! row.favorite"><span class="msym text-base">star</span></span>
                                    </button>
                                    <x-icon-button name="eye" size="lg" x-show="row.kind === 'file'" @click="openFile(row)" title="{{ __('files.preview') }}" aria-label="{{ __('files.preview') }}" />
                                    <x-icon-button name="info" size="lg" @click="openInfo(row)" title="{{ __('files.info') }}" aria-label="{{ __('files.info') }}" />
                                    <x-icon-button name="arrow-down-tray" size="lg" x-show="row.kind === 'file'" @click="download(row)" title="{{ __('files.download') }}" aria-label="{{ __('files.download') }}" />
                                    <div class="relative inline-block text-left">
                                        <x-icon-button name="ellipsis" size="lg" @click="toggleMenu($event)" @keydown.escape="menu = false" aria-label="{{ __('files.actions') }}" />
                                        {{-- Teleported to the body so the table's overflow-x-auto wrapper cannot clip the menu. --}}
                                        <template x-teleport="body">
                                        <div x-ref="menu" x-show="menu" x-cloak @click.outside="menu = false" @keydown.escape.window="menu = false" @scroll.window="menu = false" @resize.window="menu = false" :style="menuStyle" class="fixed z-[60] w-44 rounded-xl border border-black/[0.06] dark:border-white/10 bg-md-surface py-1 text-left text-sm shadow-lg">
                                            @php $c = 'flex w-full items-center gap-2 px-3 py-1.5 text-left text-md-on-surface-var hover:bg-accent/5'; @endphp
                                            <button type="button" x-show="row.kind === 'file'" @click="download(row); menu = false" class="{{ $c }}"><span class="msym text-base">download</span>{{ __('files.download') }}</button>
                                            <button type="button" @click="openInfo(row); menu = false" class="{{ $c }}"><span class="msym text-base">info</span>{{ __('files.info') }}</button>
                                            <button type="button" @click="startRename(row); menu = false" class="{{ $c }}"><span class="msym text-base">edit</span>{{ __('files.rename') }}</button>
                                            <button type="button" @click="openMove(row); menu = false" class="{{ $c }}"><span class="msym text-base">drive_file_move</span>{{ __('files.move') }}</button>
                                            <button type="button" @click="openTags(row); menu = false" class="{{ $c }}"><span class="msym text-base">label</span>{{ __('files.edit_tags') }}</button>
                                            <button type="button" @click="openShare(row); menu = false" class="{{ $c }}"><span class="msym text-base">link</span>{{ __('files.share_public') }}</button>
                                            <button type="button" x-show="row.kind === 'folder'" @click="shareFolderWithUser(row); menu = false" class="{{ $c }}"><span class="msym text-base">share</span>{{ __('files.folder_share_add') }}</button>
                                            
                                            <button type="button" x-show="row.kind !== 'folder'" @click="openVersions(row); menu = false" class="{{ $c }}"><span class="msym text-base">history</span>{{ __('files.versions') }}</button>
                                            <button type="button" x-show="isMarkdown(row)" @click="openMigrate(row); menu = false" class="{{ $c }}"><span class="msym text-base">description</span>{{ __('files.migrate_to_note') }}</button>
                                            <button type="button" x-show="isPdf(row) && $store.paperless.configured" @click="openPaperless(row); menu = false" class="{{ $c }}"><span class="msym text-base">share</span>{{ __('paperless.send_to_paperless') }}</button>
                                            <button type="button" @click="confirmDelete(row); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 dark:text-red-400 hover:bg-accent/5"><span class="msym text-base">delete</span>{{ __('common.delete') }}</button>
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

    {{-- Bulk bar: floats at the bottom so actions are reachable without scrolling. --}}
    <div x-show="selected.length && ! trashView" x-cloak x-transition
        :class="uploads.length ? 'bottom-72' : 'bottom-5'"
        class="fixed inset-x-0 z-40 mx-auto flex w-max max-w-[95vw] flex-wrap items-center justify-center gap-3 rounded-full border border-black/[0.06] dark:border-white/10 bg-md-surface px-4 py-2 shadow-xl">
        <span class="text-sm font-medium text-md-on-surface-var"><span x-text="selected.length"></span> {{ __('files.selected_word') }}</span>
        <x-icon-button name="arrows-right-left" variant="solid" @click="openMove(null)" title="{{ __('files.move') }}" aria-label="{{ __('files.move') }}" />
        <x-icon-button name="trash" tone="red" @click="confirmDelete(null)" title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}" />
    </div>

    {{-- Upload tray (fixed bottom-right, per-file state) --}}
    <div x-show="uploads.length" x-cloak class="fixed bottom-5 right-5 z-[950] w-80 overflow-hidden rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface shadow-xl">
        <div class="flex items-center justify-between border-b border-md-outline-variant px-4 py-2 text-sm font-medium text-md-on-surface-var">
            <span x-show="uploading">{{ __('files.uploading') }} (<span x-text="uploadsDone"></span>/<span x-text="uploads.length"></span>)</span>
            <span x-show="! uploading">{{ __('files.upload_done') }}</span>
            <button type="button" x-show="! uploading" @click="dismissUploads()" class="text-md-on-surface-var hover:text-md-on-surface dark:text-md-on-surface-var dark:hover:text-md-on-surface">{{ __('files.upload_dismiss') }}</button>
        </div>
        <div class="max-h-64 space-y-2 overflow-y-auto p-3">
            <template x-for="(u, i) in uploads" :key="i">
                <div>
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="truncate text-md-on-surface-var" x-text="u.name"></span>
                        <span class="shrink-0" :class="{'text-green-600 dark:text-green-400': u.state==='done', 'text-red-600 dark:text-red-400': u.state==='error', 'text-md-on-surface-var': u.state==='uploading'||u.state==='pending'}">
                            <template x-if="u.state==='done'"><span class="msym text-base">check</span></template>
                            <template x-if="u.state==='error'"><span class="msym text-base">close</span></template>
                            <span x-show="u.state==='uploading'" x-text="u.progress + '%'"></span>
                            <span x-show="u.state==='pending'">…</span>
                        </span>
                    </div>
                    <div class="mt-1 h-1.5 w-full rounded bg-md-surface-variant">
                        <div class="h-1.5 rounded transition-all" :class="{'bg-green-500': u.state==='done', 'bg-red-500': u.state==='error', 'bg-md-primary': u.state==='uploading'||u.state==='pending'}"
                            :style="`width: ${u.state==='pending' ? 4 : (u.state==='uploading' ? u.progress : 100)}%`"></div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Move modal --}}
    <template x-teleport="body">
        <div x-show="moveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="moveOpen = false">
            <div class="absolute inset-0 bg-black/40" @click="moveOpen = false"></div>
            <div class="relative flex max-h-[80vh] w-full max-w-md flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface shadow-xl">
                <h3 class="border-b border-md-outline-variant px-6 py-4 text-base font-semibold text-md-on-surface">{{ __('files.move_title') }} <span class="text-md-on-surface-var">(<span x-text="moveRefs.length"></span>)</span></h3>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-accent/5">
                        <input type="radio" name="move_target" value="" x-model="moveTarget" class="border-md-outline text-md-on-surface focus:ring-accent">
                        {{ __('files.root_folder') }}
                    </label>
                    <template x-for="opt in moveOptions" :key="opt.id">
                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-accent/5">
                            <input type="radio" name="move_target" :value="opt.id" x-model="moveTarget" class="border-md-outline text-md-on-surface focus:ring-accent">
                            <span x-text="opt.label"></span>
                        </label>
                    </template>
                </div>
                <div class="flex justify-end gap-3 border-t border-md-outline-variant px-6 py-4">
                    <x-button variant="secondary" @click="moveOpen = false">{{ __('common.cancel') }}</x-button>
                    <x-button variant="primary" @click="applyMove()">{{ __('files.move_here') }}</x-button>
                </div>
            </div>
        </div>
    </template>

    {{-- Viewer / editor: image, PDF, video, audio or editable text (plaintext bytes) --}}
    <template x-teleport="body">
        <div x-show="viewer.open" x-cloak class="fixed inset-0 z-[1050] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeViewer()"
            @keydown.arrow-left.window="viewerHasGallery && viewerStep(-1)" @keydown.arrow-right.window="viewerHasGallery && viewerStep(1)">
            <div class="absolute inset-0 bg-black/50" @click="closeViewer()"></div>
            <div class="relative flex max-h-[92vh] w-full max-w-5xl flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface shadow-xl">
                <div class="flex items-center justify-between gap-3 border-b border-md-outline-variant px-5 py-3">
                    <h3 class="truncate text-base font-semibold text-md-on-surface" x-text="viewer.row?.name"></h3>
                    <div class="flex shrink-0 items-center gap-3">
                        <span x-show="viewerHasGallery" x-cloak class="text-xs tabular-nums text-md-on-surface-var" x-text="`${viewerIndex + 1} / ${viewerImages.length}`"></span>
                        <x-icon-button name="share" x-show="viewer.kind === 'pdf' && $store.paperless.configured" @click="openPaperless(viewer.row)" title="{{ __('paperless.send_to_paperless') }}" aria-label="{{ __('paperless.send_to_paperless') }}" />
                        <x-icon-button name="arrow-down-tray" @click="download(viewer.row)" title="{{ __('files.download') }}" aria-label="{{ __('files.download') }}" />
                        <x-icon-button name="x-mark" @click="closeViewer()" title="{{ __('common.close') }}" aria-label="{{ __('common.close') }}" />
                    </div>
                </div>
                <div class="flex min-h-0 flex-1">
                <div class="min-h-0 flex-1 overflow-auto p-4">
                    <div x-show="viewer.kind === 'image'" x-cloak class="relative">
                        <img :src="viewer.src" :alt="viewer.row?.name"
                            :class="viewerHasGallery ? 'cursor-pointer' : ''"
                            @click="viewerHasGallery && viewerStep(1)"
                            class="mx-auto max-h-[75vh] rounded object-contain">
                        <button type="button" x-show="viewerHasGallery" @click.stop="viewerStep(-1)"
                            title="{{ __('files.prev_image') }}" aria-label="{{ __('files.prev_image') }}"
                            class="absolute left-1 top-1/2 -translate-y-1/2 rounded-full bg-black/40 p-2 text-white hover:bg-black/60"><span class="msym text-xl">chevron_left</span></button>
                        <button type="button" x-show="viewerHasGallery" @click.stop="viewerStep(1)"
                            title="{{ __('files.next_image') }}" aria-label="{{ __('files.next_image') }}"
                            class="absolute right-1 top-1/2 -translate-y-1/2 rounded-full bg-black/40 p-2 text-white hover:bg-black/60"><span class="msym text-xl">chevron_right</span></button>
                    </div>
                    <template x-if="viewer.kind === 'pdf'">
                        <iframe :src="viewer.src || 'about:blank'" class="h-[75vh] w-full rounded"></iframe>
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
                        <textarea x-model="editorText" spellcheck="false"
                            class="h-[60vh] w-full rounded-lg border border-md-outline bg-md-surface p-3 font-mono text-sm leading-relaxed text-md-on-surface focus:border-accent focus:ring-accent"></textarea>
                        <div class="mt-3 flex items-center gap-3">
                            <x-button variant="primary" @click="saveText()" ::disabled="viewer.saving">{{ __('files.save') }}</x-button>
                            <span x-show="viewer.saved" x-cloak class="text-green-600"><span class="msym text-base">check</span></span>
                        </div>
                    </div>
                    <p x-show="viewer.kind === 'none'" x-cloak class="py-10 text-center text-sm text-md-on-surface-var">{{ __('files.encrypted_no_preview') }}</p>
                </div>

                {{-- Info sidebar: metadata + tags/labels for the previewed file --}}
                <aside x-show="viewer.row" x-cloak class="hidden w-72 shrink-0 overflow-y-auto border-l border-md-outline-variant p-4 text-sm sm:block">
                    <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-md-on-surface-var">{{ __('files.info_title') }}</h4>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-md-on-surface-var">{{ __('files.info_name') }}</dt>
                            <dd class="break-words text-md-on-surface" x-text="viewer.row?.name"></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-md-on-surface-var">{{ __('files.info_mime') }}</dt>
                            <dd class="break-words font-mono text-xs text-md-on-surface-var" x-text="viewer.row?.mime || '—'"></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-md-on-surface-var">{{ __('files.info_size') }}</dt>
                            <dd class="tabular-nums text-md-on-surface" x-text="fmtSize(viewer.row?.size || 0)"></dd>
                        </div>
                        <div x-show="viewer.row?.created">
                            <dt class="text-xs text-md-on-surface-var">{{ __('files.info_uploaded') }}</dt>
                            <dd class="text-md-on-surface" x-text="fmtDate(viewer.row?.created)"></dd>
                        </div>
                        <div x-show="viewer.row?.updated">
                            <dt class="text-xs text-md-on-surface-var">{{ __('files.info_modified') }}</dt>
                            <dd class="text-md-on-surface" x-text="fmtDate(viewer.row?.updated)"></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-md-on-surface-var">{{ __('files.info_tags') }}</dt>
                            <dd class="mt-1 flex flex-wrap gap-1">
                                <template x-for="t in (viewer.row?.tags || [])" :key="t">
                                    <x-badge variant="gray"><span x-text="t"></span></x-badge>
                                </template>
                                <span x-show="! (viewer.row?.tags || []).length" class="text-md-on-surface-var">—</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-md-on-surface-var">{{ __('files.info_labels') }}</dt>
                            <dd class="mt-1 flex flex-wrap gap-1">
                                <template x-for="l in fileLabelObjects(viewer.row || {})" :key="l.id">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium text-white" :style="`background:${l.color}`" x-text="l.name"></span>
                                </template>
                                <span x-show="! fileLabelObjects(viewer.row || {}).length" class="text-md-on-surface-var">—</span>
                            </dd>
                        </div>
                        <div x-show="viewer.row?.note">
                            <dt class="text-xs text-md-on-surface-var">{{ __('files.note') }}</dt>
                            <dd class="whitespace-pre-line break-words text-md-on-surface" x-text="viewer.row?.note"></dd>
                        </div>
                        <div x-show="viewer.row?.favorite" class="flex items-center gap-1.5 text-amber-500">
                            <span class="msym msym-fill text-base">star</span><span>{{ __('files.favorite') }}</span>
                        </div>
                    </dl>
                </aside>
                </div>
            </div>
        </div>
    </template>

    {{-- Tags modal --}}
    <template x-teleport="body">
        <div x-show="tagsOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="tagsOpen = false">
            <div class="absolute inset-0 bg-black/40" @click="tagsOpen = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface p-6 shadow-xl">
                <h3 class="text-base font-semibold text-md-on-surface">{{ __('files.edit_tags') }}</h3>
                <x-tag-field list="file-tags" :placeholder="__('files.tags_placeholder')" class="mt-4" />
                <datalist id="file-tags">
                    <template x-for="tag in allTags" :key="tag"><option :value="tag"></option></template>
                </datalist>
                <div class="mt-5 flex justify-end gap-3">
                    <x-button variant="secondary" @click="tagsOpen = false">{{ __('common.cancel') }}</x-button>
                    <x-button variant="primary" @click="applyTags()">{{ __('files.save') }}</x-button>
                </div>
            </div>
        </div>
    </template>


    {{-- Versions modal --}}
    <template x-teleport="body">
        <div x-show="versions.open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" @keydown.escape.window="versions.open = false">
            <div class="absolute inset-0 bg-black/40" @click="versions.open = false"></div>
            <div class="relative my-16 w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface shadow-xl">
                <h3 class="border-b border-md-outline-variant px-6 py-4 text-base font-semibold text-md-on-surface">{{ __('files.versions') }} <span class="text-md-on-surface-var" x-text="versions.row?.name"></span></h3>
                <div class="max-h-[60vh] overflow-y-auto px-6 py-4">
                    <p x-show="!versions.loading && !versions.list.length" x-cloak class="text-sm text-md-on-surface-var">{{ __('files.versions_none') }}</p>
                    <ul class="divide-y divide-md-outline-variant">
                        <template x-for="v in versions.list" :key="v.id">
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="min-w-0">
                                    <span class="block text-xs text-md-on-surface-var" x-text="v.created_at ? new Date(v.created_at).toLocaleString() : ''"></span>
                                    <span class="text-md-on-surface-var" x-text="fmtSize(v.size)"></span>
                                </span>
                                <span class="flex shrink-0 items-center gap-2">
                                    <x-button variant="secondary" @click="downloadVersion(v)">{{ __('files.version_download') }}</x-button>
                                    <x-button variant="primary" @click="restoreVersion(v)">{{ __('files.version_restore') }}</x-button>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
                <div class="flex justify-end border-t border-md-outline-variant px-6 py-3">
                    <x-button variant="secondary" @click="versions.open = false">{{ __('common.close') }}</x-button>
                </div>
            </div>
        </div>
    </template>

    {{-- Information modal --}}
    <template x-teleport="body">
        <div x-show="infoOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="infoOpen = false">
            <div class="absolute inset-0 bg-black/40" @click="infoOpen = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface p-6 shadow-xl" x-show="infoRow">
                <h3 class="text-base font-semibold text-md-on-surface">{{ __('files.info_title') }}</h3>
                <dl class="mt-4 divide-y divide-md-outline-variant text-sm">
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-md-on-surface-var">{{ __('files.info_name') }}</dt>
                        <dd class="min-w-0 break-all text-right font-medium text-md-on-surface" x-text="infoRow?.name"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-md-on-surface-var">{{ __('files.info_type') }}</dt>
                        <dd class="text-right text-md-on-surface" x-text="infoRow?.kind === 'folder' ? @js(__('files.folder')) : typeLabel(infoRow)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.kind === 'file'">
                        <dt class="text-md-on-surface-var">{{ __('files.info_mime') }}</dt>
                        <dd class="min-w-0 break-all text-right text-md-on-surface" x-text="infoRow?.mime"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.kind === 'file'">
                        <dt class="text-md-on-surface-var">{{ __('files.info_size') }}</dt>
                        <dd class="text-right text-md-on-surface" x-text="fmtSize(infoRow?.size)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.kind === 'folder'">
                        <dt class="text-md-on-surface-var">{{ __('files.info_items') }}</dt>
                        <dd class="text-right text-md-on-surface" x-text="folderItemCount(infoRow)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="infoRow?.created">
                        <dt class="text-md-on-surface-var">{{ __('files.info_uploaded') }}</dt>
                        <dd class="text-right text-md-on-surface" x-text="fmtDate(infoRow?.created)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-md-on-surface-var">{{ __('files.info_folder') }}</dt>
                        <dd class="text-right text-md-on-surface" x-text="infoFolderPath(infoRow)"></dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2" x-show="(infoRow?.tags ?? []).length">
                        <dt class="text-md-on-surface-var">{{ __('files.info_tags') }}</dt>
                        <dd class="text-right text-md-on-surface" x-text="(infoRow?.tags ?? []).join(', ')"></dd>
                    </div>
                </dl>
                <div x-show="infoRow?.kind === 'file'" class="mt-4">
                    <label class="block text-sm font-medium text-md-on-surface-var">{{ __('files.note') }}</label>
                    <textarea x-model="infoNote" @blur="saveNote()" rows="3" placeholder="{{ __('files.note_placeholder') }}"
                        class="mt-1 w-full m3-field text-sm shadow-sm focus:border-accent focus:ring-accent"></textarea>
                </div>
                <div x-show="infoRow?.kind === 'file' && fileLabels.length" class="mt-4">
                    <label class="block text-sm font-medium text-md-on-surface-var">{{ __('files.info_labels') }}</label>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <template x-for="l in fileLabels" :key="l.id">
                            <button type="button" @click="toggleFileLabel(infoRow, l.id)"
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs ring-1 ring-inset transition"
                                :class="(infoRow?.labelIds || []).includes(l.id) ? 'text-white' : 'ring-black/10 dark:ring-white/15 text-md-on-surface-var'"
                                :style="(infoRow?.labelIds || []).includes(l.id) ? ('background:' + l.color) : ''">
                                <span class="h-2 w-2 rounded-full" :style="'background:' + l.color"></span><span x-text="l.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <x-button variant="secondary" @click="infoOpen = false">{{ __('common.close') }}</x-button>
                </div>
            </div>
        </div>
    </template>

    {{-- Migrate a Markdown file to a note --}}
    <template x-teleport="body">
        <div x-show="migrateOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="migrateOpen = false">
            <div class="absolute inset-0 bg-black/40" @click="migrateOpen = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface p-6 shadow-xl">
                <h3 class="text-base font-semibold text-md-on-surface">{{ __('files.migrate_title') }}</h3>
                <p class="mt-2 text-sm text-md-on-surface-var">
                    <span x-text="migrateRow?.name"></span> — {{ __('files.migrate_intro') }}
                </p>
                <label class="mt-4 flex items-center gap-2 text-sm text-md-on-surface-var">
                    <input type="checkbox" x-model="migrateDelete" class="rounded border-md-outline text-md-on-surface focus:ring-accent">
                    {{ __('files.migrate_delete_after') }}
                </label>
                <div class="mt-5 flex justify-end gap-3">
                    <x-button variant="secondary" @click="migrateOpen = false">{{ __('common.cancel') }}</x-button>
                    <x-button variant="primary" @click="applyMigrate()" ::disabled="migrateBusy">{{ __('files.migrate_confirm') }}</x-button>
                </div>
            </div>
        </div>
    </template>

    {{-- Delete confirm --}}
    <template x-teleport="body">
        <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteOpen = false">
            <div class="absolute inset-0 bg-black/40" @click="deleteOpen = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface p-6 shadow-xl">
                <h3 class="text-base font-semibold text-md-on-surface">{{ __('common.confirm_title') }}</h3>
                <p class="mt-2 text-sm text-md-on-surface-var">
                    <span class="font-medium" x-text="deleteRefs.map(r => r.name).join(', ')"></span>
                </p>
                <p class="mt-2 text-sm text-md-on-surface-var">{{ __('files.delete_choice_hint') }}</p>
                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <x-button variant="secondary" @click="deleteOpen = false">{{ __('common.cancel') }}</x-button>
                    <x-button variant="danger" @click="applyDelete(true)">{{ __('files.delete_forever') }}</x-button>
                    <x-button variant="primary" @click="applyDelete(false)">{{ __('files.move_to_trash') }}</x-button>
                </div>
            </div>
        </div>
    </template>

    {{-- New folder modal --}}
    <template x-teleport="body">
        <div x-show="newFolderModal" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center p-4"
             role="dialog" aria-modal="true" @keydown.escape.window="newFolderModal = false">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="newFolderModal = false"></div>
            <div class="relative w-full max-w-sm rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface p-5 shadow-xl space-y-4">
                <h3 class="text-base font-semibold text-md-on-surface">{{ __('files.new_folder') }}</h3>
                <div>
                    <label class="block text-xs font-medium text-md-on-surface-var mb-1">{{ __('files.col_name') }}</label>
                    <input type="text" x-model="newFolderName" x-ref="newFolderInput"
                        @keydown.enter.prevent="submitNewFolder()"
                        placeholder="{{ __('files.new_folder') }}"
                        class="w-full rounded-lg border border-md-outline bg-md-surface px-3 py-2 text-sm text-md-on-surface focus:border-accent focus:ring-accent">
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <x-button variant="secondary" @click="newFolderModal = false">{{ __('common.cancel') }}</x-button>
                    <x-button variant="primary" @click="submitNewFolder()" ::disabled="! newFolderName.trim()">{{ __('files.new_folder') }}</x-button>
                </div>
            </div>
        </div>
    </template>

    {{-- Public share link modal (plaintext bytes; optional password gate) --}}
    <template x-teleport="body">
        <div x-show="share.open" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeShare()">
            <div class="absolute inset-0 bg-black/40" @click="closeShare()"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface p-6 shadow-xl">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-base font-semibold text-md-on-surface">{{ __('files.share_dialog_title') }}</h3>
                    <x-icon-button name="x-mark" @click="closeShare()" aria-label="{{ __('files.share_close') }}" />
                </div>
                <p class="mt-1 text-xs text-md-on-surface-var">{{ __('files.share_intro') }}</p>
                <p class="mt-2 text-sm font-medium text-md-on-surface-var truncate" x-text="share.name"></p>

                <div x-show="share.link" x-cloak class="mt-4 rounded-xl border border-md-outline-variant p-3">
                    <label class="text-xs uppercase tracking-wide text-md-on-surface-var">{{ __('files.share_link_label') }}</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="text" readonly :value="share.link" @focus="$event.target.select()" class="w-full rounded-md border-md-outline-variant dark:bg-md-surface-variant text-xs text-md-on-surface-var">
                        <x-icon-button name="clipboard" @click="copyShareLink()" title="{{ __('files.share_copy') }}" aria-label="{{ __('files.share_copy') }}" />
                    </div>
                    <p class="mt-2 text-[11px] leading-relaxed text-md-on-surface-var">{{ __('files.share_active_hint') }}</p>
                </div>

                <div class="mt-4 space-y-3">
                    <label class="flex items-center gap-2 text-sm text-md-on-surface-var">
                        <input type="checkbox" x-model="share.allowDownload" class="h-4 w-4 rounded border-md-outline text-accent focus:ring-0">
                        {{ __('files.share_allow_download') }}
                    </label>
                    <label class="block text-xs text-md-on-surface-var">{{ __('files.share_password') }}
                        <input type="password" x-model="share.password" autocomplete="new-password" :placeholder="share.current?.needsPassword ? '{{ __('files.share_password_set') }}' : '{{ __('files.share_password_hint') }}'"
                            class="mt-1 block w-full m3-field dark:bg-md-surface-variant text-sm text-md-on-surface focus:border-accent focus:ring-accent">
                    </label>
                    <label class="block text-xs text-md-on-surface-var">{{ __('files.share_expiry') }}
                        <input type="datetime-local" x-model="share.expiresAt"
                            class="mt-1 block w-full m3-field dark:bg-md-surface-variant text-sm text-md-on-surface focus:border-accent focus:ring-accent">
                    </label>
                </div>

                <p x-show="share.error" x-cloak class="mt-3 text-sm text-red-600 dark:text-red-400" x-text="share.error"></p>

                <div class="mt-5 flex items-center justify-between gap-2">
                    <x-button variant="danger" x-show="share.current" x-cloak @click="revokeShare()" ::disabled="share.busy">{{ __('files.share_revoke') }}</x-button>
                    <div class="ml-auto flex gap-2">
                        <x-button variant="secondary" @click="closeShare()">{{ __('files.share_close') }}</x-button>
                        <x-button variant="primary" x-show="! share.current" @click="createShare()" ::disabled="share.busy"><span class="msym text-base">link</span>{{ __('files.share_create_link') }}</x-button>
                        <x-button variant="primary" x-show="share.current" x-cloak @click="updateShare()" ::disabled="share.busy">{{ __('files.share_update') }}</x-button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Storage stats: size-by-type breakdown + suspected duplicates --}}
    <template x-teleport="body">
        <div x-show="stats.open" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="stats.open = false">
            <div class="absolute inset-0 bg-black/40" @click="stats.open = false"></div>
            <div class="relative flex max-h-[88vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface shadow-xl">
                {{-- Header --}}
                <div class="flex items-center justify-between gap-3 border-b border-md-outline-variant px-6 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-md-on-surface">{{ __('files.storage') }}</h3>
                        <p class="mt-0.5 text-xs text-md-on-surface-var" x-text="'{{ __('files.storage_used_only', ['used' => '__U__']) }}'.replace('__U__', fmtSize(stats.used))"></p>
                    </div>
                    <x-icon-button name="x-mark" @click="stats.open = false" aria-label="{{ __('files.share_close') }}" />
                </div>

                <div class="min-h-0 flex-1 overflow-auto px-6 py-5 space-y-6">
                    {{-- By type: a labelled bar per category (share of total) --}}
                    <section>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-md-on-surface-var">{{ __('files.storage_by_type') }}</h4>
                        <div class="space-y-2">
                            <template x-for="r in statsRows" :key="r.type">
                                <div>
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="text-md-on-surface-var" x-text="typeName(r.type)"></span>
                                        <span class="tabular-nums text-md-on-surface-var" x-text="fmtSize(r.size)"></span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10">
                                        <div class="h-full rounded-full bg-accent" :style="`width:${stats.used ? Math.max(2, Math.round(r.size / stats.used * 100)) : 0}%`"></div>
                                    </div>
                                </div>
                            </template>
                            <p x-show="! statsRows.length" x-cloak class="text-xs text-md-on-surface-var">—</p>
                        </div>
                    </section>

                    {{-- Possible duplicates: full path per copy + trash actions --}}
                    <section>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-md-on-surface-var">
                                {{ __('files.duplicates') }} <span class="text-md-on-surface-var" x-text="'(' + stats.duplicates.length + ')'"></span>
                            </h4>
                            <x-button x-show="dupeExtras > 0" variant="danger" size="sm" icon="trash" @click="trashAllDupes()" ::disabled="stats.trashing">
                                <x-icon x-show="stats.trashing" x-cloak name="arrow-path" class="h-4 w-4 animate-spin" />
                                <span x-show="! stats.trashing" x-text="'{{ __('files.dupes_trash_all', ['n' => '__N__']) }}'.replace('__N__', dupeExtras)"></span>
                                <span x-show="stats.trashing" x-cloak x-text="stats.trashDone + ' / ' + stats.trashTotal"></span>
                            </x-button>
                        </div>
                        {{-- Busy overlay while duplicates are being trashed --}}
                        <div x-show="stats.trashing" x-cloak class="mb-3 flex items-center gap-2 rounded-lg bg-accent/5 px-3 py-2 text-xs text-accent">
                            <span class="msym text-base animate-spin">history</span>
                            <span x-text="'{{ __('files.dupes_trashing') }} ' + stats.trashDone + ' / ' + stats.trashTotal"></span>
                        </div>
                        <div class="space-y-3" :class="stats.trashing ? 'pointer-events-none opacity-50' : ''">
                            <template x-for="(g, gi) in stats.duplicates" :key="gi">
                                <div class="rounded-xl border border-black/[0.06] dark:border-white/10 overflow-hidden">
                                    <div class="flex items-center justify-between gap-3 border-b border-md-outline-variant bg-black/[0.02] dark:bg-white/5 px-3 py-1.5">
                                        <span class="text-xs text-md-on-surface-var" x-text="fmtSize(g[0].size) + ' · ' + g.length + '×'"></span>
                                        <button type="button" class="text-xs text-red-600 hover:underline dark:text-red-400" @click="trashDupeGroup(g)">{{ __('files.dupes_trash_group') }}</button>
                                    </div>
                                    <div class="divide-y divide-black/[0.04] dark:divide-white/5">
                                        <template x-for="(f, fi) in g" :key="f.id">
                                            <div class="flex items-center gap-2 px-3 py-2">
                                                <span class="msym text-base shrink-0 text-md-on-surface-var">description</span>
                                                <span class="min-w-0 flex-1 truncate font-mono text-xs text-md-on-surface-var" :title="f.path" x-text="f.path"></span>
                                                <span x-show="fi === 0" class="shrink-0 rounded-full bg-green-500/15 px-2 py-0.5 text-[10px] font-medium text-green-600 dark:text-green-400">{{ __('files.dupes_keep') }}</span>
                                                <x-icon-button name="trash" tone="red" size="sm" @click="trashDupe(f.id)" title="{{ __('files.dupes_trash_one') }}" aria-label="{{ __('files.dupes_trash_one') }}" />
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <p x-show="! stats.duplicates.length" x-cloak class="py-6 text-center text-xs text-md-on-surface-var">{{ __('files.duplicates_none') }}</p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </template>

    {{-- Shared-with-me: list of folders others shared with me, browse + download --}}
    <template x-teleport="body">
        <div x-show="swm.open" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeSwm()">
            <div class="absolute inset-0 bg-black/40" @click="closeSwm()"></div>
            <div class="relative flex max-h-[80vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-md-on-surface">
                        <button type="button" x-show="swm.view === 'browse'" @click="swm.view = 'list'" class="mr-1 text-accent">&larr;</button>
                        <span x-text="swm.view === 'browse' ? (swm.current?.folder_name || '{{ __('files.shared_with_me') }}') : '{{ __('files.shared_with_me') }}'"></span>
                    </h3>
                    <x-icon-button name="x-mark" @click="closeSwm()" aria-label="{{ __('files.share_close') }}" />
                </div>
                {{-- List of shares --}}
                <div x-show="swm.view === 'list'" class="mt-4 min-h-0 flex-1 overflow-auto">
                    <template x-for="s in swm.shares" :key="s.id">
                        <button type="button" @click="browseShare(s)" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left hover:bg-accent/5">
                            <span class="ll-chip h-9 w-9 shrink-0 rounded-xl" style="--chip:#3b9fd6"><span class="msym text-base text-white">folder</span></span>
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-md-on-surface" x-text="s.folder_name"></span><span class="block text-xs text-md-on-surface-var" x-text="(s.owner?.name || s.owner?.email || '') + ' · ' + s.role"></span></span>
                            <span class="msym text-base shrink-0 text-md-on-surface-var">chevron_right</span>
                        </button>
                    </template>
                    <p x-show="! swm.shares.length" x-cloak class="px-3 py-6 text-center text-xs text-md-on-surface-var">{{ __('files.shared_none') }}</p>
                </div>
                {{-- Browse a shared folder (read + download) --}}
                <div x-show="swm.view === 'browse'" x-cloak class="mt-4 min-h-0 flex-1 overflow-auto">
                    <div class="divide-y divide-black/[0.06] dark:divide-white/10">
                        <template x-for="f in swm.files" :key="f.id">
                            <div class="flex items-center gap-3 px-2 py-2">
                                <span class="min-w-0 flex-1 truncate text-sm text-md-on-surface" x-text="f.name"></span>
                                <a :href="swmRawUrl(swm.current, f) + '?download=1'" class="shrink-0 text-accent hover:underline"><span class="msym text-base">download</span></a>
                            </div>
                        </template>
                        <p x-show="! swm.files.length" x-cloak class="px-3 py-6 text-center text-xs text-md-on-surface-var">{{ __('files.sf_empty_folder') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Label manager (create / rename / recolor / delete coloured labels) --}}
    <template x-teleport="body">
        <div x-show="labelModal" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="labelModal = false">
            <div class="absolute inset-0 bg-black/40" @click="labelModal = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-md-surface p-6 shadow-xl">
                <h3 class="text-base font-semibold text-md-on-surface">{{ __('files.labels_title') }}</h3>
                <div class="mt-4 max-h-60 space-y-1 overflow-auto">
                    <template x-for="l in fileLabels" :key="l.id">
                        <div class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-black/[0.03] dark:hover:bg-white/5">
                            <span class="h-3.5 w-3.5 shrink-0 rounded-full" :style="'background:' + l.color"></span>
                            <span class="min-w-0 flex-1 truncate text-sm text-md-on-surface" x-text="l.name"></span>
                            <x-icon-button name="pencil" size="sm" @click="editLabel(l)" aria-label="{{ __('common.details') }}" />
                            <x-icon-button name="trash" tone="red" size="sm" @click="deleteLabel(l)" aria-label="{{ __('common.delete') }}" />
                        </div>
                    </template>
                    <p x-show="! fileLabels.length" x-cloak class="px-2 py-3 text-center text-xs text-md-on-surface-var">{{ __('files.labels_none') }}</p>
                </div>
                <div class="mt-4 border-t border-black/[0.06] dark:border-white/10 pt-4">
                    <div class="flex items-center gap-2">
                        <input type="color" x-model="labelDraft.color" class="h-9 w-10 shrink-0 cursor-pointer rounded-md border border-md-outline bg-transparent p-0.5">
                        <input type="text" x-model="labelDraft.name" maxlength="120" placeholder="{{ __('files.label_name') }}" @keydown.enter="saveLabel()"
                            class="min-w-0 flex-1 rounded-xl border-md-outline bg-md-surface-variant text-sm focus:border-accent focus:ring-accent">
                        <x-button variant="primary" size="sm" @click="saveLabel()" ::disabled="! labelDraft.name.trim()"><span x-text="labelDraft.id ? @js(__('files.save')) : @js(__('files.label_add'))"></span></x-button>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <x-button variant="secondary" @click="labelModal = false">{{ __('common.close') }}</x-button>
                </div>
            </div>
        </div>
    </template>

    @include('_paperless_modal')
  </div>{{-- /vaultFiles --}}
  </div>{{-- /filesTab: files --}}
  </div>{{-- /filesTab wrapper --}}
</x-layouts.app>
