<x-layouts.app :title="__('contacts.ui.heading')">
    @php $cfg = [
        'dataUrl' => route('contacts.data'),
        'contactBase' => url('contacts'),
        'createUrl' => route('contacts.create'),
        'booksUrl' => route('address-books.store'),
        'bookBase' => url('address-books'),
        'groupsUrl' => route('contact-groups.store'),
        'groupBase' => url('contact-groups'),
        'importUrl' => route('contacts.import'),
        'exportUrl' => route('contacts.export'),
        'settingsUrl' => route('contacts.settings'),
        'importResultLabel' => __('contacts.ui.import_result'),
        'sharesDataUrl' => route('shares.data'),
        'sharesUrl' => route('shares.store'),
        'sharesBase' => url('shares'),
        'shareError' => __('shares.error'),
        'shareLink' => route('contacts.index'),
        'mailConfigured' => \App\Services\Notifications\ChannelNotifier::mailConfigured(),
        'linkCopied' => __('shares.link_copied'),
        'mailSent' => __('shares.mail_sent'),
        'mailUnavailable' => __('shares.mail_unavailable'),
        'publicStoreUrl' => route('public-share.store'),
        'publicBase' => url('shares/public'),
        'token' => csrf_token(),
        'newBook' => __('contacts.ui.new_book'),
        'newGroup' => __('contacts.ui.new_group'),
        'bookBase' => url('address-books'),
        'groupBase' => url('contact-groups'),
        'renameBook' => __('contacts.ui.rename_book'),
        'confirmDeleteBook' => __('contacts.ui.delete_book_confirm'),
        'confirmDeleteGroup' => __('contacts.ui.delete_group_confirm'),
    ]; @endphp
    <div x-data="contactsPage(@js($cfg))" x-init="init()" class="flex flex-col gap-4 md:flex-row">
        {{-- Sidebar: trigger + rail (md) + slide-over (mobile) --}}
        <div class="md:hidden">
            <button type="button" @click="$store.nav.toggleSidebar()"
                class="flex min-h-11 w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium text-gray-700 shadow-sm">
                <x-icon name="bars-3" class="h-4 w-4 text-gray-400" />
                <span>{{ __('contacts.ui.books') }}</span>
            </button>
        </div>
        <aside class="hidden w-full shrink-0 space-y-4 self-start rounded-lg border border-gray-200 bg-white p-3 shadow-sm md:block md:w-56">
            @include('contacts._sidebar_content')
        </aside>
        <x-sheet side="left" store="sidebarOpen" :title="__('contacts.ui.books')">
            <div class="space-y-4">@include('contacts._sidebar_content')</div>
        </x-sheet>

        {{-- Main --}}
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <input type="search" x-model.debounce.300ms="q" placeholder="{{ __('contacts.ui.search') }}"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                <x-button variant="primary" icon="plus" class="shrink-0" @click="openEditor(null)">{{ __('contacts.ui.new_contact') }}</x-button>
            </div>

            {{-- Sort + display-name format --}}
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500">
                <label class="flex items-center gap-1.5">
                    <span>{{ __('contacts.ui.sort_by') }}</span>
                    <select x-model="sort" @change="saveSettings()" class="rounded-md border-gray-300 py-1 text-xs">
                        <option value="first_name">{{ __('contacts.ui.sort_first_name') }}</option>
                        <option value="last_name">{{ __('contacts.ui.sort_last_name') }}</option>
                    </select>
                </label>
                <label class="flex items-center gap-1.5">
                    <span>{{ __('contacts.ui.display_format') }}</span>
                    <select x-model="displayFormat" @change="saveSettings()" class="rounded-md border-gray-300 py-1 text-xs">
                        <option value="first_last">{{ __('contacts.ui.display_first_last') }}</option>
                        <option value="last_first">{{ __('contacts.ui.display_last_first') }}</option>
                    </select>
                </label>
            </div>

            <template x-if="!loading && contacts.length===0">
                <p class="mt-8 text-center text-sm text-gray-500">{{ __('contacts.ui.empty') }}</p>
            </template>

            <ul class="mt-4 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                <template x-for="c in contacts" :key="c.id">
                    <li class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer" @click="openEditor(c.id)">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 text-xs font-medium text-gray-500 ring-1 ring-gray-200">
                            <template x-if="c.avatar"><img :src="c.avatar" alt="" class="h-full w-full object-cover"></template>
                            <template x-if="! c.avatar && initials(c)"><span x-text="initials(c)"></span></template>
                            <template x-if="! c.avatar && ! initials(c)"><x-icon name="user" class="h-4 w-4 text-gray-400" /></template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900" x-text="displayName(c)"></p>
                            <p class="truncate text-xs text-gray-500" x-text="(c.org || '') + (c.emails[0] ? ' · '+c.emails[0] : '')"></p>
                        </div>
                        <button type="button" @click.stop="toggleFavorite(c)"
                            class="inline-flex min-h-9 min-w-9 shrink-0 items-center justify-center text-gray-400 hover:text-gray-700"
                            :title="c.favorite ? '{{ __('contacts.ui.favorite_remove') }}' : '{{ __('contacts.ui.favorite_add') }}'">
                            <x-icon x-show="! c.favorite" name="star" class="h-4 w-4" />
                            <x-icon x-show="c.favorite" x-cloak name="star-solid" class="h-4 w-4" />
                        </button>
                    </li>
                </template>
            </ul>
        </div>

        {{-- Name prompt modal (new/rename address book or group) --}}
        <div x-show="nameModal.open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="nameModal.open=false">
            <div class="absolute inset-0 bg-gray-900/40" @click="nameModal.open=false"></div>
            <div class="relative my-16 w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900" x-text="nameModal.title"></h3>
                <form @submit.prevent="submitNameModal()">
                    <input x-ref="nameInput" x-model="nameModal.value" class="mt-3 w-full rounded-md border-gray-300 text-sm" type="text">
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" @click="nameModal.open=false" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('contacts.ui.cancel') }}</button>
                        <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('contacts.ui.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @include('partials.share-modal')

        {{-- Confirm modal (delete contact / book / group) --}}
        <div x-show="confirmModal.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="confirmModal.open=false">
            <div class="absolute inset-0 bg-gray-900/40" @click="confirmModal.open=false"></div>
            <div class="relative my-24 w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('contacts.ui.confirm_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600" x-text="confirmModal.message"></p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="confirmModal.open=false" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('contacts.ui.cancel') }}</button>
                    <button type="button" @click="doConfirm()" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('contacts.ui.delete') }}</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
