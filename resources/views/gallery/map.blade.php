<x-layouts.app :title="__('gallery.map')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('gallery.map') }}</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('gallery.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.timeline') }}</a>
        </div>
    </div>

    <div x-data="photoMap('{{ route('gallery.points') }}')" class="mt-4">
        <div x-ref="map" class="h-[70vh] w-full overflow-hidden rounded-lg border border-gray-200 shadow-sm"></div>

        <template x-teleport="body">
            <div x-show="lightbox" x-cloak class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/80 p-4" @keydown.escape.window="lightbox = null" @click="lightbox = null">
                <img :src="lightbox?.src" class="max-h-[85vh] max-w-full rounded shadow-2xl" @click.stop>
                <a x-show="lightbox" :href="lightbox?.download" @click.stop class="absolute bottom-6 rounded-md bg-white/90 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-white">{{ __('gallery.download') }}</a>
                <button type="button" @click="lightbox = null" class="absolute right-6 top-6 text-2xl text-white/80 hover:text-white" aria-label="{{ __('gallery.close') }}">✕</button>
            </div>
        </template>
    </div>
</x-layouts.app>
