<x-layouts.app :title="__('gallery.map')">
    <div class="flex flex-col gap-4 md:flex-row">
    @include('gallery._sidebar')
    <div class="min-w-0 flex-1">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.map') }}</h1>
    </div>

    <div x-data="photoMap('{{ route('gallery.points') }}', {{ (int) $mapZoom }})" class="mt-4">
        <div x-ref="map" class="h-[70vh] w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800 shadow-sm"></div>

        <template x-teleport="body">
            <div x-show="lightbox" x-cloak class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/80 p-4" @keydown.escape.window="lightbox = null" @click="lightbox = null">
                <img :src="lightbox?.src" class="max-h-[85vh] max-w-full rounded shadow-2xl" @click.stop>
                <a x-show="lightbox" :href="lightbox?.download" @click.stop class="absolute bottom-6 rounded-md bg-white/90 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-white">{{ __('gallery.download') }}</a>
                <button type="button" @click="lightbox = null" class="absolute right-6 top-6 text-white/80 hover:text-white" aria-label="{{ __('gallery.close') }}"><x-icon name="x-mark" class="h-6 w-6" /></button>
            </div>
        </template>
    </div>
    </div>
    </div>
</x-layouts.app>
