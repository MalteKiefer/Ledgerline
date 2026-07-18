<x-layouts.app :title="__('gallery.title')">
  <div x-data="vaultGallery({
        uploadUrl: '{{ url('/gallery/upload') }}',
        processUrl: '{{ url('/gallery/process') }}',
        analyzeUrl: '{{ url('/gallery/analyze') }}',
        rawBase: '{{ url('/gallery/raw') }}',
        blobBase: '{{ url('/gallery/blob') }}',
        usageUrl: '{{ url('/gallery/usage') }}',
        reconcileUrl: '{{ url('/gallery/blobs/reconcile') }}',
        embedTextUrl: '{{ url('/gallery/embed-text') }}',
        clipModel: @js(config('gallery.ml_clip_model')),
        geocodeUrl: '{{ url('/gallery/geocode') }}',
        sharesUrl: '{{ url('/gallery/shares') }}',
        shareBase: '{{ url('/s') }}',
        token: '{{ csrf_token() }}',
     }, {
        loadFailed: @js(__('gallery.load_failed')),
        deleteConfirm: @js(__('gallery.delete_confirm')),
        purgeConfirm: @js(__('gallery.purge_confirm')),
        emptyTrashConfirm: @js(__('gallery.empty_trash_confirm')),
        albumName: @js(__('gallery.album_name')),
        deleteAlbumConfirm: @js(__('gallery.delete_album_confirm')),
        personName: @js(__('gallery.person_name')),
        create: @js(__('gallery.create')),
        save: @js(__('gallery.save')),
        uploadErrQuota: @js(__('gallery.upload_err_quota')),
        uploadErrNetwork: @js(__('gallery.upload_err_network')),
        uploadErrTimeout: @js(__('gallery.upload_err_timeout')),
        uploadErrFailed: @js(__('gallery.upload_err_failed')),
        uploadErrGeneric: @js(__('gallery.upload_err_generic')),
        procErrFailed: @js(__('gallery.proc_err_failed')),
        faceTagNone: @js(__('gallery.face_tag_none')),
        faceTagFailed: @js(__('gallery.face_tag_failed')),
        faceTagReset: @js(__('gallery.face_tag_reset')),
        faceTagHint: @js(__('gallery.face_tag_hint')),
        reindexConfirm: @js(__('gallery.reindex_confirm')),
        reindexNone: @js(__('gallery.reindex_none')),
        reindexDone: @js(__('gallery.reindex_done')),
        mergeDupConfirm: @js(__('gallery.merge_dup_confirm')),
        mergeDupNone: @js(__('gallery.merge_dup_none')),
        mergeDupDone: @js(__('gallery.merge_dup_done')),
        uploadAdded: @js(__('gallery.upload_added')),
        uploadMerged: @js(__('gallery.upload_merged')),
        uploadSkipped: @js(__('gallery.upload_skipped')),
        shareError: @js(__('gallery.share_error')),
        shareCopied: @js(__('gallery.share_copied')),
     })">

    <div x-show="dragging && state === 'ready'" x-cloak @drop.prevent="drop($event)" @dragover.prevent
        class="fixed inset-0 z-[900] flex items-center justify-center bg-gray-900/60 p-8 backdrop-blur-sm">
      <div class="rounded-3xl border-4 border-dashed border-white/70 px-16 py-24 text-center text-lg font-medium text-white">{{ __('gallery.drop_hint') }}</div>
    </div>

    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <x-page-heading :title="__('gallery.title')">
      <x-slot:actions>
        <div class="flex items-center gap-1.5">
          <span x-show="state === 'ready'" x-cloak class="mr-1 text-xs tabular-nums text-gray-400 dark:text-gray-500" x-text="photoCount() + ' · ' + fmtBytes(usage.used)"></span>
          <button type="button" @click="$store.vault.unlocked ? $store.vault.lock() : $dispatch('vault-panel')"
              :title="$store.vault.unlocked ? @js(__('vault.unlocked')) : @js(__('vault.unlock'))"
              class="min-h-11 min-w-11 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">
            <span x-show="$store.vault.unlocked"><x-icon name="lock-open" class="h-5 w-5" /></span>
            <span x-show="! $store.vault.unlocked"><x-icon name="lock-closed" class="h-5 w-5" /></span>
          </button>
          <x-button x-show="state === 'ready'" x-cloak variant="primary" @click="$refs.picker.click()">
            <x-icon name="arrow-up-tray" class="mr-1.5 h-4 w-4" />{{ __('gallery.upload') }}
          </x-button>
          <input x-ref="picker" type="file" accept="image/*,video/*,.heic,.heif,.mov" multiple class="hidden" @change="upload($event.target.files); $event.target.value = ''">
        </div>
      </x-slot:actions>
    </x-page-heading>

    <template x-if="state === 'locked'">
      <div class="mx-auto mt-16 flex max-w-md flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="lock-closed" class="h-7 w-7 text-gray-400" /></div>
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400" x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
        <button type="button" @click="$dispatch('vault-panel')" class="mt-5 rounded-lg bg-gray-900 dark:bg-gray-100 px-5 py-2.5 text-sm font-medium text-white dark:text-gray-900"><span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span></button>
      </div>
    </template>
    <template x-if="state === 'error'"><p class="mt-8 text-center text-sm text-red-500">{{ __('gallery.load_failed') }}</p></template>

    <div x-show="state === 'ready'" x-cloak class="mt-6 flex gap-6">
      {{-- Sidebar --}}
      <aside class="hidden w-44 shrink-0 md:block">
        <nav class="sticky top-6 space-y-0.5 rounded-xl bg-white dark:bg-gray-900 p-2 ring-1 ring-gray-100 dark:ring-gray-800">
          <button type="button" @click="view = 'library'"
              :class="view === 'library' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="photo" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.library') }}</span><span class="text-xs tabular-nums text-gray-400" x-text="photoCount()"></span>
          </button>
          <button type="button" @click="view = 'memories'" x-show="memoryCount()" x-cloak
              :class="view === 'memories' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="sparkles" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.memories') }}</span><span class="text-xs tabular-nums text-gray-400" x-text="memoryCount()"></span>
          </button>
          <button type="button" @click="view = 'favorites'"
              :class="view === 'favorites' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="star" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.favorites') }}</span><span x-show="favoriteCount()" class="text-xs tabular-nums text-gray-400" x-text="favoriteCount()"></span>
          </button>
          <button type="button" @click="view = 'map'"
              :class="view === 'map' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="map-pin" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.map') }}</span><span x-show="mapPhotos.length" class="text-xs tabular-nums text-gray-400" x-text="mapPhotos.length"></span>
          </button>
          <button type="button" @click="view = 'albums'"
              :class="view === 'albums' || view === 'album' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="folder" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.albums') }}</span><span x-show="albums.length" class="text-xs tabular-nums text-gray-400" x-text="albums.length"></span>
          </button>
          <button type="button" @click="view = 'people'"
              :class="view === 'people' || view === 'person' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="user" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.people') }}</span><span x-show="people.length" class="text-xs tabular-nums text-gray-400" x-text="people.length"></span>
          </button>
          <button type="button" @click="view = 'duplicates'"
              :class="view === 'duplicates' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="square-2-stack" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.duplicates') }}</span><span x-show="dupTotal" class="text-xs tabular-nums text-gray-400" x-text="dupTotal"></span>
          </button>
          <button type="button" @click="view = 'jobs'"
              :class="view === 'jobs' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <span :class="_pipelineRunning ? 'animate-spin' : ''"><x-icon name="arrow-path" class="h-4 w-4" /></span><span class="flex-1 text-left">{{ __('gallery.jobs') }}</span><span x-show="failedCount" class="rounded-full bg-amber-100 dark:bg-amber-900/40 px-1.5 text-xs font-medium tabular-nums text-amber-700 dark:text-amber-300" x-text="failedCount"></span>
          </button>
          <button type="button" @click="view = 'archive'"
              :class="view === 'archive' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="archive-box" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.archive') }}</span><span x-show="archiveCount()" class="text-xs tabular-nums text-gray-400" x-text="archiveCount()"></span>
          </button>
          <button type="button" @click="view = 'trash'"
              :class="view === 'trash' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="trash" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.trash') }}</span><span x-show="trashCount()" class="text-xs tabular-nums text-gray-400" x-text="trashCount()"></span>
          </button>
        </nav>
      </aside>

      <div class="min-w-0 flex-1">
        {{-- Bulk-select bar --}}
        <div x-show="selectedCount" x-cloak class="fixed bottom-5 left-1/2 z-40 flex max-w-[calc(100vw-1.5rem)] -translate-x-1/2 items-center gap-3 overflow-x-auto rounded-full border border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-900/95 px-4 py-2 shadow-xl backdrop-blur">
          <button type="button" @click="clearSelection()" class="shrink-0 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="x-mark" class="h-5 w-5" /></button>
          <span class="shrink-0 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-gray-200" x-text="@js(__('gallery.selected', ['count' => '{n}'])).replace('{n}', selectedCount)"></span>
          <button type="button" @click="selectAllVisible()" title="{{ __('gallery.select_all') }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="check-circle" class="h-5 w-5" /></button>
          <div class="flex shrink-0 items-center gap-2">
            <template x-if="view === 'library' || view === 'favorites'">
              <span class="flex items-center gap-2">
                <button type="button" @click="bulkFavorite()" title="{{ __('gallery.favorite') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="star" class="h-5 w-5" /></button>
                <button type="button" @click="albumPicker = true" title="{{ __('gallery.add_to_album') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="folder" class="h-5 w-5" /></button>
                <button type="button" @click="openBulkDate()" title="{{ __('gallery.bulk_date') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="calendar" class="h-5 w-5" /></button>
                <button type="button" @click="openBulkLocPicker()" title="{{ __('gallery.edit_location') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="map-pin" class="h-5 w-5" /></button>
                <button type="button" @click="bulkArchive()" title="{{ __('gallery.archive_action') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="archive-box" class="h-5 w-5" /></button>
                <button type="button" @click="bulkTrash()" title="{{ __('gallery.delete') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 hover:bg-black dark:hover:bg-white"><x-icon name="trash" class="h-5 w-5" /></button>
              </span>
            </template>
            <template x-if="view === 'archive'">
              <span class="flex items-center gap-2">
                <button type="button" @click="bulkUnarchive()" title="{{ __('gallery.unarchive_action') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="arrow-uturn-left" class="h-5 w-5" /></button>
                <button type="button" @click="bulkTrash()" title="{{ __('gallery.delete') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 hover:bg-black dark:hover:bg-white"><x-icon name="trash" class="h-5 w-5" /></button>
              </span>
            </template>
            <template x-if="view === 'trash'">
              <span class="flex gap-2">
                <button type="button" @click="bulkRestore()" title="{{ __('gallery.restore') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="arrow-path" class="h-5 w-5" /></button>
                <button type="button" @click="bulkPurge()" title="{{ __('gallery.purge') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600"><x-icon name="trash" class="h-5 w-5" /></button>
              </span>
            </template>
          </div>
        </div>

        {{-- Mobile view switch --}}
        <div class="mb-4 -mx-1 flex gap-2 overflow-x-auto px-1 pb-1 md:hidden">
          <button type="button" @click="view = 'library'; clearSelection()" :class="view === 'library' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.library') }}</button>
          <button type="button" x-show="memoryCount()" x-cloak @click="view = 'memories'; clearSelection()" :class="view === 'memories' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.memories') }}</button>
          <button type="button" @click="view = 'favorites'; clearSelection()" :class="view === 'favorites' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.favorites') }}</button>
          <button type="button" @click="view = 'albums'; clearSelection()" :class="view === 'albums' || view === 'album' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.albums') }}</button>
          <button type="button" @click="view = 'people'; clearSelection()" :class="view === 'people' || view === 'person' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.people') }}</button>
          <button type="button" @click="view = 'duplicates'; clearSelection()" :class="view === 'duplicates' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.duplicates') }}</button>
          <button type="button" @click="view = 'map'; clearSelection()" :class="view === 'map' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.map') }}</button>
          <button type="button" @click="view = 'archive'; clearSelection()" :class="view === 'archive' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.archive') }}</button>
          <button type="button" @click="view = 'trash'; clearSelection()" :class="view === 'trash' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.trash') }} <span x-show="trashCount()" x-text="'('+trashCount()+')'"></span></button>
        </div>

        {{-- LIBRARY --}}
        <div x-show="view === 'library'">
          {{-- Search (metadata + CLIP content, all client-side) --}}
          <div class="relative mb-4" x-show="libraryPhotos.length || isSearching">
            <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input type="search" x-model="query" @input="runSearch()" placeholder="{{ __('gallery.search_placeholder') }}"
                class="w-full rounded-lg border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-2 pl-9 pr-9 text-sm shadow-sm focus:border-gray-400 focus:ring-0">
            <button type="button" x-show="query" @click="clearSearch()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
            <svg x-show="searching" x-cloak class="absolute right-9 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
          </div>

          <template x-if="isSearching && ! displayGroups.length && ! searching">
            <p class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.no_results') }}</p>
          </template>
          <template x-if="! isSearching && ! libraryPhotos.length && ! progress.active && ! uploading">
            <button type="button" @click="$refs.picker.click()"
                class="mx-auto mt-6 flex w-full max-w-lg flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-700 p-16 text-center hover:border-gray-400 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-900/50">
              <x-icon name="photo" class="h-12 w-12 text-gray-300 dark:text-gray-600" />
              <p class="mt-4 text-sm font-medium text-gray-600 dark:text-gray-300">{{ __('gallery.empty') }}</p>
              <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('gallery.drop_hint') }}</p>
            </button>
          </template>

          <template x-for="group in displayGroups" :key="group.day">
            <section class="mb-6">
              <label x-show="group.label" class="mb-2.5 flex cursor-pointer items-center gap-2">
                <input type="checkbox" :checked="groupSelected(group)" @change="toggleGroup(group)" title="{{ __('gallery.select_all') }}" class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0 focus:ring-offset-0">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300" x-text="group.label"></h2>
              </label>
              <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
                <template x-for="p in group.photos" :key="p.id">
                  <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
                       :class="isSelected(p.id) ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-gray-100 ring-offset-white dark:ring-offset-gray-950' : ''" x-intersect.once="thumbFor(p)"
                       @mouseenter="hoverMotion(p, $event.currentTarget)" @mouseleave="unhoverMotion($event.currentTarget)">
                    <button type="button" @click="openViewer(p)" class="block h-full w-full">
                      <img x-show="thumbs[p.id]" :src="thumbs[p.id]" :style="photoTransform(p)" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                      <template x-if="p.motionRef && p.media_type !== 'video'"><video data-motion muted loop playsinline preload="none" style="display:none" class="pointer-events-none absolute inset-0 h-full w-full object-cover"></video></template>
                      <div x-show="!thumbs[p.id]" class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900">
                        <svg x-show="!p.thumbRef && !p.failed" class="h-5 w-5 animate-spin text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                        <span x-show="p.failed" :title="p.procError" class="text-amber-500 dark:text-amber-400"><x-icon name="exclamation-triangle" class="h-6 w-6" /></span>
                      </div>
                      <template x-if="p.media_type === 'video'"><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span></span></template>
                      <template x-if="p.motionRef && p.media_type !== 'video'"><span class="pointer-events-none absolute left-1.5 top-1.5 rounded bg-black/45 px-1 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-white backdrop-blur-sm">Live</span></template>
                    </button>
                    <label class="absolute left-2 top-2 z-10 cursor-pointer" :class="selectedCount ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'" @click.stop.prevent="clickSelect(p.id, $event)">
                      <input type="checkbox" :checked="isSelected(p.id)" class="pointer-events-none h-4 w-4 rounded border-white/80 bg-black/30 text-gray-900 focus:ring-0 focus:ring-offset-0">
                    </label>
                    <button type="button" @click.stop="toggleFavorite(p)" :title="p.favorite ? '{{ __('gallery.unfavorite') }}' : '{{ __('gallery.favorite') }}'"
                        class="absolute right-11 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-sm transition hover:bg-black/60"
                        :class="p.favorite ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'">
                      <x-icon x-show="p.favorite" name="star-solid" class="h-4 w-4 text-amber-400" />
                      <x-icon x-show="! p.favorite" name="star" class="h-4 w-4" />
                    </button>
                    <button type="button" @click.stop="trash(p)" title="{{ __('gallery.delete') }}"
                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/40 text-white opacity-0 backdrop-blur-sm transition hover:bg-red-500 group-hover:opacity-100"><x-icon name="trash" class="h-4 w-4" /></button>
                  </div>
                </template>
              </div>
            </section>
          </template>
          {{-- Infinite-scroll sentinel: reveals the next page of tiles as it
               nears the viewport, so the grid never builds the whole library. --}}
          <div x-show="hasMore" x-intersect.margin.800px="loadMore()" class="flex items-center justify-center py-6">
            <svg class="h-5 w-5 animate-spin text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
          </div>
        </div>

        {{-- ACTIVITY / BATCHES --}}
        <div x-show="view === 'jobs'">
          <div class="mb-2 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.jobs_heading') }}</h2>
            <button type="button" @click="runAllJobs()" :disabled="_pipelineRunning || progress.active || peopleScanning || dupScanning || deepScanning"
                class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 dark:bg-gray-100 px-3 py-1.5 text-sm font-medium text-white dark:text-gray-900 disabled:opacity-50">
              <x-icon name="arrow-path" class="h-4 w-4" />{{ __('gallery.jobs_run_all') }}
            </button>
          </div>
          <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.jobs_hint') }}</p>
          <div class="space-y-3">
            {{-- Processing --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('gallery.jobs_processing') }}</span>
                <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="progress.active ? (progress.done + ' / ' + progress.total) : (failedCount ? (failedCount + ' ' + @js(__('gallery.failed_label'))) : @js(__('gallery.jobs_done')))"></span>
              </div>
              <div x-show="progress.active" class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${progress.total ? (progress.done / progress.total * 100) : 0}%`"></div></div>
              <button type="button" x-show="failedCount && !progress.active" @click="retryFailed()" class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300 underline">{{ __('gallery.retry_failed') }}</button>
            </div>
            {{-- Face analysis (ML detection backlog — must run before faces can be grouped) --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('gallery.jobs_analyze') }}</span>
                <div class="flex items-center gap-3">
                  <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400"
                        x-text="(deepScanning || _mlRunning) ? (mlProgress.done + ' / ' + mlProgress.total) : (unanalyzedCount() ? (unanalyzedCount() + ' ' + @js(__('gallery.jobs_pending'))) : @js(__('gallery.jobs_done')))"></span>
                  <button type="button" x-show="!deepScanning && !_mlRunning && !peopleScanning && unanalyzedCount() > 0" @click="deepFaceRescan()" class="text-xs font-medium text-gray-700 dark:text-gray-300 underline">{{ __('gallery.jobs_run') }}</button>
                </div>
              </div>
              <div x-show="deepScanning || _mlRunning" class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${mlProgress.total ? (mlProgress.done / mlProgress.total * 100) : 8}%`"></div></div>
            </div>
            {{-- Faces --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('gallery.people') }}</span>
                <div class="flex items-center gap-3">
                  <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="peopleScanning ? (peopleProgress.done + ' / ' + peopleProgress.total) : people.length"></span>
                  <button type="button" x-show="!peopleScanning && !progress.active && !deepScanning" @click="scanFaces()" class="text-xs font-medium text-gray-700 dark:text-gray-300 underline">{{ __('gallery.jobs_run') }}</button>
                </div>
              </div>
              <div x-show="peopleScanning" class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${peopleProgress.total ? (peopleProgress.done / peopleProgress.total * 100) : 0}%`"></div></div>
            </div>
            {{-- Duplicates --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('gallery.duplicates') }}</span>
                <div class="flex items-center gap-3">
                  <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="dupScanning ? (dupProgress.done + ' / ' + dupProgress.total) : dupTotal"></span>
                  <button type="button" x-show="!dupScanning && !progress.active" @click="scanDuplicates()" class="text-xs font-medium text-gray-700 dark:text-gray-300 underline">{{ __('gallery.jobs_run') }}</button>
                </div>
              </div>
              <div x-show="dupScanning" class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${dupProgress.total ? (dupProgress.done / dupProgress.total * 100) : 0}%`"></div></div>
            </div>
          </div>
        </div>

        {{-- TRASH --}}
        <div x-show="view === 'trash'">
          <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.trash') }} <span class="ml-1 text-sm font-normal tabular-nums text-gray-400" x-text="trashCount()"></span></h2>
            <button type="button" x-show="trashCount()" @click="emptyTrash()"
                class="inline-flex items-center gap-1.5 rounded-lg bg-red-500 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-red-600">
              <x-icon name="trash" class="h-4 w-4" />{{ __('gallery.empty_trash') }}
            </button>
          </div>
          <template x-if="! trashCount()"><p class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.trash_empty') }}</p></template>
          <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
            <template x-for="p in trashedPhotos" :key="p.id">
              <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
                   :class="isSelected(p.id) ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-gray-100 ring-offset-white dark:ring-offset-gray-950' : ''" x-intersect.once="thumbFor(p)">
                <img x-show="thumbs[p.id]" :src="thumbs[p.id]" loading="lazy" class="h-full w-full object-cover opacity-70">
                <div x-show="!thumbs[p.id]" class="h-full w-full bg-gray-200 dark:bg-gray-700"></div>
                <label class="absolute left-2 top-2 z-10 cursor-pointer" @click.stop.prevent="clickSelect(p.id, $event)">
                  <input type="checkbox" :checked="isSelected(p.id)" class="pointer-events-none h-4 w-4 rounded border-white/80 bg-black/30 text-gray-900 focus:ring-0 focus:ring-offset-0">
                </label>
                <div class="absolute inset-0 flex items-center justify-center gap-1.5 bg-black/40 opacity-0 transition group-hover:opacity-100">
                  <button type="button" @click="restore(p)" title="{{ __('gallery.restore') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-gray-800 hover:bg-white"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
                  <button type="button" @click="purge(p)" title="{{ __('gallery.purge') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
              </div>
            </template>
          </div>
        </div>

        {{-- MEMORIES (on this day, grouped by year) --}}
        <div x-show="view === 'memories'">
          <div class="mb-4">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.memories') }}</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('gallery.memories_hint') }}</p>
          </div>
          <template x-if="! memoryCount()"><p class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.memories_empty') }}</p></template>
          <template x-for="grp in memories" :key="grp.year">
            <section class="mb-6">
              <h3 class="mb-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300" x-text="grp.yearsAgo === 1 ? '{{ __('gallery.memories_year_ago') }}' : grp.yearsAgo + ' {{ __('gallery.memories_years_ago') }}'"></h3>
              <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
                <template x-for="p in grp.photos" :key="p.id">
                  <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800" x-intersect.once="thumbFor(p)">
                    <button type="button" @click="openViewer(p)" class="block h-full w-full">
                      <img x-show="thumbs[p.id]" :src="thumbs[p.id]" :style="photoTransform(p)" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                      <div x-show="!thumbs[p.id]" class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div>
                      <template x-if="p.media_type === 'video'"><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span></span></template>
                    </button>
                    <span x-show="p.favorite" class="pointer-events-none absolute right-2 top-2 text-white drop-shadow"><x-icon name="star-solid" class="h-4 w-4" /></span>
                  </div>
                </template>
              </div>
            </section>
          </template>
        </div>

        {{-- FAVORITES --}}
        <div x-show="view === 'favorites'">
          <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.favorites') }} <span class="ml-1 text-sm font-normal tabular-nums text-gray-400" x-text="favoriteCount()"></span></h2>
          <template x-if="! favoriteCount()"><p class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.favorites_empty') }}</p></template>
          <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
            <template x-for="p in favoritePhotos" :key="p.id">
              <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
                   :class="isSelected(p.id) ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-gray-100 ring-offset-white dark:ring-offset-gray-950' : ''" x-intersect.once="thumbFor(p)">
                <button type="button" @click="openViewer(p)" class="block h-full w-full">
                  <img x-show="thumbs[p.id]" :src="thumbs[p.id]" :style="photoTransform(p)" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                  <div x-show="!thumbs[p.id]" class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div>
                  <template x-if="p.media_type === 'video'"><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span></span></template>
                </button>
                <label class="absolute left-2 top-2 z-10 cursor-pointer" :class="selectedCount ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'" @click.stop.prevent="clickSelect(p.id, $event)">
                  <input type="checkbox" :checked="isSelected(p.id)" class="pointer-events-none h-4 w-4 rounded border-white/80 bg-black/30 text-gray-900 focus:ring-0 focus:ring-offset-0">
                </label>
                <button type="button" @click.stop="toggleFavorite(p)" title="{{ __('gallery.unfavorite') }}"
                    class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-sm transition hover:bg-black/60"><x-icon name="star-solid" class="h-4 w-4" /></button>
              </div>
            </template>
          </div>
        </div>

        {{-- ARCHIVE --}}
        <div x-show="view === 'archive'">
          <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.archive') }} <span class="ml-1 text-sm font-normal tabular-nums text-gray-400" x-text="archiveCount()"></span></h2>
          <p class="mb-4 -mt-2 text-xs text-gray-400 dark:text-gray-500">{{ __('gallery.archive_hint') }}</p>
          <template x-if="! archiveCount()"><p class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.archive_empty') }}</p></template>
          <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
            <template x-for="p in archivedPhotos" :key="p.id">
              <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
                   :class="isSelected(p.id) ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-gray-100 ring-offset-white dark:ring-offset-gray-950' : ''" x-intersect.once="thumbFor(p)">
                <button type="button" @click="openViewer(p)" class="block h-full w-full">
                  <img x-show="thumbs[p.id]" :src="thumbs[p.id]" :style="photoTransform(p)" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                  <div x-show="!thumbs[p.id]" class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div>
                  <template x-if="p.media_type === 'video'"><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span></span></template>
                </button>
                <label class="absolute left-2 top-2 z-10 cursor-pointer" :class="selectedCount ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'" @click.stop.prevent="clickSelect(p.id, $event)">
                  <input type="checkbox" :checked="isSelected(p.id)" class="pointer-events-none h-4 w-4 rounded border-white/80 bg-black/30 text-gray-900 focus:ring-0 focus:ring-offset-0">
                </label>
                <button type="button" @click.stop="unarchive(p)" title="{{ __('gallery.unarchive_action') }}"
                    class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/40 text-white opacity-0 backdrop-blur-sm transition hover:bg-black/60 group-hover:opacity-100"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
              </div>
            </template>
          </div>
        </div>

        {{-- MAP --}}
        <div x-show="view === 'map'">
          <div x-show="geoProgress.total && geoProgress.done < geoProgress.total" x-cloak class="mb-3 flex items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-2 text-sm text-gray-600 dark:text-gray-300">
            <svg class="h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
            <span>{{ __('gallery.map_loading') }} <span class="tabular-nums" x-text="geoProgress.done + ' / ' + geoProgress.total"></span></span>
          </div>
          <template x-if="! mapPhotos.length && ! (geoProgress.total && geoProgress.done < geoProgress.total)"><p class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.no_results') }}</p></template>
          <div x-ref="map" x-show="mapPhotos.length" class="h-[70vh] w-full overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"></div>
        </div>

        {{-- ALBUMS (list) --}}
        <div x-show="view === 'albums'">
          <div x-show="albums.length" class="mb-5 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.albums') }} <span class="ml-1 text-sm font-normal tabular-nums text-gray-400" x-text="albums.length"></span></h2>
            <button type="button" @click="createAlbum()" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200">
              <x-icon name="plus" class="h-4 w-4" />{{ __('gallery.new_album') }}
            </button>
          </div>

          <div x-show="! albums.length" x-cloak class="mx-auto mt-8 flex max-w-md flex-col items-center rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-800 p-12 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="folder" class="h-8 w-8 text-gray-400 dark:text-gray-500" /></div>
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.no_albums') }}</p>
          </div>

          <div x-show="albums.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <template x-for="al in albums" :key="al.id">
              <button type="button" @click="openAlbum(al)" class="group text-left focus:outline-none">
                <div class="relative aspect-square overflow-hidden rounded-2xl bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-800 transition duration-300 group-hover:shadow-md group-hover:ring-gray-300 dark:group-hover:ring-gray-700"
                     x-init="$nextTick(() => albumCover(al) && thumbFor(albumCover(al)))">
                  <img x-show="albumCover(al) && thumbs[albumCover(al).id]" :src="albumCover(al) && thumbs[albumCover(al).id]" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                  <div x-show="! (albumCover(al) && thumbs[albumCover(al).id])" class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"><x-icon name="folder" class="h-9 w-9 text-gray-300 dark:text-gray-600" /></div>
                  <span class="absolute bottom-2 right-2 inline-flex h-5 items-center rounded-full bg-black/55 px-2 text-[11px] font-medium tabular-nums text-white backdrop-blur-sm" x-text="albumCount(al)"></span>
                </div>
                <p class="mt-2 truncate text-sm font-medium text-gray-800 dark:text-gray-200" x-text="al.name"></p>
              </button>
            </template>
          </div>
        </div>

        {{-- ALBUM (single) --}}
        <div x-show="view === 'album'">
          <template x-if="currentAlbum">
            <div>
              <div class="mb-4 flex items-center gap-3">
                <button type="button" @click="view = 'albums'" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"><x-icon name="arrow-uturn-left" class="h-4 w-4" />{{ __('gallery.back') }}</button>
                <h2 class="truncate text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="currentAlbum?.name"></h2>
                <span class="text-xs tabular-nums text-gray-400" x-text="albumCount(currentAlbum)"></span>
                <div class="ml-auto flex items-center gap-1.5">
                  <button type="button" @click="openShare(currentAlbum)" title="{{ __('gallery.share') }}" class="rounded-lg p-2 hover:bg-gray-100 dark:hover:bg-gray-800" :class="currentAlbum?.share ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500'"><x-icon name="share" class="h-4 w-4" /></button>
                  <button type="button" @click="renameAlbum(currentAlbum)" title="{{ __('gallery.rename') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="pencil" class="h-4 w-4" /></button>
                  <button type="button" @click="deleteAlbum(currentAlbum)" title="{{ __('gallery.delete_album') }}" class="rounded-lg p-2 text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
              </div>
              <template x-if="! albumCount(currentAlbum)"><p class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.album_empty') }}</p></template>
              <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
                <template x-for="p in albumPhotos(currentAlbum)" :key="p.id">
                  <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800" x-intersect.once="thumbFor(p)">
                    <button type="button" @click="openViewer(p)" class="block h-full w-full">
                      <img x-show="thumbs[p.id]" :src="thumbs[p.id]" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                      <div x-show="!thumbs[p.id]" class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div>
                    </button>
                    <button type="button" @click.stop="removeFromAlbum(currentAlbum, p)" title="{{ __('gallery.remove') }}"
                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/40 text-white opacity-0 backdrop-blur-sm transition hover:bg-red-500 group-hover:opacity-100"><x-icon name="x-mark" class="h-4 w-4" /></button>
                  </div>
                </template>
              </div>
            </div>
          </template>
        </div>

        {{-- PEOPLE (list) --}}
        <div x-show="view === 'people'">
          {{-- Birthdays today: a linked person whose contact's birthday is today.
               Tap to open the person and see their photos. --}}
          <div x-show="birthdayPeople.length" x-cloak class="mb-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
            <div class="mb-3 flex items-center gap-2">
              <x-icon name="sparkles" class="h-4 w-4 text-gray-500" />
              <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('gallery.birthdays_today') }}</h3>
            </div>
            <div class="flex flex-wrap gap-4">
              <template x-for="b in birthdayPeople" :key="b.pp.id">
                <button type="button" @click="openPerson(b.pp)" class="group flex w-20 flex-col items-center focus:outline-none">
                  <div class="relative aspect-square w-16 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 transition group-hover:ring-2 group-hover:ring-gray-900 dark:group-hover:ring-gray-100"
                       x-init="$nextTick(() => personCover(b.pp) && faceThumb(personCover(b.pp)))">
                    <img x-show="personCover(b.pp) && faceThumbs[personCover(b.pp).cropRef]" :src="personCover(b.pp) && faceThumbs[personCover(b.pp).cropRef]" class="h-full w-full object-cover">
                    <div x-show="! (personCover(b.pp) && faceThumbs[personCover(b.pp).cropRef])" class="flex h-full w-full items-center justify-center"><x-icon name="user" class="h-6 w-6 text-gray-300 dark:text-gray-600" /></div>
                  </div>
                  <p class="mt-1.5 max-w-full truncate text-xs font-medium text-gray-800 dark:text-gray-200" x-text="personLabel(b.pp)"></p>
                  <p class="text-[11px] tabular-nums text-gray-400" x-text="b.age != null ? @js(__('gallery.turns_age', ['age' => '{n}'])).replace('{n}', b.age) : '{{ __('gallery.birthday_today_label') }}'"></p>
                </button>
              </template>
            </div>
          </div>

          {{-- Toolbar: only when results exist --}}
          <div x-show="people.length && ! peopleScanning && ! deepScanning" class="mb-5 flex items-center justify-between gap-3">
            <div class="min-w-0">
              <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.people') }} <span class="ml-1 text-sm font-normal tabular-nums text-gray-400" x-text="people.length"></span></h2>
              <p class="text-xs text-gray-400 dark:text-gray-500" x-text="facesDetected() + ' {{ __('gallery.faces_found') }} · ' + photosWithFaces() + ' {{ __('gallery.photos_label') }}' + (unanalyzedCount() ? ' · ' + unanalyzedCount() + ' {{ __('gallery.jobs_pending') }}' : '')"></p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <button type="button" x-show="unanalyzedCount() > 0" x-cloak @click="deepFaceRescan()" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 dark:bg-gray-100 px-3 py-1.5 text-sm font-medium text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-white" :title="'{{ __('gallery.analyze_all_hint') }}'">
                <x-icon name="sparkles" class="h-4 w-4" />{{ __('gallery.analyze_all') }} <span class="tabular-nums opacity-80" x-text="'(' + unanalyzedCount() + ')'"></span>
              </button>
              <button type="button" x-show="duplicatePeopleCount() > 0" x-cloak @click="mergeDuplicates()" :title="'{{ __('gallery.merge_dup_hint') }}'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
                <x-icon name="users" class="h-4 w-4" />{{ __('gallery.merge_duplicates') }} <span class="tabular-nums opacity-70" x-text="'(' + duplicatePeopleCount() + ')'"></span>
              </button>
              <button type="button" @click="smartRescan()" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                <x-icon name="arrow-path" class="h-4 w-4" />{{ __('gallery.rescan') }}
              </button>
              <button type="button" @click="reindexAll()" x-show="! _mlRunning" x-cloak :title="'{{ __('gallery.reindex_hint') }}'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                <x-icon name="sparkles" class="h-4 w-4" />{{ __('gallery.reindex_all') }}
              </button>
              <span x-show="reindexProgress" x-cloak class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 tabular-nums">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                <span x-text="(reindexProgress?.done || 0) + ' / ' + (reindexProgress?.total || 0)"></span>
              </span>
            </div>
          </div>

          {{-- Scanning card (face clustering OR deep ML re-analysis) --}}
          <div x-show="peopleScanning || deepScanning" x-cloak class="mx-auto mt-8 flex max-w-sm flex-col items-center rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-10 text-center shadow-sm">
            <svg class="h-8 w-8 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
            <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-200" x-text="deepScanning && _mlRunning ? '{{ __('gallery.analyzing') }}' : '{{ __('gallery.scanning') }}'"></p>
            <template x-if="deepScanning && _mlRunning">
              <div class="w-full">
                <div class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-gray-800 dark:bg-gray-200 transition-all duration-300" :style="`width: ${mlProgress.total ? (mlProgress.done / mlProgress.total * 100) : 8}%`"></div></div>
                <p class="mt-2 text-xs tabular-nums text-gray-400" x-text="mlProgress.done + ' / ' + mlProgress.total"></p>
              </div>
            </template>
            <template x-if="! (deepScanning && _mlRunning)">
              <div class="w-full">
                <div class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-gray-800 dark:bg-gray-200 transition-all duration-300" :style="`width: ${peopleProgress.total ? (peopleProgress.done / peopleProgress.total * 100) : 8}%`"></div></div>
                <p class="mt-2 text-xs tabular-nums text-gray-400" x-text="peopleProgress.done + ' / ' + peopleProgress.total"></p>
              </div>
            </template>
          </div>

          {{-- Empty / first-run hero --}}
          <div x-show="! people.length && ! peopleScanning && ! deepScanning" x-cloak class="mx-auto mt-8 flex max-w-md flex-col items-center rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-800 p-12 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="user" class="h-8 w-8 text-gray-400 dark:text-gray-500" /></div>
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.no_people') }}</p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
              <button type="button" x-show="unanalyzedCount() > 0" x-cloak @click="deepFaceRescan()" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 dark:bg-gray-100 px-5 py-2.5 text-sm font-medium text-white dark:text-gray-900 shadow-sm transition hover:bg-gray-800 dark:hover:bg-white" :title="'{{ __('gallery.analyze_all_hint') }}'">
                <x-icon name="sparkles" class="h-4 w-4" />{{ __('gallery.analyze_all') }} <span class="tabular-nums opacity-80" x-text="'(' + unanalyzedCount() + ')'"></span>
              </button>
              <button type="button" @click="smartRescan()" class="inline-flex items-center gap-2 rounded-xl border border-gray-300 dark:border-gray-700 px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 transition hover:bg-gray-50 dark:hover:bg-gray-800">
                <x-icon name="sparkles" class="h-4 w-4" />{{ __('gallery.scan_faces') }}
              </button>
            </div>
          </div>

          {{-- People grid --}}
          <div x-show="people.length && ! peopleScanning && ! deepScanning" class="grid grid-cols-3 gap-x-4 gap-y-6 sm:grid-cols-4 lg:grid-cols-6">
            <template x-for="pp in people" :key="pp.id">
              <button type="button" @click="openPerson(pp)" class="group flex flex-col items-center focus:outline-none">
                <div class="relative aspect-square w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 transition duration-300 group-hover:ring-2 group-hover:ring-gray-900 dark:group-hover:ring-gray-100 group-hover:shadow-md"
                     x-init="$nextTick(() => personCover(pp) && faceThumb(personCover(pp)))">
                  <img x-show="personCover(pp) && faceThumbs[personCover(pp).cropRef]" :src="personCover(pp) && faceThumbs[personCover(pp).cropRef]" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                  <div x-show="! (personCover(pp) && faceThumbs[personCover(pp).cropRef])" class="flex h-full w-full items-center justify-center"><x-icon name="user" class="h-8 w-8 text-gray-300 dark:text-gray-600" /></div>
                </div>
                <p class="mt-2 max-w-full truncate text-sm font-medium text-gray-800 dark:text-gray-200" x-text="personLabel(pp) || (@js(__('gallery.person_unnamed')))"></p>
                <p class="text-xs tabular-nums text-gray-400" x-text="@js(__('gallery.photos_count', ['count' => '{n}'])).replace('{n}', personCount(pp))"></p>
              </button>
            </template>
          </div>
        </div>

        {{-- PERSON (single) --}}
        <div x-show="view === 'person'">
          <template x-if="currentPerson">
            <div>
              <div class="mb-4 flex items-center gap-3">
                <button type="button" @click="view = 'people'" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"><x-icon name="arrow-uturn-left" class="h-4 w-4" />{{ __('gallery.back') }}</button>
                <h2 class="truncate text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="personLabel(currentPerson) || (@js(__('gallery.person_unnamed')))"></h2>
                <span class="text-xs tabular-nums text-gray-400" x-text="personCount(currentPerson)"></span>
                <div class="ml-auto flex items-center gap-1.5">
                  <button type="button" @click="renamePerson(currentPerson)" title="{{ __('gallery.rename') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="pencil" class="h-4 w-4" /></button>
                  <button type="button" @click="openMergePicker()" title="{{ __('gallery.merge') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="arrows-pointing-in" class="h-4 w-4" /></button>
                  <button type="button" @click="openLinkPicker()" :title="'{{ __('gallery.link_contact') }}'" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" :class="currentPerson?.contactId ? 'text-gray-900 dark:text-gray-100' : ''"><x-icon name="users" class="h-4 w-4" /></button>
                  <button type="button" @click="hidePerson(currentPerson)" title="{{ __('gallery.hide') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="x-mark" class="h-4 w-4" /></button>
                </div>
              </div>
              {{-- Linked contact chip --}}
              <div x-show="currentPerson?.contactId" x-cloak class="mb-4 inline-flex items-center gap-2 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-1 pl-1 pr-3 text-sm">
                <span class="flex h-6 w-6 items-center justify-center overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-semibold text-gray-500" x-init="$nextTick(() => currentPerson?.contactAvatarRef && contactAvatarFor(currentPerson))">
                    <img x-show="currentPerson?.contactAvatarRef && _contactAvatars[currentPerson.contactAvatarRef]" :src="currentPerson?.contactAvatarRef && _contactAvatars[currentPerson.contactAvatarRef]" class="h-full w-full object-cover">
                    <x-icon x-show="! (currentPerson?.contactAvatarRef && _contactAvatars[currentPerson.contactAvatarRef])" name="user" class="h-3.5 w-3.5 text-gray-400" />
                </span>
                <a :href="'/contacts?c=' + currentPerson.contactId" class="font-medium text-gray-800 dark:text-gray-200 hover:underline" x-text="personLabel(currentPerson) || '{{ __('gallery.linked_contact') }}'"></a>
                <button type="button" @click="unlinkContact()" title="{{ __('gallery.unlink') }}" class="text-gray-400 hover:text-red-600"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
              </div>
              {{-- Birthday of the linked contact (with age when the year is known) --}}
              <div x-show="personBday(currentPerson)" x-cloak class="mb-4 ml-1.5 inline-flex items-center gap-1.5 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1 text-sm text-gray-700 dark:text-gray-300"
                   :class="_bdayToday(personBday(currentPerson)) ? 'ring-1 ring-gray-900 dark:ring-gray-100' : ''">
                <x-icon name="sparkles" class="h-3.5 w-3.5 text-gray-400" />
                <span x-text="fmtDate(personBday(currentPerson)) + (personAge(currentPerson) != null ? ' · ' + @js(__('gallery.age_years', ['age' => '{n}'])).replace('{n}', personAge(currentPerson)) : '')"></span>
              </div>
              <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
                <template x-for="p in personPhotos(currentPerson)" :key="p.id">
                  <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800" x-intersect.once="thumbFor(p)">
                    <button type="button" @click="openViewer(p)" class="block h-full w-full">
                      <img x-show="thumbs[p.id]" :src="thumbs[p.id]" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                      <div x-show="!thumbs[p.id]" class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div>
                    </button>
                    <div class="absolute right-1.5 top-1.5 flex gap-1 opacity-0 transition group-hover:opacity-100">
                      <button type="button" @click.stop="setPersonCover(p)" title="{{ __('gallery.set_cover') }}" class="flex h-7 w-7 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm hover:bg-black/70"><x-icon name="photo" class="h-3.5 w-3.5" /></button>
                      <button type="button" @click.stop="openReassign(p)" title="{{ __('gallery.reassign') }}" class="flex h-7 w-7 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm hover:bg-black/70"><x-icon name="arrows-right-left" class="h-3.5 w-3.5" /></button>
                      <button type="button" @click.stop="removeFaceFromPerson(p)" title="{{ __('gallery.not_this_person') }}" class="flex h-7 w-7 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm hover:bg-red-500"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </template>
        </div>

        {{-- DUPLICATES --}}
        <div x-show="view === 'duplicates'">
          {{-- Toolbar: only when result groups exist --}}
          <div x-show="dupGroups && dupGroups.length && ! dupScanning" class="mb-5 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.duplicates') }}
              <span class="ml-1 text-sm font-normal text-gray-400" x-text="@js(__('gallery.duplicate_sets', ['count' => '{n}'])).replace('{n}', (dupGroups ? dupGroups.length : 0))"></span>
            </h2>
            <div class="flex items-center gap-2">
              @include('gallery._scan_scope')
              <button type="button" @click="scanDuplicates()" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                <x-icon name="arrow-path" class="h-4 w-4" />{{ __('gallery.rescan') }}
              </button>
            </div>
          </div>

          {{-- Scanning card --}}
          <div x-show="dupScanning" x-cloak class="mx-auto mt-8 flex max-w-sm flex-col items-center rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-10 text-center shadow-sm">
            <svg class="h-8 w-8 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
            <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('gallery.scanning') }}</p>
            <div class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-gray-800 dark:bg-gray-200 transition-all duration-300" :style="`width: ${dupProgress.total ? (dupProgress.done / dupProgress.total * 100) : 8}%`"></div></div>
            <p class="mt-2 text-xs tabular-nums text-gray-400" x-text="dupProgress.done + ' / ' + dupProgress.total"></p>
          </div>

          {{-- Empty / first-run hero (no scan yet, or scan found nothing) --}}
          <div x-show="! dupScanning && (! dupGroups || ! dupGroups.length)" x-cloak class="mx-auto mt-8 flex max-w-md flex-col items-center rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-800 p-12 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="square-2-stack" class="h-8 w-8 text-gray-400 dark:text-gray-500" /></div>
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400" x-text="dupGroups ? (@js(__('gallery.no_duplicates'))) : (@js(__('gallery.duplicates_hint')))"></p>
            <div class="mt-5 flex items-center gap-2">
              @include('gallery._scan_scope')
              <button type="button" @click="scanDuplicates()" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 dark:bg-gray-100 px-5 py-2.5 text-sm font-medium text-white dark:text-gray-900 shadow-sm transition hover:bg-gray-800 dark:hover:bg-white">
                <x-icon name="sparkles" class="h-4 w-4" />{{ __('gallery.find_duplicates') }}
              </button>
            </div>
          </div>

          {{-- Result groups --}}
          <div x-show="dupGroups && dupGroups.length && ! dupScanning" class="space-y-3">
            <template x-for="(group, gi) in (dupGroups || [])" :key="gi">
              <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-sm">
                <div class="mb-2 flex items-center gap-2 px-1">
                  <span class="inline-flex h-5 items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2 text-[11px] font-medium tabular-nums text-gray-500 dark:text-gray-400" x-text="@js(__('gallery.copies', ['count' => '{n}'])).replace('{n}', group.length)"></span>
                  <span class="text-[11px] text-gray-400">· {{ __('gallery.select_to_delete') }}</span>
                  <div class="ml-auto flex items-center gap-1.5">
                    <button type="button" @click="markRest(group)" class="rounded-lg px-2 py-1 text-[11px] font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200">{{ __('gallery.select_rest') }}</button>
                    <button type="button" @click="trashMarked(group)" :disabled="! dupMarkedCount(group)"
                        class="inline-flex items-center gap-1 rounded-lg bg-red-500 px-2.5 py-1 text-[11px] font-medium text-white transition hover:bg-red-600 disabled:opacity-40"
                        x-text="dupMarkedCount(group) ? @js(__('gallery.delete_selected', ['count' => '{n}'])).replace('{n}', dupMarkedCount(group)) : @js(__('gallery.delete'))"></button>
                  </div>
                </div>
                <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-4 lg:grid-cols-6">
                  <template x-for="(p, pi) in group" :key="p.id">
                    <div class="group relative aspect-square cursor-pointer overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800 ring-2 transition"
                         :class="isDupMarked(p.id) ? 'ring-red-500' : (pi === 0 ? 'ring-emerald-400' : 'ring-transparent')"
                         x-intersect.once="thumbFor(p)" @click="toggleDupMark(p.id)">
                      <img x-show="thumbs[p.id]" :src="thumbs[p.id]" loading="lazy" class="h-full w-full object-cover" :class="isDupMarked(p.id) ? 'opacity-60' : ''">
                      <div x-show="!thumbs[p.id]" class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div>
                      {{-- Selection checkmark --}}
                      <span class="absolute left-1 top-1 flex h-5 w-5 items-center justify-center rounded-full border-2 transition"
                          :class="isDupMarked(p.id) ? 'border-red-500 bg-red-500 text-white' : 'border-white/80 bg-black/25'">
                        <svg x-show="isDupMarked(p.id)" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                      </span>
                      <span x-show="pi === 0 && ! isDupMarked(p.id)" class="pointer-events-none absolute right-1 top-1 rounded-full bg-emerald-500 px-1.5 py-0.5 text-[10px] font-medium text-white">{{ __('gallery.best') }}</span>
                      <span x-show="p.size" class="pointer-events-none absolute bottom-1 left-1 rounded bg-black/55 px-1.5 py-0.5 text-[10px] font-medium text-white backdrop-blur-sm" x-text="fmtBytes(p.size)"></span>
                      <button type="button" @click.stop="openViewer(p)" title="{{ __('gallery.view') }}"
                          class="absolute bottom-1 right-1 flex h-6 w-6 items-center justify-center rounded-md bg-black/50 text-white opacity-0 backdrop-blur-sm transition hover:bg-black/70 group-hover:opacity-100"><x-icon name="eye" class="h-3.5 w-3.5" /></button>
                    </div>
                  </template>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>

    {{-- Floating upload / processing card --}}
    <div x-show="state === 'ready' && (uploading || progress.active || uploads.length || failedCount || _mlRunning || peopleScanning)" x-cloak x-transition
        class="fixed bottom-4 right-4 z-[860] w-72 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-xl">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200" x-text="reindexProgress ? @js(__('gallery.reindex_all')) : (progress.active ? @js(__('gallery.processing')) : @js(__('gallery.upload')))"></span>
        <button type="button" @click="dismissUploads()" x-show="! uploading && ! progress.active" class="text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
      </div>
      {{-- Always-visible overall upload counter, so progress is readable without scrolling the list --}}
      <template x-if="uploads.length">
        <div class="mt-2">
          <div class="flex justify-between text-[11px] font-medium text-gray-600 dark:text-gray-300"><span>{{ __('gallery.uploaded_label') }}</span><span class="tabular-nums" x-text="uploadDone() + ' / ' + uploads.length"></span></div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${uploads.length ? (uploadDone() / uploads.length * 100) : 0}%`"></div></div>
        </div>
      </template>
      <template x-if="progress.active">
        <div class="mt-2">
          <div class="flex justify-between text-[11px] text-gray-500 dark:text-gray-400"><span>{{ __('gallery.processing') }}</span><span x-text="progress.done + ' / ' + progress.total"></span></div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${progress.total ? (progress.done / progress.total * 100) : 0}%`"></div></div>
        </div>
      </template>
      {{-- Search re-index (CLIP re-embed). Reuses the _mlRunning lock, so guard the
           face-analysis rows below with ! reindexProgress to avoid a stale 0/0 label. --}}
      <template x-if="reindexProgress">
        <div class="mt-2">
          <div class="flex justify-between text-[11px] text-gray-500 dark:text-gray-400"><span>{{ __('gallery.reindex_all') }}</span><span class="tabular-nums" x-text="reindexProgress.done + ' / ' + reindexProgress.total"></span></div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${reindexProgress.total ? (reindexProgress.done / reindexProgress.total * 100) : 8}%`"></div></div>
        </div>
      </template>
      {{-- Face analysis (ML) — visible so the background face pass isn't a mystery --}}
      <template x-if="_mlRunning && ! reindexProgress">
        <div class="mt-2">
          <div class="flex justify-between text-[11px] text-gray-500 dark:text-gray-400"><span>{{ __('gallery.jobs_analyze') }}</span><span class="tabular-nums" x-text="mlProgress.done + ' / ' + mlProgress.total"></span></div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${mlProgress.total ? (mlProgress.done / mlProgress.total * 100) : 8}%`"></div></div>
        </div>
      </template>
      <template x-if="peopleScanning && ! _mlRunning">
        <div class="mt-2">
          <div class="flex justify-between text-[11px] text-gray-500 dark:text-gray-400"><span>{{ __('gallery.people') }}</span><span class="tabular-nums" x-text="peopleProgress.done + ' / ' + peopleProgress.total"></span></div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${peopleProgress.total ? (peopleProgress.done / peopleProgress.total * 100) : 8}%`"></div></div>
        </div>
      </template>
      <template x-if="failedCount && !progress.active">
        <div class="mt-2 flex items-center justify-between gap-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 px-2.5 py-1.5">
          <span class="text-[11px] text-amber-700 dark:text-amber-300" x-text="failedCount + ' ' + @js(__('gallery.failed_label'))"></span>
          <button type="button" @click="retryFailed()" class="shrink-0 rounded-md bg-gray-900 dark:bg-gray-100 px-2 py-1 text-[11px] font-medium text-white dark:text-gray-900">{{ __('gallery.retry_failed') }}</button>
        </div>
      </template>
      <div class="mt-2 max-h-40 space-y-1.5 overflow-y-auto">
        <template x-for="(u, i) in uploads" :key="i">
          <div>
            <div class="flex items-center gap-2 text-[11px] text-gray-600 dark:text-gray-400"><span class="truncate" x-text="u.name"></span><span class="ml-auto tabular-nums" :class="u.state === 'error' ? 'text-red-500' : (u.state === 'duplicate' ? 'text-gray-400' : '')" :title="u.state === 'error' ? u.error : ''" x-text="u.state === 'error' ? '⚠' : (u.state === 'done' ? '✓' : (u.state === 'duplicate' ? @js(__('gallery.duplicate_skipped')) : (u.state === 'pending' ? '…' : u.progress + '%')))"></span></div>
            <div x-show="u.state === 'uploading'" class="mt-0.5 h-0.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-500 transition-all" :style="`width: ${u.progress}%`"></div></div>
            <p x-show="u.state === 'error' && u.error" class="mt-0.5 text-[10px] leading-tight text-red-500" x-text="u.error"></p>
          </div>
        </template>
      </div>
    </div>

    {{-- Viewer with info panel --}}
    <div x-show="viewer.open" x-cloak @keydown.escape.window="closeViewer()"
        class="fixed inset-0 z-[950] flex bg-black/90" @click.self="closeViewer()">
      <button type="button" @click="closeViewer()" class="absolute right-4 top-4 z-10 text-white/70 hover:text-white"><x-icon name="x-mark" class="h-7 w-7" /></button>
      <div class="flex flex-1 items-center justify-center p-4" x-ref="vstage" @resize.window="_fitViewer()" @click.self="closeViewer()">
        <template x-if="viewer.kind === 'loading'"><svg class="h-8 w-8 animate-spin text-white/60" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg></template>
        <template x-if="viewer.kind === 'image'">
          <div class="relative" @click.stop>
            <img :src="viewer.src" x-ref="vimg" @load="_fitViewer()" x-show="! viewer.motionOn" :style="viewerTransform()" class="max-h-[92vh] max-w-full rounded-lg">
            <template x-if="viewer.motionOn">
              <video :src="viewer.motionSrc" autoplay muted playsinline @ended="stopMotion()" :style="viewerTransform()" class="max-h-[92vh] max-w-full rounded-lg"></video>
            </template>
            {{-- Manual face-tag draw overlay --}}
            <div x-show="faceTag.active && ! viewer.motionOn" class="absolute inset-0 rounded-lg" style="cursor:crosshair;touch-action:none"
                 @pointerdown.stop.prevent="faceDragStart($event)" @pointermove="faceDragMove($event)" @pointerup="faceDragEnd()" @pointercancel="faceDragEnd()">
              <template x-if="faceTag.box">
                <div class="pointer-events-none absolute rounded-sm border-2 border-white shadow-[0_0_0_9999px_rgba(0,0,0,0.4)]" :style="`left:${faceTag.box.x}px;top:${faceTag.box.y}px;width:${faceTag.box.w}px;height:${faceTag.box.h}px`"></div>
              </template>
              <div x-show="faceTag.busy" class="absolute inset-0 flex items-center justify-center rounded-lg bg-black/50">
                <svg class="h-8 w-8 animate-spin text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
              </div>
            </div>
            <button type="button" x-show="viewer.hasMotion && ! viewer.motionOn" @click.stop="playMotion()"
                class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white backdrop-blur-sm transition hover:bg-black/70">
              <x-icon name="play" class="h-4 w-4" />Live
            </button>
            {{-- Toggle manual face tagging (works without the info panel, e.g. on mobile) --}}
            <button type="button" x-show="viewer.kind === 'image' && view !== 'trash'" @click.stop="toggleFaceTag()" :title="'{{ __('gallery.tag_face') }}'"
                :class="faceTag.active ? 'bg-white text-gray-900' : 'bg-black/50 text-white hover:bg-black/70'"
                class="absolute right-3 top-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold backdrop-blur-sm transition">
              <x-icon name="user-plus" class="h-4 w-4" /><span x-text="faceTag.active ? '{{ __('common.cancel') }}' : '{{ __('gallery.tag_face') }}'"></span>
            </button>
          </div>
        </template>
        <template x-if="viewer.kind === 'video'"><video :src="viewer.src" controls autoplay playsinline class="max-h-[92vh] max-w-full rounded-lg" @click.stop></video></template>
      </div>
      {{-- Info panel --}}
      <aside x-show="viewer.photo" class="hidden w-80 shrink-0 overflow-y-auto border-l border-gray-200 bg-white p-6 text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100 md:block">
        <div class="flex items-start justify-between gap-2">
          <h3 class="min-w-0 flex-1 truncate text-base font-semibold" x-text="viewer.photo?.name"></h3>
          <div class="flex shrink-0 items-center gap-1" x-show="view !== 'trash'">
            <button type="button" @click="toggleFavorite(viewer.photo)" :title="viewer.photo?.favorite ? '{{ __('gallery.unfavorite') }}' : '{{ __('gallery.favorite') }}'"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200" :class="viewer.photo?.favorite ? 'text-amber-500 dark:text-amber-400' : ''">
              <x-icon x-show="viewer.photo?.favorite" name="star-solid" class="h-4 w-4" />
              <x-icon x-show="! viewer.photo?.favorite" name="star" class="h-4 w-4" />
            </button>
            <button type="button" @click="viewer.photo?.archived ? unarchive(viewer.photo) : archivePhoto(viewer.photo)" :title="viewer.photo?.archived ? '{{ __('gallery.unarchive_action') }}' : '{{ __('gallery.archive_action') }}'"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200" :class="viewer.photo?.archived ? 'text-gray-900 dark:text-gray-100' : ''">
              <x-icon name="archive-box" class="h-4 w-4" />
            </button>
          </div>
        </div>
        <div x-show="view !== 'trash'" class="mt-4">
          <label class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.caption') }}</label>
          <textarea rows="2" @change="setCaption(viewer.photo, $event.target.value)" :value="viewer.photo?.caption || ''" placeholder="{{ __('gallery.caption_placeholder') }}"
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-500 focus:ring-gray-500"></textarea>
        </div>
        <dl class="mt-5 space-y-4 text-sm">
          <div x-show="viewer.meta?.exif?.taken_at || viewer.photo?.taken_at">
            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.info_date') }}</dt>
            <dd class="mt-0.5" x-text="fmtDate(viewer.meta?.exif?.taken_at || viewer.photo?.taken_at)"></dd>
          </div>
          <div x-show="viewer.meta?.exif?.camera || viewer.photo?.camera">
            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.info_camera') }}</dt>
            <dd class="mt-0.5" x-text="viewer.meta?.exif?.camera || viewer.photo?.camera"></dd>
          </div>
          <div x-show="viewer.photo?.width && viewer.photo?.height">
            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.info_dimensions') }}</dt>
            <dd class="mt-0.5" x-text="viewer.photo?.width + ' × ' + viewer.photo?.height"></dd>
          </div>
          <div x-show="placeText(viewer.meta?.place)">
            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.info_place') }}</dt>
            <dd class="mt-0.5" x-text="placeText(viewer.meta?.place)"></dd>
          </div>
          <div x-show="viewer.photo?.size">
            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.info_size') }}</dt>
            <dd class="mt-0.5" x-text="fmtBytes(viewer.photo?.size)"></dd>
          </div>
        </dl>
        {{-- Faces detected on this photo (with the linked person, if any) --}}
        <div x-show="viewerFaces().length" x-cloak class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4">
          <h4 class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.faces_found') }} <span class="tabular-nums" x-text="'(' + viewerFaces().length + ')'"></span></h4>
          <div class="mt-3 flex flex-wrap gap-3">
            <template x-for="(f, i) in viewerFaces()" :key="i">
              <button type="button" @click="f.personId && openPersonById(f.personId)" :class="f.personId ? 'cursor-pointer' : 'cursor-default'" class="flex w-14 flex-col items-center focus:outline-none">
                <span class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700" :class="f.personId ? 'hover:ring-gray-900 dark:hover:ring-gray-100' : ''" x-init="$nextTick(() => faceThumb(f).then(u => u && $el.querySelector('img')?.setAttribute('src', u)))">
                  <img class="h-full w-full object-cover" alt="">
                </span>
                <span class="mt-1 w-full truncate text-center text-[10px] text-gray-600 dark:text-gray-400" x-text="f.name || '—'"></span>
              </button>
            </template>
          </div>
        </div>
        {{-- Non-destructive edit: rotate / flip / date-time / location --}}
        <div x-show="viewer.photo && view !== 'trash'" class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4">
          <h4 class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.edit_heading') }}</h4>
          <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" @click="rotatePhoto(viewer.photo, -1)" title="{{ __('gallery.rotate_left') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
            <button type="button" @click="rotatePhoto(viewer.photo, 1)" title="{{ __('gallery.rotate_right') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="arrow-uturn-right" class="h-4 w-4" /></button>
            <button type="button" @click="flipPhoto(viewer.photo, 'h')" title="{{ __('gallery.flip_h') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="arrows-right-left" class="h-4 w-4" /></button>
            <button type="button" @click="flipPhoto(viewer.photo, 'v')" title="{{ __('gallery.flip_v') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="arrows-up-down" class="h-4 w-4" /></button>
          </div>
          <label class="mt-3 block text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.edit_datetime') }}
            <input type="datetime-local" :value="toLocalInput(viewer.photo?.taken_at)" @change="setTakenAt(viewer.photo, $event.target.value)"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-500 focus:ring-gray-500">
          </label>
          <button type="button" @click="openLocPicker(viewer.photo)" class="mt-3 inline-flex items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="map-pin" class="h-4 w-4" />{{ __('gallery.edit_location') }}</button>
        </div>
        <div x-ref="minimap" x-show="(viewer.meta?.exif?.lat != null) || (viewer.photo?.lat != null)"
            class="mt-5 h-40 w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800"></div>
      </aside>
    </div>

    {{-- Public album share link (zero-knowledge; the key lives in the URL fragment) --}}
    <div x-show="share.open" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeShare()">
      <div class="absolute inset-0 bg-black/60" @click="closeShare()"></div>
      <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-5 shadow-xl">
        <div class="flex items-start justify-between gap-2">
          <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.share_album') }}</h3>
          <button type="button" @click="closeShare()" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="x-mark" class="h-5 w-5" /></button>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.share_intro') }}</p>

        {{-- Active link --}}
        <div x-show="share.link" x-cloak class="mt-4 rounded-lg border border-gray-200 dark:border-gray-800 p-3">
          <label class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.share_link_label') }}</label>
          <div class="mt-1 flex items-center gap-2">
            <input type="text" readonly :value="share.link" @focus="$event.target.select()" class="w-full rounded-md border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-xs text-gray-700 dark:text-gray-300">
            <button type="button" @click="copyShareLink()" title="{{ __('gallery.share_copy') }}" class="shrink-0 rounded-md bg-gray-100 dark:bg-gray-800 p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="clipboard" class="h-4 w-4" /></button>
          </div>
          <p class="mt-2 text-[11px] leading-relaxed text-gray-400 dark:text-gray-500">{{ __('gallery.share_active_hint') }}</p>
        </div>

        {{-- Options --}}
        <div class="mt-4 space-y-3">
          <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" x-model="share.allowDownload" class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0">
            {{ __('gallery.share_allow_download') }}
          </label>
          <label class="block text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.share_password') }}
            <input type="password" x-model="share.password" autocomplete="new-password" :placeholder="share.album?.share?.hasPassword ? '{{ __('gallery.share_password_set') }}' : '{{ __('gallery.share_password_hint') }}'"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-500 focus:ring-gray-500">
          </label>
          <label class="block text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.share_expiry') }}
            <input type="datetime-local" x-model="share.expiresAt"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-500 focus:ring-gray-500">
          </label>
        </div>

        <p x-show="share.error" x-cloak class="mt-3 text-sm text-red-600 dark:text-red-400" x-text="share.error"></p>

        <div class="mt-5 flex items-center justify-between gap-2">
          <button type="button" x-show="share.album?.share" x-cloak @click="revokeShare()" :disabled="share.busy" class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 disabled:opacity-50">{{ __('gallery.share_revoke') }}</button>
          <div class="ml-auto flex gap-2">
            <button type="button" @click="closeShare()" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('gallery.share_close') }}</button>
            <button type="button" x-show="! share.album?.share" @click="createShare()" :disabled="share.busy" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 dark:bg-gray-100 px-4 py-2 text-sm font-medium text-white dark:text-gray-900 disabled:opacity-50"><x-icon name="link" class="h-4 w-4" />{{ __('gallery.share_create_link') }}</button>
            <button type="button" x-show="share.album?.share" x-cloak @click="updateShare()" :disabled="share.busy" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 dark:bg-gray-100 px-4 py-2 text-sm font-medium text-white dark:text-gray-900 disabled:opacity-50">{{ __('gallery.share_update') }}</button>
          </div>
        </div>
      </div>
    </div>

    {{-- Location picker (Leaflet): click the map to set the photo's place --}}
    <div x-show="loc.open" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeLocPicker()">
      <div class="absolute inset-0 bg-black/60" @click="closeLocPicker()"></div>
      <div class="relative w-full max-w-2xl rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.edit_location') }}</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.location_hint') }}</p>
        <div class="relative z-20 mt-3">
          <form @submit.prevent="geoSearch()" class="flex gap-2">
            <input type="search" x-model="geoQuery" placeholder="{{ __('gallery.search_place') }}"
                class="w-full rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300">
            <button type="submit" :disabled="geoBusy || ! geoQuery.trim()" class="inline-flex shrink-0 items-center rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 disabled:opacity-40"><x-icon name="magnifying-glass" class="h-4 w-4" /></button>
          </form>
          <div x-show="geoResults.length" x-cloak class="absolute z-10 mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-1 shadow-lg">
            <template x-for="(r, i) in geoResults" :key="i">
              <button type="button" @click="pickGeoResult(r)" class="block w-full truncate rounded px-3 py-1.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800" x-text="r.display"></button>
            </template>
          </div>
          <p x-show="geoSearched && ! geoBusy && ! geoResults.length" x-cloak class="mt-1 text-xs text-gray-400">{{ __('gallery.no_place_results') }}</p>
        </div>
        <div x-ref="locmap" class="mt-3 h-72 w-full overflow-hidden rounded-md border border-gray-200 dark:border-gray-800"></div>
        <div class="mt-3 flex items-center justify-between">
          <button type="button" @click="clearLoc()" class="text-sm text-gray-500 hover:text-red-600">{{ __('gallery.location_clear') }}</button>
          <div class="flex gap-2">
            <x-button variant="secondary" type="button" @click="closeLocPicker()">{{ __('common.cancel') }}</x-button>
            <x-button type="button" @click="saveLoc()">{{ __('common.save') }}</x-button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk date/time picker -->
    <div x-show="dateModal" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeBulkDate()">
      <div class="absolute inset-0 bg-black/60" @click="closeBulkDate()"></div>
      <div class="relative w-full max-w-sm rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.bulk_date') }}</h3>
        <input type="datetime-local" x-model="bulkDate" class="mt-3 w-full rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
        <div class="mt-4 flex justify-end gap-2">
          <x-button variant="secondary" type="button" @click="closeBulkDate()">{{ __('common.cancel') }}</x-button>
          <x-button type="button" @click="bulkApplyDate()">{{ __('common.save') }}</x-button>
        </div>
      </div>
    </div>

    {{-- Merge people: pick another person to fold into the current one --}}
    {{-- Add the selected photos to an album --}}
    <div x-show="albumPicker" x-cloak class="fixed inset-0 z-[965] flex items-center justify-center p-4" @keydown.escape.window="albumPicker = false">
      <div class="absolute inset-0 bg-black/60" @click="albumPicker = false"></div>
      <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.add_to_album') }}</h3>
        <p x-show="! albums.length" x-cloak class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.no_albums') }}</p>
        <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
          <template x-for="al in albums" :key="al.id">
            <button type="button" @click="addSelectedToAlbum(al); albumPicker = false" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800">
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-gray-100 dark:bg-gray-800"><x-icon name="folder" class="h-5 w-5 text-gray-400" /></span>
              <span class="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-200" x-text="al.name"></span>
              <span class="shrink-0 text-xs tabular-nums text-gray-400" x-text="albumCount(al)"></span>
            </button>
          </template>
        </div>
        <div class="mt-4 flex items-center justify-between gap-2">
          <button type="button" @click="albumPicker = false; createAlbum()" class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100"><x-icon name="plus" class="h-4 w-4" />{{ __('gallery.new_album') }}</button>
          <x-button variant="secondary" type="button" @click="albumPicker = false">{{ __('common.cancel') }}</x-button>
        </div>
      </div>
    </div>

    {{-- Assign a manually tagged face to a person (existing or new) --}}
    <div x-show="assignPicker" x-cloak class="fixed inset-0 z-[975] flex items-center justify-center p-4" @keydown.escape.window="closeAssign()">
      <div class="absolute inset-0 bg-black/60" @click="closeAssign()"></div>
      <div class="relative w-full max-w-lg rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.assign_heading') }}</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.assign_hint') }}</p>
        <input type="search" x-model="assignQuery" placeholder="{{ __('gallery.person_name') }}" class="mt-3 w-full rounded-md border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        <button type="button" x-show="assignQuery.trim().length" x-cloak @click="assignToNew()" class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-gray-900 dark:bg-gray-100 px-3 py-1.5 text-sm font-medium text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-white">
          <x-icon name="user-plus" class="h-4 w-4" /><span x-text="'{{ __('gallery.assign_new') }}: ' + assignQuery.trim()"></span>
        </button>
        <div class="mt-3 grid max-h-72 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4">
          <template x-for="pp in assignCandidates()" :key="pp.id">
            <button type="button" @click="assignToPerson(pp)" class="group flex flex-col items-center focus:outline-none">
              <span class="relative h-16 w-16 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 group-hover:ring-gray-900 dark:group-hover:ring-gray-100 flex items-center justify-center"
                    x-init="$nextTick(() => personCover(pp) && faceThumb(personCover(pp)))">
                <img x-show="personCover(pp) && faceThumbs[personCover(pp).cropRef]" :src="personCover(pp) && faceThumbs[personCover(pp).cropRef]" class="h-full w-full object-cover">
                <span x-show="! (personCover(pp) && faceThumbs[personCover(pp).cropRef])"><x-icon name="user" class="h-7 w-7 text-gray-300 dark:text-gray-600" /></span>
              </span>
              <span class="mt-1 max-w-full truncate text-xs text-gray-700 dark:text-gray-300" x-text="personLabel(pp) || (@js(__('gallery.person_unnamed')))"></span>
            </button>
          </template>
        </div>
        <div class="mt-4 flex justify-end">
          <x-button variant="secondary" type="button" @click="closeAssign()">{{ __('common.cancel') }}</x-button>
        </div>
      </div>
    </div>

    <div x-show="mergePicker" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeMergePicker()">
      <div class="absolute inset-0 bg-black/60" @click="closeMergePicker()"></div>
      <div class="relative w-full max-w-lg rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.merge_heading') }}</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.merge_hint') }}</p>
        <input type="search" x-model="mergeQuery" placeholder="{{ __('gallery.person_name') }}" class="mt-3 w-full rounded-md border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        <p x-show="! mergeCandidates().length" x-cloak class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.merge_none') }}</p>
        <div class="mt-3 grid max-h-80 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4">
          <template x-for="pp in mergeCandidates()" :key="pp.id">
            <button type="button" @click="mergeInto(pp)" class="group flex flex-col items-center focus:outline-none">
              <span class="relative h-20 w-20 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 group-hover:ring-gray-900 dark:group-hover:ring-gray-100"
                    x-init="$nextTick(() => personCover(pp) && faceThumb(personCover(pp)))">
                <img x-show="personCover(pp) && faceThumbs[personCover(pp).cropRef]" :src="personCover(pp) && faceThumbs[personCover(pp).cropRef]" class="h-full w-full object-cover">
                <span x-show="! (personCover(pp) && faceThumbs[personCover(pp).cropRef])" class="flex h-full w-full items-center justify-center"><x-icon name="user" class="h-8 w-8 text-gray-300 dark:text-gray-600" /></span>
              </span>
              <span class="mt-1 max-w-full truncate text-xs text-gray-700 dark:text-gray-300" x-text="personLabel(pp) || (@js(__('gallery.person_unnamed')))"></span>
              <span class="text-[11px] tabular-nums text-gray-400" x-text="personCount(pp)"></span>
            </button>
          </template>
        </div>
        <div class="mt-4 flex justify-end">
          <x-button variant="secondary" type="button" @click="closeMergePicker()">{{ __('common.cancel') }}</x-button>
        </div>
      </div>
    </div>

    {{-- Reassign a photo's face to another person --}}
    <div x-show="reassignFor" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeReassign()">
      <div class="absolute inset-0 bg-black/60" @click="closeReassign()"></div>
      <div class="relative w-full max-w-lg rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.reassign_heading') }}</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.reassign_hint') }}</p>
        <p x-show="! mergeCandidates().length" x-cloak class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.merge_none') }}</p>
        <div class="mt-3 grid max-h-80 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4">
          <template x-for="pp in mergeCandidates()" :key="pp.id">
            <button type="button" @click="moveFaceToPerson(pp)" class="group flex flex-col items-center focus:outline-none">
              <span class="relative h-20 w-20 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 group-hover:ring-gray-900 dark:group-hover:ring-gray-100"
                    x-init="$nextTick(() => personCover(pp) && faceThumb(personCover(pp)))">
                <img x-show="personCover(pp) && faceThumbs[personCover(pp).cropRef]" :src="personCover(pp) && faceThumbs[personCover(pp).cropRef]" class="h-full w-full object-cover">
                <span x-show="! (personCover(pp) && faceThumbs[personCover(pp).cropRef])" class="flex h-full w-full items-center justify-center"><x-icon name="user" class="h-8 w-8 text-gray-300 dark:text-gray-600" /></span>
              </span>
              <span class="mt-1 max-w-full truncate text-xs text-gray-700 dark:text-gray-300" x-text="personLabel(pp) || (@js(__('gallery.person_unnamed')))"></span>
              <span class="text-[11px] tabular-nums text-gray-400" x-text="personCount(pp)"></span>
            </button>
          </template>
        </div>
        <div class="mt-4 flex justify-end">
          <x-button variant="secondary" type="button" @click="closeReassign()">{{ __('common.cancel') }}</x-button>
        </div>
      </div>
    </div>

    {{-- Link a person to a contact (loads the /store manifest lazily) --}}
    <div x-show="linkPicker" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeLinkPicker()">
      <div class="absolute inset-0 bg-black/60" @click="closeLinkPicker()"></div>
      <div class="relative w-full max-w-lg rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.link_heading') }}</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.link_hint') }}</p>
        <input type="search" x-model="linkQuery" placeholder="{{ __('contacts.search') }}" class="mt-3 w-full rounded-md border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        <p x-show="! linkSuggestions().length" x-cloak class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.link_none') }}</p>
        <div class="mt-3 max-h-80 divide-y divide-gray-100 dark:divide-gray-800 overflow-y-auto">
          <template x-for="c in linkSuggestions()" :key="c.id">
            <button type="button" @click="linkTo(c)" class="flex w-full items-center gap-3 px-1 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-800">
              <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-xs font-semibold text-gray-500"><x-icon name="user" class="h-4 w-4 text-gray-400" /></span>
              <span class="min-w-0 flex-1 truncate text-sm text-gray-800 dark:text-gray-200" x-text="_contactName(c) || '{{ __('contacts.unnamed') }}'"></span>
            </button>
          </template>
        </div>
        <div class="mt-4 flex justify-end">
          <x-button variant="secondary" type="button" @click="closeLinkPicker()">{{ __('common.cancel') }}</x-button>
        </div>
      </div>
    </div>

    {{-- After linking: choose which of the person's photos becomes the contact avatar (then crop) --}}
    <div x-show="avatarChoose" x-cloak class="fixed inset-0 z-[970] flex items-center justify-center p-4" @keydown.escape.window="closeAvatarChoose()">
      <div class="absolute inset-0 bg-black/60" @click="closeAvatarChoose()"></div>
      <div class="relative w-full max-w-lg rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.link_choose_photo') }}</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.link_choose_hint') }}</p>
        <div class="mt-3 grid max-h-80 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4">
          <template x-for="p in _choosePhotos" :key="p.id">
            <button type="button" @click="chooseAvatarPhoto(p)" class="group aspect-square overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-gray-900 dark:hover:ring-gray-100 focus:outline-none" x-init="$nextTick(() => thumbFor(p).then(u => u && $el.querySelector('img')?.setAttribute('src', u)))">
              <img class="h-full w-full object-cover" alt="">
            </button>
          </template>
        </div>
        <div class="mt-4 flex justify-end">
          <x-button variant="secondary" type="button" @click="closeAvatarChoose()">{{ __('gallery.link_skip_photo') }}</x-button>
        </div>
      </div>
    </div>
  </div>
</x-layouts.app>
