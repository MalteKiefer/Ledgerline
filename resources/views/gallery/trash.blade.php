<x-layouts.app :title="__('gallery.trash_title')">
  <div class="flex flex-col gap-4 md:flex-row">
  @include('gallery._sidebar')
  <div class="min-w-0 flex-1" x-data="{ selected: [], all: {{ $photos->pluck('id')->toJson() }},
                 toggleAll(e){ this.selected = e.target.checked ? [...this.all] : [] } }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.trash_heading') }}</h1>
        @if ($photos->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">
                <label class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" @change="toggleAll($event)" class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500"> {{ __('gallery.select_all') }}
                </label>

                {{-- Restore selected --}}
                <form method="POST" action="{{ route('gallery.restore') }}" x-show="selected.length">
                    @csrf
                    <template x-for="id in selected" :key="id"><input type="hidden" name="photo_ids[]" :value="id"></template>
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">{{ __('gallery.restore_selected') }}</button>
                </form>
                {{-- Delete selected --}}
                <span x-show="selected.length" class="inline">
                    <x-confirm-action :action="route('gallery.force-destroy')" method="DELETE"
                        :trigger="__('gallery.delete_selected')"
                        trigger-class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 dark:bg-gray-900 dark:text-red-300"
                        :message="__('gallery.delete_selected_confirm')">
                        <template x-for="id in selected" :key="id"><input type="hidden" name="photo_ids[]" :value="id"></template>
                    </x-confirm-action>
                </span>

                {{-- Restore all --}}
                <form method="POST" action="{{ route('gallery.restore') }}">
                    @csrf <input type="hidden" name="all" value="1">
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">{{ __('gallery.restore_all') }}</button>
                </form>
                {{-- Empty trash --}}
                <x-confirm-action :action="route('gallery.force-destroy')" method="DELETE"
                    :trigger="__('gallery.empty_trash')"
                    trigger-class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                    :message="__('gallery.empty_trash_confirm')">
                    <input type="hidden" name="all" value="1">
                </x-confirm-action>
            </div>
        @endif
    </div>

    @if ($photos->isEmpty())
        <p class="mt-6 rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">{{ __('gallery.trash_empty') }}</p>
    @else
        <div class="mt-6 grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
            @foreach ($photos as $photo)
                <div class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                    <img src="{{ route('gallery.image', ['photo' => $photo, 'size' => 'thumb']) }}" alt="{{ $photo->name }}" loading="lazy" class="h-full w-full object-cover">
                    <input type="checkbox" value="{{ $photo->id }}" x-model.number="selected"
                        class="absolute left-1.5 top-1.5 rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-6">{{ $photos->links() }}</div>
  </div>
  </div>
</x-layouts.app>
