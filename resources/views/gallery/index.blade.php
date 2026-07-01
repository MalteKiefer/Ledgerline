<x-layouts.app :title="__('gallery.title')">
  <div x-data="gallery('{{ route('gallery.store') }}', '{{ csrf_token() }}')"
    @if ($photos->getCollection()->contains(fn ($p) => ! $p->isReady()))
        x-init="setTimeout(() => window.location.reload(), 5000)"
    @endif>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('gallery.heading') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ __('gallery.subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('gallery.trips') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.trips') }}</a>
            <a href="{{ route('gallery.map') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.map') }}</a>
            <a href="{{ route('gallery.trash') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.trash') }}</a>
            <label class="cursor-pointer rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                {{ __('gallery.upload') }}
                <input type="file" accept="image/*" multiple class="hidden" @change="pick($event)">
            </label>
        </div>
    </div>

    {{-- Drop zone --}}
    <div @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
        :class="dragging ? 'border-gray-800 bg-gray-50' : 'border-gray-300'"
        class="mt-4 rounded-lg border-2 border-dashed px-6 py-8 text-center text-sm text-gray-500">
        {{ __('gallery.drop_hint') }}
    </div>

    {{-- Upload progress --}}
    <div x-show="queue.length" x-cloak class="mt-4 space-y-2 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <template x-for="(item, i) in queue" :key="i">
            <div>
                <div class="flex justify-between text-xs text-gray-600">
                    <span class="truncate" x-text="item.name"></span>
                    <span x-show="item.error" class="text-red-600">✕</span>
                    <span x-show="! item.error" x-text="item.progress + '%'"></span>
                </div>
                <div class="mt-1 h-1.5 w-full rounded bg-gray-100">
                    <div class="h-1.5 rounded" :class="item.error ? 'bg-red-500' : (item.done ? 'bg-green-500' : 'bg-gray-800')" :style="`width: ${item.progress}%`"></div>
                </div>
            </div>
        </template>
    </div>

    {{-- Bulk bar --}}
    <div x-show="selected.length" x-cloak class="mt-4 flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm" x-data="{ deleteOpen: false }">
        <span class="text-sm font-medium text-gray-700"><span x-text="selected.length"></span> {{ __('gallery.selected', ['count' => '']) }}</span>
        <button type="button" @click="deleteOpen = true" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('gallery.delete') }}</button>

        <template x-teleport="body">
            <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteOpen = false">
                <div class="absolute inset-0 bg-gray-900/40" @click="deleteOpen = false"></div>
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('gallery.delete_confirm') }}</p>
                    <form method="POST" action="{{ route('gallery.destroy') }}" class="mt-5 flex justify-end gap-3">
                        @csrf @method('DELETE')
                        <template x-for="id in selected" :key="id"><input type="hidden" name="photo_ids[]" :value="id"></template>
                        <button type="button" @click="deleteOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                        <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('common.delete') }}</button>
                    </form>
                </div>
            </div>
        </template>
    </div>

    {{-- Timeline --}}
    <div class="mt-6 space-y-8" x-data="{ lightbox: null }">
        @forelse ($grouped as $day => $dayPhotos)
            <section>
                <h2 class="mb-3 text-sm font-semibold text-gray-700">{{ \Illuminate\Support\Carbon::parse($day)->isoFormat('LL') }}</h2>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                    @foreach ($dayPhotos as $photo)
                        <div data-photo-id="{{ $photo->id }}" class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100">
                            @if ($photo->isReady())
                                <img src="{{ route('gallery.image', ['photo' => $photo, 'size' => 'thumb']) }}" alt="{{ $photo->name }}" loading="lazy"
                                    @click="lightbox = { src: '{{ route('gallery.image', ['photo' => $photo, 'size' => 'medium']) }}', download: '{{ route('gallery.image', ['photo' => $photo, 'size' => 'original']) }}', name: @js($photo->name) }"
                                    class="h-full w-full cursor-pointer object-cover transition group-hover:opacity-90">
                                <input type="checkbox" value="{{ $photo->id }}" x-model.number="selected"
                                    class="absolute left-1.5 top-1.5 rounded border-gray-300 text-gray-800 opacity-0 focus:ring-gray-500 group-hover:opacity-100"
                                    :class="selected.includes({{ $photo->id }}) ? '!opacity-100' : ''">
                            @else
                                <div class="flex h-full w-full animate-pulse items-center justify-center bg-gray-200 text-xs text-gray-400">{{ __('gallery.processing') }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm">{{ __('gallery.empty') }}</p>
        @endforelse

        {{-- Lightbox --}}
        <template x-teleport="body">
            <div x-show="lightbox" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @keydown.escape.window="lightbox = null" @click="lightbox = null">
                <img :src="lightbox?.src" :alt="lightbox?.name" class="max-h-[85vh] max-w-full rounded shadow-2xl" @click.stop>
                <a x-show="lightbox" :href="lightbox?.download" @click.stop class="absolute bottom-6 rounded-md bg-white/90 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-white">{{ __('gallery.download') }}</a>
                <button type="button" @click="lightbox = null" class="absolute right-6 top-6 text-2xl text-white/80 hover:text-white" aria-label="{{ __('gallery.close') }}">✕</button>
            </div>
        </template>
    </div>

    <div class="mt-6">{{ $photos->links() }}</div>
  </div>
</x-layouts.app>
