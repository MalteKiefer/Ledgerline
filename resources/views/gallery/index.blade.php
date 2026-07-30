<x-layouts.app :title="__('gallery.title')">
  <div x-data="vaultGallery({
        photoBase: '{{ url('/gallery/photos') }}',
        uploadUrl: '{{ url('/gallery/photos') }}',
        chunkBase: '{{ url('/gallery/photos/chunk') }}',
        dataUrl: '{{ url('/gallery/data') }}',
        trashUrl: '{{ url('/gallery/trash') }}',
        emptyTrashUrl: '{{ url('/gallery/photos/trash/empty') }}',
        albumsBase: '{{ url('/gallery/albums') }}',
        sharesUrl: '{{ url('/gallery/rel-shares') }}',
        shareBase: '{{ url('/gallery-share') }}',
        geocodeUrl: '{{ url('/gallery/geocode') }}',
        token: '{{ csrf_token() }}',
     }, {
        loadFailed: @js(__('gallery.load_failed')),
        deleteConfirm: @js(__('gallery.delete_confirm')),
        purgeConfirm: @js(__('gallery.purge_confirm')),
        emptyTrashConfirm: @js(__('gallery.empty_trash_confirm')),
        albumName: @js(__('gallery.album_name')),
        deleteAlbumConfirm: @js(__('gallery.delete_album_confirm')),
        create: @js(__('gallery.create')),
        save: @js(__('gallery.save')),
        uploadErrQuota: @js(__('gallery.upload_err_quota')),
        uploadErrNetwork: @js(__('gallery.upload_err_network')),
        uploadErrTimeout: @js(__('gallery.upload_err_timeout')),
        uploadErrFailed: @js(__('gallery.upload_err_failed')),
        uploadErrGeneric: @js(__('gallery.upload_err_generic')),
        shareError: @js(__('gallery.share_error')),
        shareCopied: @js(__('gallery.share_copied')),
     }, @js([
        'photos' => $photos ?? [],
        'albums' => $albums ?? [],
        'usage' => $usage ?? ['used' => 0, 'quota' => 0],
     ]))">

    <div x-show="dragging" x-cloak @drop.prevent="drop($event)" @dragover.prevent
        class="fixed inset-0 z-[900] flex items-center justify-center bg-gray-900/60 p-8 backdrop-blur-sm">
      <div class="rounded-3xl border-4 border-dashed border-white/70 px-16 py-24 text-center text-lg font-medium text-white">{{ __('gallery.drop_hint') }}</div>
    </div>

    <x-page-heading :title="__('gallery.title')">
      <x-slot:actions>
        <div class="flex items-center gap-1.5">
          <span class="mr-1 text-xs tabular-nums text-gray-400 dark:text-gray-500" x-text="photoCount() + ' · ' + fmtBytes(usage.used)"></span>
          <x-button variant="primary" @click="$refs.picker.click()">
            <x-icon name="arrow-up-tray" class="mr-1.5 h-4 w-4" />{{ __('gallery.upload') }}
          </x-button>
          <input x-ref="picker" type="file" accept="image/*,video/*,.heic,.heif,.mov" multiple class="hidden" @change="upload($event.target.files); $event.target.value = ''">
        </div>
      </x-slot:actions>
    </x-page-heading>

    <div class="mt-6 flex gap-6">
      {{-- Sidebar --}}
      <aside class="hidden w-44 shrink-0 md:block">
        <nav class="sticky top-6 space-y-0.5 rounded-xl bg-white dark:bg-[#1c1c1e] p-2 ring-1 ring-black/[0.06] dark:ring-white/10">
          <button type="button" @click="view = 'library'"
              :class="view === 'library' ? 'bg-accent/10 font-medium text-accent' : 'text-gray-600 dark:text-gray-400 hover:bg-accent/5'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#7066f5"><x-icon name="photo" class="h-4 w-4" /></span><span class="flex-1 text-left">{{ __('gallery.library') }}</span><span class="text-xs tabular-nums text-gray-400" x-text="photoCount()"></span>
          </button>
          <button type="button" @click="view = 'memories'" x-show="memoryCount()" x-cloak
              :class="view === 'memories' ? 'bg-accent/10 font-medium text-accent' : 'text-gray-600 dark:text-gray-400 hover:bg-accent/5'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#9e70fa"><x-icon name="sparkles" class="h-4 w-4" /></span><span class="flex-1 text-left">{{ __('gallery.memories') }}</span><span class="text-xs tabular-nums text-gray-400" x-text="memoryCount()"></span>
          </button>
          <button type="button" @click="view = 'favorites'"
              :class="view === 'favorites' ? 'bg-accent/10 font-medium text-accent' : 'text-gray-600 dark:text-gray-400 hover:bg-accent/5'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#d9a441"><x-icon name="star" class="h-4 w-4" /></span><span class="flex-1 text-left">{{ __('gallery.favorites') }}</span><span x-show="favoriteCount()" class="text-xs tabular-nums text-gray-400" x-text="favoriteCount()"></span>
          </button>
          <button type="button" @click="view = 'albums'"
              :class="view === 'albums' || view === 'album' ? 'bg-accent/10 font-medium text-accent' : 'text-gray-600 dark:text-gray-400 hover:bg-accent/5'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#3b9fd6"><x-icon name="folder" class="h-4 w-4" /></span><span class="flex-1 text-left">{{ __('gallery.albums') }}</span><span x-show="albums.length" class="text-xs tabular-nums text-gray-400" x-text="albums.length"></span>
          </button>
          <button type="button" @click="view = 'trash'"
              :class="view === 'trash' ? 'bg-accent/10 font-medium text-accent' : 'text-gray-600 dark:text-gray-400 hover:bg-accent/5'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#6b7280"><x-icon name="trash" class="h-4 w-4" /></span><span class="flex-1 text-left">{{ __('gallery.trash') }}</span><span x-show="trashCount()" class="text-xs tabular-nums text-gray-400" x-text="trashCount()"></span>
          </button>
        </nav>
      </aside>

      <div class="min-w-0 flex-1">
        {{-- Bulk-select bar --}}
        <div x-show="selectedCount" x-cloak class="fixed bottom-5 left-1/2 z-40 flex max-w-[calc(100vw-1.5rem)] -translate-x-1/2 items-center gap-3 overflow-x-auto rounded-full border border-black/[0.06] dark:border-white/10 bg-white/95 dark:bg-[#1c1c1e]/95 px-4 py-2 shadow-xl backdrop-blur">
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
                <button type="button" @click="bulkTrash()" title="{{ __('gallery.delete') }}" class="flex h-9 w-9 items-center justify-center rounded-full ll-accent hover:opacity-90"><x-icon name="trash" class="h-5 w-5" /></button>
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
          <button type="button" @click="view = 'library'; clearSelection()" :class="view === 'library' ? 'bg-accent text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.library') }}</button>
          <button type="button" x-show="memoryCount()" x-cloak @click="view = 'memories'; clearSelection()" :class="view === 'memories' ? 'bg-accent text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.memories') }}</button>
          <button type="button" @click="view = 'favorites'; clearSelection()" :class="view === 'favorites' ? 'bg-accent text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.favorites') }}</button>
          <button type="button" @click="view = 'albums'; clearSelection()" :class="view === 'albums' || view === 'album' ? 'bg-accent text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.albums') }}</button>
          <button type="button" @click="view = 'trash'; clearSelection()" :class="view === 'trash' ? 'bg-accent text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="shrink-0 rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.trash') }} <span x-show="trashCount()" x-text="'('+trashCount()+')'"></span></button>
        </div>

        {{-- LIBRARY --}}
        <div x-show="view === 'library'">
          <div class="relative mb-4" x-show="libraryPhotos.length || isSearching">
            <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input type="search" x-model="query" @input="runSearch()" placeholder="{{ __('gallery.search_placeholder') }}"
                class="w-full rounded-lg border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] py-2 pl-9 pr-9 text-sm shadow-sm focus:border-accent focus:ring-accent">
            <button type="button" x-show="query" @click="clearSearch()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
          </div>

          <template x-if="isSearching && ! displayGroups.length">
            <x-empty-state class="mt-10">{{ __('gallery.no_results') }}</x-empty-state>
          </template>
          <template x-if="! isSearching && ! libraryPhotos.length && ! uploading">
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
                       :class="isSelected(p.id) ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-gray-100 ring-offset-white dark:ring-offset-gray-950' : ''">
                    <button type="button" @click="openViewer(p)" class="block h-full w-full">
                      <template x-if="p.hasThumb">
                        <img :src="thumbUrl(p)" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                      </template>
                      <template x-if="! p.hasThumb">
                        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"><x-icon name="photo" class="h-5 w-5 text-gray-300 dark:text-gray-600" /></div>
                      </template>
                      <template x-if="p.kind === 'video'"><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span></span></template>
                      <template x-if="p.hasMotion && p.kind !== 'video'"><span class="pointer-events-none absolute left-1.5 top-1.5 rounded bg-black/45 px-1 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-white backdrop-blur-sm">Live</span></template>
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
                    <button type="button" @click.stop="trashPhoto(p)" title="{{ __('gallery.delete') }}"
                        class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/40 text-white opacity-0 backdrop-blur-sm transition hover:bg-red-500 group-hover:opacity-100"><x-icon name="trash" class="h-4 w-4" /></button>
                  </div>
                </template>
              </div>
            </section>
          </template>
          {{-- Infinite-scroll sentinel --}}
          <div x-show="hasMore" x-intersect.margin.800px="loadMore()" class="flex items-center justify-center py-6">
            <x-icon name="arrow-path" class="h-5 w-5 animate-spin text-gray-300 dark:text-gray-600" />
          </div>
        </div>

        {{-- TRASH --}}
        <div x-show="view === 'trash'">
          <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.trash') }} <span class="ml-1 text-sm font-normal tabular-nums text-gray-400" x-text="trashCount()"></span></h2>
            <x-button x-show="trashCount()" variant="danger" @click="emptyTrash()">
              <x-icon name="trash" class="mr-1.5 h-4 w-4" />{{ __('gallery.empty_trash') }}
            </x-button>
          </div>
          <template x-if="! trashCount()"><x-empty-state class="mt-10">{{ __('gallery.trash_empty') }}</x-empty-state></template>
          <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
            <template x-for="p in trashedPhotos" :key="p.id">
              <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
                   :class="isSelected(p.id) ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-gray-100 ring-offset-white dark:ring-offset-gray-950' : ''">
                <template x-if="p.hasThumb"><img :src="thumbUrl(p)" loading="lazy" class="h-full w-full object-cover opacity-70"></template>
                <template x-if="! p.hasThumb"><div class="h-full w-full bg-gray-200 dark:bg-gray-700"></div></template>
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
          <template x-if="! memoryCount()"><x-empty-state class="mt-10">{{ __('gallery.memories_empty') }}</x-empty-state></template>
          <template x-for="grp in memories" :key="grp.year">
            <section class="mb-6">
              <h3 class="mb-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300" x-text="grp.yearsAgo === 1 ? '{{ __('gallery.memories_year_ago') }}' : grp.yearsAgo + ' {{ __('gallery.memories_years_ago') }}'"></h3>
              <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
                <template x-for="p in grp.photos" :key="p.id">
                  <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                    <button type="button" @click="openViewer(p)" class="block h-full w-full">
                      <template x-if="p.hasThumb"><img :src="thumbUrl(p)" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"></template>
                      <template x-if="! p.hasThumb"><div class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div></template>
                      <template x-if="p.kind === 'video'"><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span></span></template>
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
          <template x-if="! favoriteCount()"><x-empty-state class="mt-10">{{ __('gallery.favorites_empty') }}</x-empty-state></template>
          <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
            <template x-for="p in favoritePhotos" :key="p.id">
              <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
                   :class="isSelected(p.id) ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-gray-100 ring-offset-white dark:ring-offset-gray-950' : ''">
                <button type="button" @click="openViewer(p)" class="block h-full w-full">
                  <template x-if="p.hasThumb"><img :src="thumbUrl(p)" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"></template>
                  <template x-if="! p.hasThumb"><div class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div></template>
                  <template x-if="p.kind === 'video'"><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span></span></template>
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

        {{-- ALBUMS (list) --}}
        <div x-show="view === 'albums'">
          <div x-show="albums.length" class="mb-5 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.albums') }} <span class="ml-1 text-sm font-normal tabular-nums text-gray-400" x-text="albums.length"></span></h2>
            <x-button variant="secondary" @click="createAlbum()"><x-icon name="plus" class="mr-1.5 h-4 w-4" />{{ __('gallery.new_album') }}</x-button>
          </div>

          <div x-show="! albums.length" x-cloak class="mx-auto mt-8 flex max-w-md flex-col items-center rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-800 p-12 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="folder" class="h-8 w-8 text-gray-400 dark:text-gray-500" /></div>
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.no_albums') }}</p>
            <x-button variant="secondary" @click="createAlbum()" class="mt-5"><x-icon name="plus" class="mr-1.5 h-4 w-4" />{{ __('gallery.new_album') }}</x-button>
          </div>

          <div x-show="albums.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <template x-for="al in albumsSorted" :key="al.id">
              <button type="button" @click="openAlbum(al)" class="group text-left focus:outline-none">
                <div class="relative aspect-square overflow-hidden rounded-2xl bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-800 transition duration-300 group-hover:shadow-md group-hover:ring-gray-300 dark:group-hover:ring-gray-700">
                  <template x-if="albumCover(al)"><img :src="thumbUrl(albumCover(al))" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"></template>
                  <template x-if="! albumCover(al)"><div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"><x-icon name="folder" class="h-9 w-9 text-gray-300 dark:text-gray-600" /></div></template>
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
                <div class="ml-auto">
                  <x-action-menu :aria-label="__('common.actions')">
                    <x-action-menu-item icon="share" @click="openShare(currentAlbum)">{{ __('gallery.share') }}</x-action-menu-item>
                    <x-action-menu-item icon="pencil" @click="renameAlbum(currentAlbum)">{{ __('gallery.rename') }}</x-action-menu-item>
                    <x-action-menu-item icon="trash" danger @click="deleteAlbum(currentAlbum)">{{ __('gallery.delete_album') }}</x-action-menu-item>
                  </x-action-menu>
                </div>
              </div>
              <template x-if="! albumCount(currentAlbum)"><x-empty-state class="mt-10">{{ __('gallery.album_empty') }}</x-empty-state></template>
              <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
                <template x-for="p in albumPhotos(currentAlbum)" :key="p.id">
                  <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                    <button type="button" @click="openViewer(p)" class="block h-full w-full">
                      <template x-if="p.hasThumb"><img :src="thumbUrl(p)" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"></template>
                      <template x-if="! p.hasThumb"><div class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div></template>
                      <template x-if="p.kind === 'video'"><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span></span></template>
                    </button>
                    <div class="absolute right-1.5 top-1.5 flex gap-1 opacity-0 transition group-hover:opacity-100">
                      <button type="button" @click.stop="setAlbumCover(currentAlbum, p)" title="{{ __('gallery.set_cover') }}" class="flex h-7 w-7 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm hover:bg-black/70"><x-icon name="photo" class="h-3.5 w-3.5" /></button>
                      <button type="button" @click.stop="removeFromAlbum(currentAlbum, p)" title="{{ __('gallery.remove') }}" class="flex h-7 w-7 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm hover:bg-red-500"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    {{-- Floating upload card --}}
    <div x-show="uploading || uploads.length" x-cloak x-transition
        class="fixed bottom-4 right-4 z-[860] w-72 rounded-xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-3 shadow-xl">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ __('gallery.upload') }}</span>
        <button type="button" @click="dismissUploads()" x-show="! uploading" class="text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
      </div>
      <template x-if="uploads.length">
        <div class="mt-2">
          <div class="flex justify-between text-[11px] font-medium text-gray-600 dark:text-gray-300"><span>{{ __('gallery.uploaded_label') }}</span><span class="tabular-nums" x-text="uploadDone() + ' / ' + uploads.length"></span></div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${uploads.length ? (uploadDone() / uploads.length * 100) : 0}%`"></div></div>
        </div>
      </template>
      <div class="mt-2 max-h-40 space-y-1.5 overflow-y-auto">
        <template x-for="(u, i) in uploads" :key="i">
          <div>
            <div class="flex items-center gap-2 text-[11px] text-gray-600 dark:text-gray-400"><span class="truncate" x-text="u.name"></span><span class="ml-auto tabular-nums" :class="u.state === 'error' ? 'text-red-500' : ''" :title="u.state === 'error' ? u.error : ''" x-text="u.state === 'error' ? '⚠' : (u.state === 'done' ? '✓' : (u.state === 'pending' ? '…' : u.progress + '%'))"></span></div>
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
      <button type="button" x-show="viewerHasGallery" @click.stop="viewerStep(-1)" class="absolute left-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/40 p-2 text-white/80 hover:bg-black/60 hover:text-white"><x-icon name="chevron-left" class="h-6 w-6" /></button>
      <button type="button" x-show="viewerHasGallery" @click.stop="viewerStep(1)" class="absolute right-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-black/40 p-2 text-white/80 hover:bg-black/60 hover:text-white"><x-icon name="chevron-right" class="h-6 w-6" /></button>
      <div class="flex flex-1 items-center justify-center p-4" x-ref="vstage" @click.self="closeViewer()">
        <template x-if="viewer.photo && viewer.photo.kind !== 'video'">
          <div class="relative" @click.stop>
            <img :src="viewerSrc(viewer.photo)" x-show="! viewer.motionOn" class="max-h-[92vh] max-w-full rounded-lg">
            <template x-if="viewer.motionOn">
              <video :src="motionUrl(viewer.photo)" autoplay muted playsinline @ended="stopMotion()" class="max-h-[92vh] max-w-full rounded-lg"></video>
            </template>
            <button type="button" x-show="viewer.photo?.hasMotion && ! viewer.motionOn" @click.stop="playMotion()"
                class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white backdrop-blur-sm transition hover:bg-black/70">
              <x-icon name="play" class="h-4 w-4" />Live
            </button>
          </div>
        </template>
        <template x-if="viewer.photo && viewer.photo.kind === 'video'"><video :src="rawUrl(viewer.photo)" controls autoplay playsinline class="max-h-[92vh] max-w-full rounded-lg" @click.stop></video></template>
      </div>
      {{-- Info panel --}}
      <aside x-show="viewer.photo" class="hidden w-80 shrink-0 overflow-y-auto border-l border-gray-200 bg-white p-6 text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100 md:block">
        <div class="flex items-start justify-between gap-2">
          <h3 class="min-w-0 flex-1 truncate text-base font-semibold" x-text="view === 'trash' ? '{{ __('gallery.trash') }}' : fmtDate(viewer.photo?.takenAt || viewer.photo?.created)"></h3>
          <div class="flex shrink-0 items-center gap-1" x-show="view !== 'trash'">
            <button type="button" @click="toggleFavorite(viewer.photo)" :title="viewer.photo?.favorite ? '{{ __('gallery.unfavorite') }}' : '{{ __('gallery.favorite') }}'"
                class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200" :class="viewer.photo?.favorite ? 'text-amber-500 dark:text-amber-400' : ''">
              <x-icon x-show="viewer.photo?.favorite" name="star-solid" class="h-4 w-4" />
              <x-icon x-show="! viewer.photo?.favorite" name="star" class="h-4 w-4" />
            </button>
            <a :href="rawUrl(viewer.photo) + '?download=1'" title="{{ __('gallery.share_download') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200"><x-icon name="arrow-down-tray" class="h-4 w-4" /></a>
          </div>
        </div>
        <div x-show="view !== 'trash'" class="mt-4">
          <label class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.caption') }}</label>
          <textarea rows="2" @change="setCaption(viewer.photo, $event.target.value)" :value="viewer.photo?.description || ''" placeholder="{{ __('gallery.caption_placeholder') }}"
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent"></textarea>
        </div>
        <dl class="mt-5 space-y-4 text-sm">
          <div x-show="viewer.photo?.takenAt">
            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.info_date') }}</dt>
            <dd class="mt-0.5" x-text="fmtDate(viewer.photo?.takenAt)"></dd>
          </div>
          <div x-show="viewer.photo?.camera">
            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.info_camera') }}</dt>
            <dd class="mt-0.5" x-text="viewer.photo?.camera"></dd>
          </div>
          <div x-show="viewer.photo?.width && viewer.photo?.height">
            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.info_dimensions') }}</dt>
            <dd class="mt-0.5" x-text="viewer.photo?.width + ' × ' + viewer.photo?.height"></dd>
          </div>
          <div x-show="placeText(viewer.photo?.place)">
            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.info_place') }}</dt>
            <dd class="mt-0.5" x-text="placeText(viewer.photo?.place)"></dd>
          </div>
          <div x-show="viewer.photo?.size">
            <dt class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.info_size') }}</dt>
            <dd class="mt-0.5" x-text="fmtBytes(viewer.photo?.size)"></dd>
          </div>
        </dl>
        {{-- Non-destructive edit: date-time / location --}}
        <div x-show="viewer.photo && view !== 'trash'" class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4">
          <h4 class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.edit_heading') }}</h4>
          <label class="mt-3 block text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.edit_datetime') }}
            <input type="datetime-local" :value="toLocalInput(viewer.photo?.takenAt)" @change="setTakenAt(viewer.photo, $event.target.value)"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
          </label>
          <button type="button" @click="openLocPicker(viewer.photo)" class="mt-3 inline-flex items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="map-pin" class="h-4 w-4" />{{ __('gallery.edit_location') }}</button>
        </div>
        <div x-ref="minimap" x-show="viewer.photo?.lat != null" class="mt-5 h-40 w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800"></div>
      </aside>
    </div>

    {{-- Public album share link (plaintext bytes; optional password gate) --}}
    <div x-show="share.open" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeShare()">
      <div class="absolute inset-0 bg-black/60" @click="closeShare()"></div>
      <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-5 shadow-xl">
        <div class="flex items-start justify-between gap-2">
          <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.share_album') }}</h3>
          <button type="button" @click="closeShare()" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="x-mark" class="h-5 w-5" /></button>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.share_intro') }}</p>

        <div x-show="share.link" x-cloak class="mt-4 rounded-lg border border-gray-200 dark:border-gray-800 p-3">
          <label class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('gallery.share_link_label') }}</label>
          <div class="mt-1 flex items-center gap-2">
            <input type="text" readonly :value="share.link" @focus="$event.target.select()" class="w-full rounded-md border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-xs text-gray-700 dark:text-gray-300">
            <button type="button" @click="copyShareLink()" title="{{ __('gallery.share_copy') }}" class="shrink-0 rounded-md bg-gray-100 dark:bg-gray-800 p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="clipboard" class="h-4 w-4" /></button>
          </div>
          <p class="mt-2 text-[11px] leading-relaxed text-gray-400 dark:text-gray-500">{{ __('gallery.share_active_hint') }}</p>
        </div>

        <div class="mt-4 space-y-3">
          <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" x-model="share.allowDownload" class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0">
            {{ __('gallery.share_allow_download') }}
          </label>
          <label class="block text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.share_password') }}
            <input type="password" x-model="share.password" autocomplete="new-password" :placeholder="share.album?.share?.needsPassword ? '{{ __('gallery.share_password_set') }}' : '{{ __('gallery.share_password_hint') }}'"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
          </label>
          <label class="block text-xs text-gray-500 dark:text-gray-400">{{ __('gallery.share_expiry') }}
            <input type="datetime-local" x-model="share.expiresAt"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
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
        <div class="relative z-[1100] mt-3">
          <form @submit.prevent="geoSearch()" class="flex gap-2">
            <input type="search" x-model="geoQuery" placeholder="{{ __('gallery.search_place') }}"
                class="w-full rounded-lg border border-gray-200 dark:border-gray-700 dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300">
            <button type="submit" :disabled="geoBusy || ! geoQuery.trim()" class="inline-flex shrink-0 items-center rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 disabled:opacity-40"><x-icon name="magnifying-glass" class="h-4 w-4" /></button>
          </form>
          <div x-show="geoResults.length" x-cloak class="absolute z-[1101] mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-1 shadow-lg">
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

    {{-- Bulk date/time picker --}}
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

    {{-- Add the selected photos to an album --}}
    <div x-show="albumPicker" x-cloak class="fixed inset-0 z-[965] flex items-center justify-center p-4" @keydown.escape.window="albumPicker = false">
      <div class="absolute inset-0 bg-black/60" @click="albumPicker = false"></div>
      <div class="relative w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.add_to_album') }}</h3>
        <p x-show="! albums.length" x-cloak class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.no_albums') }}</p>
        <div class="mt-3 max-h-72 space-y-1 overflow-y-auto">
          <template x-for="al in albumsSorted" :key="al.id">
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
  </div>
</x-layouts.app>
