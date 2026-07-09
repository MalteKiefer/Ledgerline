<x-layouts.app :title="__('gallery.title')">
  <div x-data="vaultGallery({
        uploadUrl: '{{ url('/gallery/upload') }}',
        processUrl: '{{ url('/gallery/process') }}',
        rawBase: '{{ url('/gallery/raw') }}',
        blobBase: '{{ url('/gallery/blob') }}',
        usageUrl: '{{ url('/gallery/usage') }}',
        reconcileUrl: '{{ url('/gallery/blobs/reconcile') }}',
        embedTextUrl: '{{ url('/gallery/embed-text') }}',
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
          <button type="button" @click="view = 'trash'"
              :class="view === 'trash' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="trash" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.trash') }}</span><span x-show="trashCount()" class="text-xs tabular-nums text-gray-400" x-text="trashCount()"></span>
          </button>
        </nav>
      </aside>

      <div class="min-w-0 flex-1">
        {{-- Bulk-select bar --}}
        <div x-show="selectedCount" x-cloak class="sticky top-2 z-20 mb-3 flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-3 py-2 shadow-sm">
          <button type="button" @click="clearSelection()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="x-mark" class="h-5 w-5" /></button>
          <span class="text-sm font-medium text-gray-800 dark:text-gray-200" x-text="@js(__('gallery.selected', ['count' => '{n}'])).replace('{n}', selectedCount)"></span>
          <button type="button" @click="selectAllVisible()" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">{{ __('gallery.select_all') }}</button>
          <div class="ml-auto flex items-center gap-2">
            <template x-if="view === 'library'">
              <span class="flex items-center gap-2">
                <div x-data="{ open: false }" class="relative">
                  <button type="button" @click="open = ! open" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300"><x-icon name="folder" class="h-4 w-4" />{{ __('gallery.add_to_album') }}</button>
                  <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-1 w-52 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-1 shadow-lg">
                    <template x-for="al in albums" :key="al.id">
                      <button type="button" @click="addSelectedToAlbum(al); open = false" class="block w-full truncate rounded px-3 py-1.5 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800" x-text="al.name"></button>
                    </template>
                    <button type="button" @click="open = false; createAlbum()" class="mt-0.5 flex w-full items-center gap-1.5 border-t border-gray-100 dark:border-gray-800 px-3 py-1.5 text-left text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="plus" class="h-4 w-4" />{{ __('gallery.new_album') }}</button>
                  </div>
                </div>
                <button type="button" @click="bulkTrash()" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 dark:bg-gray-100 px-3 py-1.5 text-sm font-medium text-white dark:text-gray-900"><x-icon name="trash" class="h-4 w-4" />{{ __('gallery.delete') }}</button>
              </span>
            </template>
            <template x-if="view === 'trash'">
              <span class="flex gap-2">
                <button type="button" @click="bulkRestore()" class="rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300">{{ __('gallery.restore') }}</button>
                <button type="button" @click="bulkPurge()" class="rounded-lg bg-red-500 px-3 py-1.5 text-sm font-medium text-white">{{ __('gallery.purge') }}</button>
              </span>
            </template>
          </div>
        </div>

        {{-- Mobile view switch --}}
        <div class="mb-4 -mx-1 flex gap-2 overflow-x-auto px-1 pb-1 md:hidden">
          <button type="button" @click="view = 'library'; clearSelection()" :class="view === 'library' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.library') }}</button>
          <button type="button" @click="view = 'albums'; clearSelection()" :class="view === 'albums' || view === 'album' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.albums') }}</button>
          <button type="button" @click="view = 'people'; clearSelection()" :class="view === 'people' || view === 'person' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.people') }}</button>
          <button type="button" @click="view = 'duplicates'; clearSelection()" :class="view === 'duplicates' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.duplicates') }}</button>
          <button type="button" @click="view = 'map'; clearSelection()" :class="view === 'map' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.map') }}</button>
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
              <h2 x-show="group.label" class="mb-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300" x-text="group.label"></h2>
              <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
                <template x-for="p in group.photos" :key="p.id">
                  <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
                       :class="isSelected(p.id) ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-gray-100 ring-offset-white dark:ring-offset-gray-950' : ''" x-init="thumbFor(p)">
                    <button type="button" @click="openViewer(p)" class="block h-full w-full">
                      <img x-show="thumbs[p.id]" :src="thumbs[p.id]" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                      <div x-show="!thumbs[p.id]" class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900">
                        <svg x-show="!p.thumbRef" class="h-5 w-5 animate-spin text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                      </div>
                      <template x-if="p.media_type === 'video'"><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span></span></template>
                      <template x-if="p.motionRef && p.media_type !== 'video'"><span class="pointer-events-none absolute left-1.5 top-1.5 rounded bg-black/45 px-1 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-white backdrop-blur-sm">Live</span></template>
                    </button>
                    <label class="absolute left-2 top-2 z-10 cursor-pointer" :class="selectedCount ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'" @click.stop>
                      <input type="checkbox" :checked="isSelected(p.id)" @change="toggleSelect(p.id)" class="h-4 w-4 rounded border-white/80 bg-black/30 text-gray-900 focus:ring-0 focus:ring-offset-0">
                    </label>
                    <button type="button" @click.stop="trash(p)" title="{{ __('gallery.delete') }}"
                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/40 text-white opacity-0 backdrop-blur-sm transition hover:bg-red-500 group-hover:opacity-100"><x-icon name="trash" class="h-4 w-4" /></button>
                  </div>
                </template>
              </div>
            </section>
          </template>
        </div>

        {{-- TRASH --}}
        <div x-show="view === 'trash'">
          <div class="mb-3 flex items-center justify-between">
            <p class="text-xs text-gray-400 dark:text-gray-500" x-text="trashCount() + ' · ' + @js(__('gallery.trash'))"></p>
            <button type="button" x-show="trashCount()" @click="emptyTrash()" class="text-xs font-medium text-red-500 hover:text-red-600">{{ __('gallery.empty_trash') }}</button>
          </div>
          <template x-if="! trashCount()"><p class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.trash_empty') }}</p></template>
          <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
            <template x-for="p in trashedPhotos" :key="p.id">
              <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
                   :class="isSelected(p.id) ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-gray-100 ring-offset-white dark:ring-offset-gray-950' : ''" x-init="thumbFor(p)">
                <img x-show="thumbs[p.id]" :src="thumbs[p.id]" loading="lazy" class="h-full w-full object-cover opacity-70">
                <div x-show="!thumbs[p.id]" class="h-full w-full bg-gray-200 dark:bg-gray-700"></div>
                <label class="absolute left-2 top-2 z-10 cursor-pointer" @click.stop>
                  <input type="checkbox" :checked="isSelected(p.id)" @change="toggleSelect(p.id)" class="h-4 w-4 rounded border-white/80 bg-black/30 text-gray-900 focus:ring-0 focus:ring-offset-0">
                </label>
                <div class="absolute inset-0 flex items-center justify-center gap-1.5 bg-black/40 opacity-0 transition group-hover:opacity-100">
                  <button type="button" @click="restore(p)" title="{{ __('gallery.restore') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-gray-800 hover:bg-white"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
                  <button type="button" @click="purge(p)" title="{{ __('gallery.purge') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-red-500 text-white hover:bg-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
              </div>
            </template>
          </div>
        </div>

        {{-- MAP --}}
        <div x-show="view === 'map'">
          <template x-if="! mapPhotos.length"><p class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.no_results') }}</p></template>
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
                  <button type="button" @click="renameAlbum(currentAlbum)" title="{{ __('gallery.rename') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="pencil" class="h-4 w-4" /></button>
                  <button type="button" @click="deleteAlbum(currentAlbum)" title="{{ __('gallery.delete_album') }}" class="rounded-lg p-2 text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
              </div>
              <template x-if="! albumCount(currentAlbum)"><p class="mt-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.album_empty') }}</p></template>
              <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
                <template x-for="p in albumPhotos(currentAlbum)" :key="p.id">
                  <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800" x-init="thumbFor(p)">
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
          {{-- Toolbar: only when results exist --}}
          <div x-show="people.length && ! peopleScanning" class="mb-5 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.people') }} <span class="ml-1 text-sm font-normal tabular-nums text-gray-400" x-text="people.length"></span></h2>
            <div class="flex items-center gap-2">
              @include('gallery._scan_scope')
              <button type="button" @click="scanFaces()" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                <x-icon name="arrow-path" class="h-4 w-4" />{{ __('gallery.rescan') }}
              </button>
            </div>
          </div>

          {{-- Scanning card --}}
          <div x-show="peopleScanning" x-cloak class="mx-auto mt-8 flex max-w-sm flex-col items-center rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-10 text-center shadow-sm">
            <svg class="h-8 w-8 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
            <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('gallery.scanning') }}</p>
            <div class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-gray-800 dark:bg-gray-200 transition-all duration-300" :style="`width: ${peopleProgress.total ? (peopleProgress.done / peopleProgress.total * 100) : 8}%`"></div></div>
            <p class="mt-2 text-xs tabular-nums text-gray-400" x-text="peopleProgress.done + ' / ' + peopleProgress.total"></p>
          </div>

          {{-- Empty / first-run hero --}}
          <div x-show="! people.length && ! peopleScanning" x-cloak class="mx-auto mt-8 flex max-w-md flex-col items-center rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-800 p-12 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="user" class="h-8 w-8 text-gray-400 dark:text-gray-500" /></div>
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.no_people') }}</p>
            <div class="mt-5 flex items-center gap-2">
              @include('gallery._scan_scope')
              <button type="button" @click="scanFaces()" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 dark:bg-gray-100 px-5 py-2.5 text-sm font-medium text-white dark:text-gray-900 shadow-sm transition hover:bg-gray-800 dark:hover:bg-white">
                <x-icon name="sparkles" class="h-4 w-4" />{{ __('gallery.scan_faces') }}
              </button>
            </div>
          </div>

          {{-- People grid --}}
          <div x-show="people.length && ! peopleScanning" class="grid grid-cols-3 gap-x-4 gap-y-6 sm:grid-cols-4 lg:grid-cols-6">
            <template x-for="pp in people" :key="pp.id">
              <button type="button" @click="openPerson(pp)" class="group flex flex-col items-center focus:outline-none">
                <div class="relative aspect-square w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 transition duration-300 group-hover:ring-2 group-hover:ring-gray-900 dark:group-hover:ring-gray-100 group-hover:shadow-md"
                     x-init="$nextTick(() => personCover(pp) && faceThumb(personCover(pp)))">
                  <img x-show="personCover(pp) && faceThumbs[personCover(pp).cropRef]" :src="personCover(pp) && faceThumbs[personCover(pp).cropRef]" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                  <div x-show="! (personCover(pp) && faceThumbs[personCover(pp).cropRef])" class="flex h-full w-full items-center justify-center"><x-icon name="user" class="h-8 w-8 text-gray-300 dark:text-gray-600" /></div>
                </div>
                <p class="mt-2 max-w-full truncate text-sm font-medium text-gray-800 dark:text-gray-200" x-text="pp.name || (@js(__('gallery.person_unnamed')))"></p>
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
                <h2 class="truncate text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="currentPerson?.name || (@js(__('gallery.person_unnamed')))"></h2>
                <span class="text-xs tabular-nums text-gray-400" x-text="personCount(currentPerson)"></span>
                <div class="ml-auto flex items-center gap-1.5">
                  <button type="button" @click="renamePerson(currentPerson)" title="{{ __('gallery.rename') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="pencil" class="h-4 w-4" /></button>
                  <button type="button" @click="hidePerson(currentPerson)" title="{{ __('gallery.hide') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="x-mark" class="h-4 w-4" /></button>
                </div>
              </div>
              <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
                <template x-for="p in personPhotos(currentPerson)" :key="p.id">
                  <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800" x-init="thumbFor(p)">
                    <button type="button" @click="openViewer(p)" class="block h-full w-full">
                      <img x-show="thumbs[p.id]" :src="thumbs[p.id]" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                      <div x-show="!thumbs[p.id]" class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div>
                    </button>
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
                  <span class="text-[11px] text-gray-400" x-text="'· ' + @js(__('gallery.keep_hint'))"></span>
                </div>
                <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-4 lg:grid-cols-6">
                  <template x-for="(p, pi) in group" :key="p.id">
                    <div class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800 ring-1 ring-transparent transition" :class="pi === 0 ? 'ring-2 ring-emerald-400' : ''" x-init="thumbFor(p)">
                      <button type="button" @click="openViewer(p)" class="block h-full w-full">
                        <img x-show="thumbs[p.id]" :src="thumbs[p.id]" loading="lazy" class="h-full w-full object-cover">
                        <div x-show="!thumbs[p.id]" class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div>
                      </button>
                      <span x-show="p.size" class="pointer-events-none absolute bottom-1 left-1 rounded bg-black/55 px-1.5 py-0.5 text-[10px] font-medium text-white backdrop-blur-sm" x-text="fmtBytes(p.size)"></span>
                      <span x-show="pi === 0" class="pointer-events-none absolute left-1 top-1 rounded-full bg-emerald-500 px-1.5 py-0.5 text-[10px] font-medium text-white">{{ __('gallery.best') }}</span>
                      <button type="button" @click.stop="keepOne(group, p)" title="{{ __('gallery.keep') }}"
                          class="absolute bottom-1 right-1 inline-flex items-center gap-1 rounded-md bg-gray-900/90 px-2 py-1 text-[10px] font-medium text-white opacity-0 backdrop-blur-sm transition hover:bg-gray-900 group-hover:opacity-100"><x-icon name="check" class="h-3 w-3" />{{ __('gallery.keep') }}</button>
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
    <div x-show="state === 'ready' && (uploading || progress.active || uploads.length)" x-cloak x-transition
        class="fixed bottom-4 right-4 z-[860] w-72 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-xl">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200" x-text="progress.active ? @js(__('gallery.processing')) : @js(__('gallery.upload'))"></span>
        <button type="button" @click="dismissUploads()" x-show="! uploading && ! progress.active" class="text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
      </div>
      <template x-if="progress.active">
        <div class="mt-2">
          <div class="flex justify-between text-[11px] text-gray-500 dark:text-gray-400"><span>{{ __('gallery.processing') }}</span><span x-text="progress.done + ' / ' + progress.total"></span></div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${progress.total ? (progress.done / progress.total * 100) : 0}%`"></div></div>
        </div>
      </template>
      <div class="mt-2 max-h-40 space-y-1.5 overflow-y-auto">
        <template x-for="(u, i) in uploads" :key="i">
          <div>
            <div class="flex items-center gap-2 text-[11px] text-gray-600 dark:text-gray-400"><span class="truncate" x-text="u.name"></span><span class="ml-auto tabular-nums" :class="u.state === 'duplicate' ? 'text-gray-400' : ''" x-text="u.state === 'error' ? '⚠' : (u.state === 'done' ? '✓' : (u.state === 'duplicate' ? @js(__('gallery.duplicate_skipped')) : (u.state === 'pending' ? '…' : u.progress + '%')))"></span></div>
            <div x-show="u.state === 'uploading'" class="mt-0.5 h-0.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-500 transition-all" :style="`width: ${u.progress}%`"></div></div>
          </div>
        </template>
      </div>
    </div>

    {{-- Viewer with info panel --}}
    <div x-show="viewer.open" x-cloak @keydown.escape.window="closeViewer()"
        class="fixed inset-0 z-[950] flex bg-black/90" @click.self="closeViewer()">
      <button type="button" @click="closeViewer()" class="absolute right-4 top-4 z-10 text-white/70 hover:text-white"><x-icon name="x-mark" class="h-7 w-7" /></button>
      <div class="flex flex-1 items-center justify-center p-4" @click.self="closeViewer()">
        <template x-if="viewer.kind === 'loading'"><svg class="h-8 w-8 animate-spin text-white/60" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg></template>
        <template x-if="viewer.kind === 'image'">
          <div class="relative" @click.stop>
            <img :src="viewer.src" x-show="! viewer.motionOn" class="max-h-[92vh] max-w-full rounded-lg">
            <template x-if="viewer.motionOn">
              <video :src="viewer.motionSrc" autoplay muted playsinline @ended="stopMotion()" class="max-h-[92vh] max-w-full rounded-lg"></video>
            </template>
            <button type="button" x-show="viewer.hasMotion && ! viewer.motionOn" @click.stop="playMotion()"
                class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white backdrop-blur-sm transition hover:bg-black/70">
              <x-icon name="play" class="h-4 w-4" />Live
            </button>
          </div>
        </template>
        <template x-if="viewer.kind === 'video'"><video :src="viewer.src" controls autoplay playsinline class="max-h-[92vh] max-w-full rounded-lg" @click.stop></video></template>
      </div>
      {{-- Info panel --}}
      <aside x-show="viewer.photo" class="hidden w-80 shrink-0 overflow-y-auto border-l border-gray-200 bg-white p-6 text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100 md:block">
        <h3 class="truncate text-base font-semibold" x-text="viewer.photo?.name"></h3>
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
        <div x-ref="minimap" x-show="(viewer.meta?.exif?.lat != null) || (viewer.photo?.lat != null)"
            class="mt-5 h-40 w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800"></div>
      </aside>
    </div>
  </div>
</x-layouts.app>
