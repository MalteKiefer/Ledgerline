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
        class="fixed inset-0 z-[900] flex items-center justify-center bg-gray-900/50 p-8">
      <div class="rounded-2xl border-4 border-dashed border-white/80 px-16 py-24 text-center text-lg font-medium text-white">{{ __('gallery.drop_hint') }}</div>
    </div>

    {{-- Working spinner badge --}}
    <div x-show="busy > 0" x-cloak x-transition
        class="fixed right-4 top-20 z-[850] flex items-center gap-2 rounded-full bg-gray-900/90 px-3 py-1.5 text-xs font-medium text-white shadow-lg">
      <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
      <span>{{ __('gallery.working') }}</span>
    </div>

    {{-- Zero-knowledge gate: the gallery can only be shown with the vault unlocked. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <x-page-heading :title="__('gallery.title')">
      <x-slot:actions>
        <div class="flex items-center gap-2">
          <span x-show="state === 'ready'" x-cloak class="text-xs text-gray-500 dark:text-gray-400"
                x-text="photoCount() + ' · ' + fmtBytes(usage.used)"></span>
          <button type="button" @click="$store.vault.unlocked ? $store.vault.lock() : $dispatch('vault-panel')"
              :title="$store.vault.unlocked ? @js(__('vault.unlocked')) : @js(__('vault.unlock'))"
              class="min-h-11 min-w-11 inline-flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">
            <span x-show="$store.vault.unlocked"><x-icon name="lock-open" class="h-5 w-5" /></span>
            <span x-show="! $store.vault.unlocked"><x-icon name="lock-closed" class="h-5 w-5" /></span>
          </button>
          <x-button x-show="state === 'ready'" x-cloak variant="primary" @click="$refs.picker.click()">{{ __('gallery.upload') }}</x-button>
          <input x-ref="picker" type="file" accept="image/*,video/*" multiple class="hidden" @change="upload($event.target.files); $event.target.value = ''">
        </div>
      </x-slot:actions>
    </x-page-heading>

    {{-- Locked --}}
    <template x-if="state === 'locked'">
      <div class="mt-10 flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
        <x-icon name="lock-closed" class="h-10 w-10 text-gray-400" />
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400"
           x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
        <button type="button" @click="$dispatch('vault-panel')" class="mt-4 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white">
          <span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span>
        </button>
      </div>
    </template>

    <template x-if="state === 'error'">
      <p class="mt-8 text-center text-sm text-red-500">{{ __('gallery.load_failed') }}</p>
    </template>

    <div x-show="state === 'ready'" x-cloak>
      {{-- Backlog progress --}}
      <div x-show="progress.active" x-cloak class="mt-4 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3">
        <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
          <span>{{ __('gallery.processing') }}</span>
          <span x-text="progress.done + ' / ' + progress.total"></span>
        </div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
          <div class="h-full bg-gray-800 dark:bg-gray-200 transition-all" :style="`width: ${progress.total ? (progress.done / progress.total * 100) : 0}%`"></div>
        </div>
      </div>

      {{-- Upload tray --}}
      <div x-show="uploads.length" x-cloak class="mt-4 space-y-1">
        <template x-for="(u, i) in uploads" :key="i">
          <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
            <span class="truncate" x-text="u.name"></span>
            <span class="ml-auto" x-text="u.state === 'error' ? '⚠' : (u.state === 'done' ? '✓' : u.progress + '%')"></span>
          </div>
        </template>
        <button type="button" @click="dismissUploads()" x-show="! uploading" class="text-xs text-gray-400 hover:text-gray-600">{{ __('gallery.dismiss') }}</button>
      </div>

      {{-- Empty --}}
      <template x-if="! libraryPhotos.length && ! progress.active">
        <p class="mt-16 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.empty') }}</p>
      </template>

      {{-- Grid: thumbnails decrypted client-side --}}
      <div class="mt-4 grid gap-2 grid-cols-3 sm:grid-cols-4 md:grid-cols-6">
        <template x-for="p in libraryPhotos" :key="p.id">
          <div class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800"
               x-data="{ src: '' }" x-init="$nextTick(() => thumbFor(p).then((u) => { src = u; }))">
            <button type="button" @click="openViewer(p)" class="block h-full w-full">
              <img x-show="src" :src="src" loading="lazy" class="h-full w-full object-cover transition-opacity duration-300">
              <div x-show="!src" class="flex h-full w-full animate-pulse items-center justify-center bg-gray-200 dark:bg-gray-700"></div>
              <template x-if="p.media_type === 'video'">
                <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                  <span class="flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white"><x-icon name="play" class="h-5 w-5" /></span>
                </span>
              </template>
            </button>
            <button type="button" @click.stop="remove(p)" title="{{ __('gallery.delete') }}"
                class="absolute right-1.5 top-1.5 hidden rounded bg-black/50 p-1 text-white group-hover:block">
              <x-icon name="trash" class="h-4 w-4" />
            </button>
          </div>
        </template>
      </div>
    </div>

    {{-- Viewer --}}
    <div x-show="viewer.open" x-cloak @keydown.escape.window="closeViewer()"
        class="fixed inset-0 z-[950] flex items-center justify-center bg-black/90 p-4" @click.self="closeViewer()">
      <button type="button" @click="closeViewer()" class="absolute right-4 top-4 text-white/80 hover:text-white"><x-icon name="x-mark" class="h-7 w-7" /></button>
      <template x-if="viewer.kind === 'loading'"><div class="text-white/70 text-sm">…</div></template>
      <template x-if="viewer.kind === 'image'"><img :src="viewer.src" class="max-h-full max-w-full rounded"></template>
      <template x-if="viewer.kind === 'video'"><video :src="viewer.src" controls autoplay class="max-h-full max-w-full rounded"></video></template>
    </div>
  </div>
</x-layouts.app>
