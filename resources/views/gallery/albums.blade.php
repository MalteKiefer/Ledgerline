<x-layouts.app :title="__('gallery.albums_heading')">
    @php $cfg = [
        'dataUrl' => route('gallery.albums.data'),
        'storeUrl' => route('gallery.albums.store'),
        'showBase' => url('gallery/albums'),
        'token' => csrf_token(),
        'sharesDataUrl' => route('shares.data'),
        'sharesUrl' => route('shares.store'),
        'sharesBase' => url('shares'),
        'shareError' => __('shares.error'),
        'shareLink' => route('gallery.albums'),
        'mailConfigured' => \App\Services\Notifications\ChannelNotifier::mailConfigured(),
        'linkCopied' => __('shares.link_copied'),
        'mailSent' => __('shares.mail_sent'),
        'mailUnavailable' => __('shares.mail_unavailable'),
        'publicStoreUrl' => route('public-share.store'),
        'publicBase' => url('shares/public'),
    ]; @endphp
    <div class="flex flex-col gap-4 md:flex-row" x-data="albumsPage(@js($cfg))" x-init="init()">
        @include('gallery._sidebar')

        <div class="min-w-0 flex-1">
            <x-page-heading :title="__('gallery.albums_heading')">
                <x-slot:actions>
                    <x-button variant="primary" icon="plus" @click="openNew()">{{ __('gallery.new_album') }}</x-button>
                </x-slot:actions>
            </x-page-heading>

            <template x-if="!loading && !albums.length">
                <div class="mt-8 rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">{{ __('gallery.no_albums') }}</div>
            </template>

            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                <template x-for="a in albums" :key="a.id">
                    <div class="group relative">
                        <a :href="cfg.showBase + '/' + a.id" class="block">
                            <div class="aspect-square overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-200">
                                <template x-if="a.cover"><img :src="a.cover" alt="" class="h-full w-full object-cover" loading="lazy"></template>
                            </div>
                            <p class="mt-2 truncate text-sm font-medium text-gray-900" x-text="a.name"></p>
                            <p class="text-xs text-gray-400" x-text="'{{ __('gallery.album_photos_count', ['count' => '__C__']) }}'.replace('__C__', a.count)"></p>
                        </a>
                        <button type="button" x-show="a.owned" @click="openShare('albums', a.id, a.name)"
                            class="absolute right-1 top-1 hidden rounded-md bg-white/90 p-1.5 text-gray-600 shadow ring-1 ring-gray-200 hover:text-gray-900 group-hover:block"
                            title="{{ __('shares.share') }}"><x-icon name="share" class="h-4 w-4" /></button>
                    </div>
                </template>
            </div>
        </div>

        @include('partials.share-modal')

        {{-- New album modal --}}
        <div x-show="nameModal.open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4" @keydown.escape.window="nameModal.open=false">
            <div class="absolute inset-0 bg-gray-900/40" @click="nameModal.open=false"></div>
            <div class="relative my-16 w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('gallery.new_album') }}</h3>
                <form @submit.prevent="createAlbum()">
                    <input x-ref="albumName" x-model="nameModal.value" placeholder="{{ __('gallery.album_name') }}" class="mt-3 w-full rounded-md border-gray-300 text-sm">
                    <div class="mt-4 flex justify-end gap-2">
                        <x-button variant="secondary" @click="nameModal.open=false">{{ __('common.cancel') }}</x-button>
                        <x-button variant="primary" type="submit">{{ __('gallery.new_album') }}</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
