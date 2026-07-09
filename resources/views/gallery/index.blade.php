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
     })">

    {{-- Whole-window drop zone --}}
    <div x-show="dragging && state === 'ready'" x-cloak @drop.prevent="drop($event)" @dragover.prevent
        class="fixed inset-0 z-[900] flex items-center justify-center bg-gray-900/60 p-8 backdrop-blur-sm">
      <div class="rounded-3xl border-4 border-dashed border-white/70 px-16 py-24 text-center text-lg font-medium text-white">{{ __('gallery.drop_hint') }}</div>
    </div>

    {{-- Zero-knowledge gate: the gallery can only be shown with the vault unlocked. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <x-page-heading :title="__('gallery.title')"
        :subtitle="null">
      <x-slot:actions>
        <div class="flex items-center gap-1.5">
          <span x-show="state === 'ready'" x-cloak class="mr-1 text-xs tabular-nums text-gray-400 dark:text-gray-500"
                x-text="photoCount() + ' · ' + fmtBytes(usage.used)"></span>
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

    {{-- Locked / setup --}}
    <template x-if="state === 'locked'">
      <div class="mx-auto mt-16 flex max-w-md flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="lock-closed" class="h-7 w-7 text-gray-400" /></div>
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400"
           x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
        <button type="button" @click="$dispatch('vault-panel')" class="mt-5 rounded-lg bg-gray-900 dark:bg-gray-100 px-5 py-2.5 text-sm font-medium text-white dark:text-gray-900">
          <span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span>
        </button>
      </div>
    </template>

    <template x-if="state === 'error'">
      <p class="mt-8 text-center text-sm text-red-500">{{ __('gallery.load_failed') }}</p>
    </template>

    <div x-show="state === 'ready'" x-cloak class="mt-6">
      {{-- Empty --}}
      <template x-if="! libraryPhotos.length && ! progress.active && ! uploading">
        <button type="button" @click="$refs.picker.click()"
            class="mx-auto mt-10 flex w-full max-w-lg flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-700 p-16 text-center hover:border-gray-400 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-900/50">
          <x-icon name="photo" class="h-12 w-12 text-gray-300 dark:text-gray-600" />
          <p class="mt-4 text-sm font-medium text-gray-600 dark:text-gray-300">{{ __('gallery.empty') }}</p>
          <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('gallery.drop_hint') }}</p>
        </button>
      </template>

      {{-- Grid --}}
      <div class="grid grid-cols-2 gap-1 sm:grid-cols-3 sm:gap-1.5 md:grid-cols-4 lg:grid-cols-6">
        <template x-for="p in libraryPhotos" :key="p.id">
          <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
               x-data="{ src: '' }" x-effect="if (p.thumbRef && ! src) thumbFor(p).then((u) => { src = u; })">
            <button type="button" @click="openViewer(p)" class="block h-full w-full">
              <img x-show="src" :src="src" loading="lazy" x-transition.opacity.duration.400ms
                   class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
              {{-- Placeholder while the thumbnail is still processing/decrypting --}}
              <div x-show="!src" class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900">
                <svg x-show="!p.thumbRef" class="h-5 w-5 animate-spin text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
              </div>
              {{-- Media badges --}}
              <template x-if="p.media_type === 'video'">
                <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                  <span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span>
                </span>
              </template>
            </button>
            <button type="button" @click.stop="remove(p)" title="{{ __('gallery.delete') }}"
                class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/40 text-white opacity-0 backdrop-blur-sm transition hover:bg-red-500 group-hover:opacity-100">
              <x-icon name="trash" class="h-4 w-4" />
            </button>
          </div>
        </template>
      </div>
    </div>

    {{-- Floating upload / processing card (bottom-right) --}}
    <div x-show="state === 'ready' && (uploading || progress.active || uploads.length)" x-cloak x-transition
        class="fixed bottom-4 right-4 z-[860] w-72 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 shadow-xl">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-gray-700 dark:text-gray-200"
              x-text="progress.active ? @js(__('gallery.processing')) : @js(__('gallery.upload'))"></span>
        <button type="button" @click="dismissUploads()" x-show="! uploading && ! progress.active" class="text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
      </div>
      {{-- Processing backlog progress --}}
      <template x-if="progress.active">
        <div class="mt-2">
          <div class="flex justify-between text-[11px] text-gray-500 dark:text-gray-400"><span>{{ __('gallery.processing') }}</span><span x-text="progress.done + ' / ' + progress.total"></span></div>
          <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${progress.total ? (progress.done / progress.total * 100) : 0}%`"></div>
          </div>
        </div>
      </template>
      {{-- Per-file upload rows --}}
      <div class="mt-2 max-h-40 space-y-1.5 overflow-y-auto">
        <template x-for="(u, i) in uploads" :key="i">
          <div>
            <div class="flex items-center gap-2 text-[11px] text-gray-600 dark:text-gray-400">
              <span class="truncate" x-text="u.name"></span>
              <span class="ml-auto tabular-nums" x-text="u.state === 'error' ? '⚠' : (u.state === 'done' ? '✓' : (u.state === 'pending' ? '…' : u.progress + '%'))"></span>
            </div>
            <div x-show="u.state === 'uploading'" class="mt-0.5 h-0.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
              <div class="h-full bg-gray-500 transition-all" :style="`width: ${u.progress}%`"></div>
            </div>
          </div>
        </template>
      </div>
    </div>

    {{-- Viewer --}}
    <div x-show="viewer.open" x-cloak @keydown.escape.window="closeViewer()"
        class="fixed inset-0 z-[950] flex items-center justify-center bg-black/90 p-4" @click.self="closeViewer()">
      <button type="button" @click="closeViewer()" class="absolute right-4 top-4 z-10 text-white/70 hover:text-white"><x-icon name="x-mark" class="h-7 w-7" /></button>
      <template x-if="viewer.kind === 'loading'">
        <svg class="h-8 w-8 animate-spin text-white/60" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
      </template>
      <template x-if="viewer.kind === 'image'"><img :src="viewer.src" class="max-h-[92vh] max-w-full rounded-lg"></template>
      <template x-if="viewer.kind === 'video'"><video :src="viewer.src" controls autoplay class="max-h-[92vh] max-w-full rounded-lg"></video></template>
    </div>
  </div>
</x-layouts.app>
