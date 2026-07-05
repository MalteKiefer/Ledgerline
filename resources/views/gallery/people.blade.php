<x-layouts.app :title="__('gallery.people_heading')">
    @php $cfg = ['dataUrl' => route('gallery.people.data'), 'showBase' => url('gallery/people')]; @endphp
    <div class="flex flex-col gap-4 md:flex-row">
    @include('gallery._sidebar')
    <div class="min-w-0 flex-1" x-data="peoplePage(@js($cfg))" x-init="init()">
        <x-page-heading :title="__('gallery.people_heading')" />

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
    </div>
</x-layouts.app>
