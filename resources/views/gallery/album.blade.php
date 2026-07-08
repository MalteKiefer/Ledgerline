<x-layouts.app :title="$album->name">
    @php $cfg = [
        'dataUrl' => route('gallery.albums.show.data', ['album' => $album]),
        'albumUrl' => route('gallery.albums.update', ['album' => $album]),
        'albumsUrl' => route('gallery.albums'),
        'photosUrl' => route('gallery.albums.photos.add', ['album' => $album]),
        'pickerUrl' => route('gallery.picker'),
        'deleteConfirm' => __('gallery.album_delete_confirm'),
        'removeConfirm' => __('gallery.album_remove_confirm'),
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
    <div class="flex flex-col gap-4 md:flex-row" x-data="albumPage(@js($cfg))" x-init="init()">
        @include('gallery._sidebar')

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <a href="{{ route('gallery.albums') }}" class="text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="chevron-left" class="h-5 w-5" /></a>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100" x-text="album.name"></h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-button variant="secondary" icon="plus" x-show="album.can_edit" @click="openPicker()">{{ __('gallery.album_add_photos') }}</x-button>
                    <x-button variant="secondary" icon="pencil" x-show="album.owned" @click="openRename()">{{ __('gallery.person_rename') }}</x-button>
                    <x-button variant="secondary" icon="share" x-show="album.owned" @click="openShare('albums', {{ Illuminate\Support\Js::from($album->id) }}, album.name)">{{ __('shares.share') }}</x-button>
                    <x-button variant="danger" icon="trash" x-show="album.owned" @click="destroyAlbum()">{{ __('gallery.album_delete') }}</x-button>
                </div>
            </div>

            <template x-if="!loading && !photos.length">
                <div class="mt-8 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.album_empty') }}</div>
            </template>

            <div class="mt-6 grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                <template x-for="ph in photos" :key="ph.id">
                    <div class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                        <a :href="'/gallery/' + ph.id + '/original'"><img :src="ph.thumb" alt="" class="h-full w-full object-cover" loading="lazy"></a>
                        <button type="button" x-show="album.can_edit" @click="removePhoto(ph.id)"
                            class="absolute right-1 top-1 hidden rounded-full bg-gray-900/80 p-1 text-white group-hover:block" title="{{ __('gallery.album_remove') }}">
                            <x-icon name="x-mark" class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </div>
        </div>

        @include('partials.share-modal')

        {{-- Add-photos picker --}}
        <div x-show="picker.open" x-cloak class="fixed inset-0 z-[65] flex items-start justify-center overflow-y-auto p-4" @keydown.escape.window="picker.open=false">
            <div class="absolute inset-0 bg-gray-900/50" @click="picker.open=false"></div>
            <div class="relative my-10 w-full max-w-2xl rounded-lg bg-white dark:bg-gray-900 p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('gallery.album_add_photos') }}</h3>
                    <button type="button" @click="picker.open=false" class="text-gray-400 dark:text-gray-500 hover:text-gray-600"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>
                <div class="mt-4 grid max-h-[60vh] grid-cols-4 gap-2 overflow-y-auto sm:grid-cols-6">
                    <template x-for="p in picker.list" :key="p.id">
                        <button type="button" @click="togglePick(p.id)"
                            class="relative aspect-square overflow-hidden rounded-md ring-2"
                            :class="picker.chosen.includes(p.id) ? 'ring-gray-900' : 'ring-transparent'">
                            <img :src="p.thumb" alt="" class="h-full w-full object-cover" loading="lazy">
                        </button>
                    </template>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <x-button variant="secondary" @click="picker.open=false">{{ __('common.cancel') }}</x-button>
                    <x-button variant="primary" @click="addChosen()"><span x-text="'{{ __('gallery.album_pick_done') }}'"></span> <span x-show="picker.chosen.length" x-text="'('+picker.chosen.length+')'"></span></x-button>
                </div>
            </div>
        </div>

        {{-- Rename modal --}}
        <div x-show="renameModal.open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4" @keydown.escape.window="renameModal.open=false">
            <div class="absolute inset-0 bg-gray-900/40" @click="renameModal.open=false"></div>
            <div class="relative my-16 w-full max-w-sm rounded-lg bg-white dark:bg-gray-900 p-5 shadow-xl">
                <form @submit.prevent="saveRename()">
                    <input x-model="renameModal.value" class="w-full rounded-md border-gray-300 text-sm">
                    <div class="mt-4 flex justify-end gap-2">
                        <x-button variant="secondary" @click="renameModal.open=false">{{ __('common.cancel') }}</x-button>
                        <x-button variant="primary" type="submit">{{ __('contacts.ui.save') }}</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
