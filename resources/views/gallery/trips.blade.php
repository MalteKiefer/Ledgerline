<x-layouts.app :title="__('gallery.trips')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('gallery.trips') }}</h1>
        <a href="{{ route('gallery.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.timeline') }}</a>
    </div>

    <div class="mt-6 space-y-8" x-data="{ lightbox: null }">
        @forelse ($trips as $trip)
            <section>
                <div class="mb-3 flex items-baseline justify-between gap-3">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ $trip['label'] ?? __('gallery.trips') }}
                        <span class="ml-2 text-sm font-normal text-gray-500">
                            {{ $trip['from']->isoFormat('LL') }}@if ($trip['from']->toDateString() !== $trip['to']->toDateString()) – {{ $trip['to']->isoFormat('LL') }}@endif
                        </span>
                    </h2>
                    <span class="text-xs text-gray-400">{{ __('gallery.photos_count', ['count' => $trip['photos']->count()]) }}</span>
                </div>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                    @foreach ($trip['photos'] as $photo)
                        <div class="aspect-square overflow-hidden rounded-lg bg-gray-100">
                            <img src="{{ route('gallery.image', ['photo' => $photo, 'size' => 'thumb']) }}" alt="{{ $photo->name }}" loading="lazy"
                                @click="lightbox = { src: '{{ route('gallery.image', ['photo' => $photo, 'size' => 'medium']) }}', download: '{{ route('gallery.image', ['photo' => $photo, 'size' => 'original']) }}', size: @js(\App\Support\Bytes::format($photo->size)) }"
                                class="h-full w-full cursor-pointer object-cover transition hover:opacity-90">
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm">{{ __('gallery.trips_empty') }}</p>
        @endforelse

        <template x-teleport="body">
            <div x-show="lightbox" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @keydown.escape.window="lightbox = null" @click="lightbox = null">
                <img :src="lightbox?.src" class="max-h-[85vh] max-w-full rounded shadow-2xl" @click.stop>
                <span x-show="lightbox" x-text="lightbox?.size" class="absolute bottom-6 left-6 rounded-md bg-black/50 px-2 py-1 text-xs font-medium text-white"></span>
                <a x-show="lightbox" :href="lightbox?.download" @click.stop class="absolute bottom-6 rounded-md bg-white/90 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-white">{{ __('gallery.download') }}</a>
                <button type="button" @click="lightbox = null" class="absolute right-6 top-6 text-2xl text-white/80 hover:text-white" aria-label="{{ __('gallery.close') }}">✕</button>
            </div>
        </template>
    </div>
</x-layouts.app>
