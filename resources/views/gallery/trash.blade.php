<x-layouts.app :title="__('gallery.trash_title')">
  <div x-data="{ selected: [], all: {{ $photos->pluck('id')->toJson() }},
                 toggleAll(e){ this.selected = e.target.checked ? [...this.all] : [] } }">
    <p class="text-sm text-gray-500"><a href="{{ route('gallery.index') }}" class="hover:underline">{{ __('gallery.back') }}</a></p>
    <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('gallery.trash_heading') }}</h1>
        @if ($photos->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">
                <label class="flex items-center gap-1.5 text-sm text-gray-600">
                    <input type="checkbox" @change="toggleAll($event)" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500"> {{ __('gallery.select_all') }}
                </label>

                {{-- Restore selected --}}
                <form method="POST" action="{{ route('gallery.restore') }}" x-show="selected.length">
                    @csrf
                    <template x-for="id in selected" :key="id"><input type="hidden" name="photo_ids[]" :value="id"></template>
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.restore_selected') }}</button>
                </form>
                {{-- Delete selected --}}
                <form method="POST" action="{{ route('gallery.force-destroy') }}" x-show="selected.length" onsubmit="return confirm('{{ __('gallery.delete_selected_confirm') }}');">
                    @csrf @method('DELETE')
                    <template x-for="id in selected" :key="id"><input type="hidden" name="photo_ids[]" :value="id"></template>
                    <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('gallery.delete_selected') }}</button>
                </form>

                {{-- Restore all --}}
                <form method="POST" action="{{ route('gallery.restore') }}">
                    @csrf <input type="hidden" name="all" value="1">
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.restore_all') }}</button>
                </form>
                {{-- Empty trash --}}
                <form method="POST" action="{{ route('gallery.force-destroy') }}" onsubmit="return confirm('{{ __('gallery.empty_trash_confirm') }}');">
                    @csrf @method('DELETE') <input type="hidden" name="all" value="1">
                    <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">{{ __('gallery.empty_trash') }}</button>
                </form>
            </div>
        @endif
    </div>

    @if ($photos->isEmpty())
        <p class="mt-6 rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm">{{ __('gallery.trash_empty') }}</p>
    @else
        <div class="mt-6 grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
            @foreach ($photos as $photo)
                <div class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100">
                    <img src="{{ route('gallery.image', ['photo' => $photo, 'size' => 'thumb']) }}" alt="{{ $photo->name }}" loading="lazy" class="h-full w-full object-cover">
                    <input type="checkbox" value="{{ $photo->id }}" x-model.number="selected"
                        class="absolute left-1.5 top-1.5 rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-6">{{ $photos->links() }}</div>
  </div>
</x-layouts.app>
