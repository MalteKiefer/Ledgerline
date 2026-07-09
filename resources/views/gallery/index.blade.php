<x-layouts.app :title="__('gallery.title')">
  <div x-data="vaultGallery({
        uploadUrl: '{{ url('/gallery/upload') }}',
        processUrl: '{{ url('/gallery/process') }}',
        rawBase: '{{ url('/gallery/raw') }}',
        blobBase: '{{ url('/gallery/blob') }}',
        usageUrl: '{{ url('/gallery/usage') }}',
        reconcileUrl: '{{ url('/gallery/blobs/reconcile') }}',
        token: '{{ csrf_token() }}',
     }, {
        loadFailed: @js(__('gallery.load_failed')),
        deleteConfirm: @js(__('gallery.delete_confirm')),
        purgeConfirm: @js(__('gallery.purge_confirm')),
        emptyTrashConfirm: @js(__('gallery.empty_trash_confirm')),
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
        <nav class="sticky top-6 space-y-1 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2">
          <button type="button" @click="view = 'library'; clearSelection()"
              :class="view === 'library' ? 'bg-gray-100 dark:bg-gray-800 font-medium text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'"
              class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm">
            <x-icon name="photo" class="h-4 w-4" /><span class="flex-1 text-left">{{ __('gallery.library') }}</span><span class="text-xs tabular-nums text-gray-400" x-text="photoCount()"></span>
          </button>
          <button type="button" @click="view = 'trash'; clearSelection()"
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
              <button type="button" @click="bulkTrash()" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 dark:bg-gray-100 px-3 py-1.5 text-sm font-medium text-white dark:text-gray-900"><x-icon name="trash" class="h-4 w-4" />{{ __('gallery.delete') }}</button>
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
        <div class="mb-4 flex gap-2 md:hidden">
          <button type="button" @click="view = 'library'; clearSelection()" :class="view === 'library' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.library') }}</button>
          <button type="button" @click="view = 'trash'; clearSelection()" :class="view === 'trash' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600'" class="rounded-lg px-3 py-1.5 text-sm">{{ __('gallery.trash') }} <span x-show="trashCount()" x-text="'('+trashCount()+')'"></span></button>
        </div>

        {{-- LIBRARY --}}
        <div x-show="view === 'library'">
          <template x-if="! libraryPhotos.length && ! progress.active && ! uploading">
            <button type="button" @click="$refs.picker.click()"
                class="mx-auto mt-6 flex w-full max-w-lg flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-700 p-16 text-center hover:border-gray-400 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-900/50">
              <x-icon name="photo" class="h-12 w-12 text-gray-300 dark:text-gray-600" />
              <p class="mt-4 text-sm font-medium text-gray-600 dark:text-gray-300">{{ __('gallery.empty') }}</p>
              <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('gallery.drop_hint') }}</p>
            </button>
          </template>

          <template x-for="group in groupedPhotos" :key="group.day">
            <section class="mb-6">
              <h2 class="mb-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300" x-text="group.label"></h2>
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
            <div class="flex items-center gap-2 text-[11px] text-gray-600 dark:text-gray-400"><span class="truncate" x-text="u.name"></span><span class="ml-auto tabular-nums" x-text="u.state === 'error' ? '⚠' : (u.state === 'done' ? '✓' : (u.state === 'pending' ? '…' : u.progress + '%'))"></span></div>
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
        <template x-if="viewer.kind === 'image'"><img :src="viewer.src" class="max-h-[92vh] max-w-full rounded-lg"></template>
        <template x-if="viewer.kind === 'video'"><video :src="viewer.src" controls autoplay class="max-h-[92vh] max-w-full rounded-lg"></video></template>
      </div>
      {{-- Info panel --}}
      <aside x-show="viewer.photo" class="hidden w-80 shrink-0 overflow-y-auto border-l border-white/10 bg-gray-950/80 p-6 text-gray-200 backdrop-blur md:block">
        <h3 class="truncate text-base font-semibold text-white" x-text="viewer.photo?.name"></h3>
        <dl class="mt-5 space-y-4 text-sm">
          <div x-show="viewer.meta?.exif?.taken_at || viewer.photo?.taken_at">
            <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('gallery.info_date') }}</dt>
            <dd class="mt-0.5" x-text="fmtDate(viewer.meta?.exif?.taken_at || viewer.photo?.taken_at)"></dd>
          </div>
          <div x-show="viewer.meta?.exif?.camera">
            <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('gallery.info_camera') }}</dt>
            <dd class="mt-0.5" x-text="viewer.meta?.exif?.camera"></dd>
          </div>
          <div x-show="viewer.photo?.width && viewer.photo?.height">
            <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('gallery.info_dimensions') }}</dt>
            <dd class="mt-0.5" x-text="viewer.photo?.width + ' × ' + viewer.photo?.height"></dd>
          </div>
          <div x-show="placeText(viewer.meta?.place)">
            <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('gallery.info_place') }}</dt>
            <dd class="mt-0.5" x-text="placeText(viewer.meta?.place)"></dd>
          </div>
          <div x-show="viewer.photo?.size">
            <dt class="text-xs uppercase tracking-wide text-gray-500">{{ __('gallery.info_size') }}</dt>
            <dd class="mt-0.5" x-text="fmtBytes(viewer.photo?.size)"></dd>
          </div>
        </dl>
      </aside>
    </div>
  </div>
</x-layouts.app>
