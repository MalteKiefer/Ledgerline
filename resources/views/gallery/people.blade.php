<x-layouts.app :title="__('gallery.people_heading')">
    @php $cfg = ['dataUrl' => route('gallery.people.data'), 'showBase' => url('gallery/people')]; @endphp
    <div x-data="peoplePage(@js($cfg))" x-init="init()">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ __('gallery.people_heading') }}</h1>
            </div>
            <a href="{{ route('gallery.index') }}" class="shrink-0 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('gallery.back') }}</a>
        </div>

        <template x-if="!loading && people.length === 0">
            <div class="mt-8 rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                {{ __('gallery.people_empty') }}
            </div>
        </template>

        <div class="mt-6 grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-6">
            <template x-for="p in people" :key="p.id">
                <a :href="cfg.showBase + '/' + p.id" class="group block text-center">
                    <div class="aspect-square overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                        <template x-if="p.cover">
                            <img :src="p.cover" alt="" class="h-full w-full object-cover" loading="lazy">
                        </template>
                    </div>
                    <p class="mt-2 truncate text-sm font-medium text-gray-800" x-text="p.name || '{{ __('gallery.person_unnamed') }}'"></p>
                    <p class="text-xs text-gray-400" x-text="p.count"></p>
                </a>
            </template>
        </div>
    </div>
</x-layouts.app>
