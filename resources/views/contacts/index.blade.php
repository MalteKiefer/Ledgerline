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
        'token' => csrf_token(),
        'bulkDestroyUrl' => route('contacts.bulk-destroy'),
        'deleteSelectedConfirm' => __('contacts.ui.delete_selected_confirm'),
        'newBook' => __('contacts.ui.new_book'),
        'newGroup' => __('contacts.ui.new_group'),
        'renameBook' => __('contacts.ui.rename_book'),
        'confirmDeleteBook' => __('contacts.ui.delete_book_confirm'),
        'confirmDeleteGroup' => __('contacts.ui.delete_group_confirm'),
    ]; @endphp

    <div x-data="contactsPage(@js($cfg))" x-init="init()"
        class="flex overflow-hidden rounded-xl border border-md-outline-variant bg-md-surface md:h-[calc(100vh-8.5rem)]">

        {{-- Sidebar: desktop rail + mobile slide-over --}}
        <x-m3.sidebar class="hidden md:flex">
            <x-slot:top>
                <x-m3.button icon="add" variant="tonal" class="w-full" @click="openEditor(null)">{{ __('contacts.ui.new_contact') }}</x-m3.button>
            </x-slot:top>
            @include('contacts._sidebar_content')
        </x-m3.sidebar>
        <x-sheet side="left" store="sidebarOpen" :title="__('contacts.ui.books')">
            <div class="flex flex-col gap-1">
                <x-m3.button icon="add" variant="tonal" class="w-full" @click="openEditor(null)">{{ __('contacts.ui.new_contact') }}</x-m3.button>
                @include('contacts._sidebar_content')
            </div>
        </x-sheet>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Toolbar: search + sort/format --}}
            <x-m3.toolbar class="gap-3">
                <button type="button" class="md:hidden m3-state flex h-10 w-10 items-center justify-center rounded-full text-md-on-surface-var" @click="$store.nav.toggleSidebar()">
                    <span class="msym text-xl">menu</span>
                </button>
                <label class="flex flex-1 items-center gap-2.5 rounded-full bg-md-surface-variant px-4 py-2 text-md-on-surface-var">
                    <span class="msym text-xl">search</span>
                    <input type="search" x-model.debounce.300ms="q" placeholder="{{ __('contacts.ui.search') }}"
                        class="w-full border-0 bg-transparent p-0 text-sm text-md-on-surface placeholder:text-md-on-surface-var focus:ring-0">
                </label>
                <div class="hidden items-center gap-2 sm:flex">
                    <select x-model="sort" @change="saveSettings()"
                        class="rounded-lg border border-md-outline bg-transparent py-1.5 pl-2.5 pr-7 text-xs text-md-on-surface-var focus:border-md-primary focus:ring-0">
                        <option value="first_name">{{ __('contacts.ui.sort_first_name') }}</option>
                        <option value="last_name">{{ __('contacts.ui.sort_last_name') }}</option>
                    </select>
                    <select x-model="displayFormat" @change="saveSettings()"
                        class="rounded-lg border border-md-outline bg-transparent py-1.5 pl-2.5 pr-7 text-xs text-md-on-surface-var focus:border-md-primary focus:ring-0">
                        <option value="first_last">{{ __('contacts.ui.display_first_last') }}</option>
                        <option value="last_first">{{ __('contacts.ui.display_last_first') }}</option>
                    </select>
                </div>
            </x-m3.toolbar>

            {{-- Bulk selection bar --}}
            <div x-show="selected.length" x-cloak class="flex flex-wrap items-center gap-3 border-b border-md-outline-variant bg-md-selected px-4 py-2 text-sm">
                <span class="font-semibold text-md-primary" x-text="@js(__('contacts.ui.selected_count')).replace(':count', selected.length)"></span>
                <button type="button" @click="toggleAll()" class="text-md-on-surface-var hover:text-md-primary">{{ __('contacts.ui.select_all') }}</button>
                <button type="button" @click="selected = []" class="text-md-on-surface-var hover:text-md-primary">{{ __('contacts.ui.clear_selection') }}</button>
                <x-m3.button variant="danger" icon="delete" size="sm" class="ml-auto" @click="bulkDelete()">{{ __('contacts.ui.delete_selected') }}</x-m3.button>
            </div>

            {{-- Column header --}}
            <div class="grid grid-cols-[24px_40px_1.6fr_1.4fr_1fr_40px] items-center gap-3 border-b border-md-outline-variant px-4 py-2 text-[11px] font-medium uppercase tracking-wide text-md-on-surface-var">
                <span></span><span></span><span>{{ __('contacts.ui.first_name') }} / {{ __('contacts.ui.last_name') }}</span><span>{{ __('contacts.ui.email') }}</span><span>{{ __('contacts.ui.phone') }}</span><span></span>
            </div>

            {{-- List --}}
            <div class="min-h-0 flex-1 overflow-y-auto">
                <template x-if="!loading && contacts.length===0">
                    <p class="py-16 text-center text-sm text-md-on-surface-var">{{ __('contacts.ui.empty') }}</p>
                </template>
                <template x-for="c in contacts" :key="c.id">
                    <div class="m3-state grid cursor-pointer grid-cols-[24px_40px_1.6fr_1.4fr_1fr_40px] items-center gap-3 border-b border-md-outline-variant/60 px-4 py-2" @click="openEditor(c.id)">
                        <input type="checkbox" :value="c.id" x-model="selected" @click.stop
                            class="h-4 w-4 rounded border-md-outline text-md-primary focus:ring-md-primary">
                        <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full text-sm font-medium text-white"
                            :style="!c.avatar && ('background:'+avatarColor(c))">
                            <template x-if="c.avatar"><img :src="c.avatar" alt="" class="h-full w-full object-cover"></template>
                            <template x-if="! c.avatar && initials(c)"><span x-text="initials(c)"></span></template>
                            <template x-if="! c.avatar && ! initials(c)"><span class="msym text-lg">person</span></template>
                        </div>
                        <span class="truncate text-sm font-medium text-md-on-surface" x-text="displayName(c)"></span>
                        <span class="truncate text-sm text-md-on-surface-var" x-text="c.emails && c.emails[0] ? c.emails[0] : ''"></span>
                        <span class="truncate text-sm text-md-on-surface-var" x-text="c.phones && c.phones[0] ? c.phones[0] : ''"></span>
                        <button type="button" @click.stop="toggleFavorite(c)"
                            class="m3-state flex h-9 w-9 items-center justify-center rounded-full text-md-on-surface-var"
                            :class="c.favorite ? 'opacity-100 text-md-primary' : 'opacity-0 group-hover:opacity-100'"
                            :title="c.favorite ? '{{ __('contacts.ui.favorite_remove') }}' : '{{ __('contacts.ui.favorite_add') }}'">
                            <span class="msym text-lg" :class="c.favorite && 'msym-fill'">star</span>
                        </button>
                    </div>
                </template>
            </div>
        </div>

        {{-- Name prompt modal (new/rename address book or group) --}}
        <div x-show="nameModal.open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" @keydown.escape.window="nameModal.open=false">
            <div class="absolute inset-0 bg-black/40" @click="nameModal.open=false"></div>
            <x-m3.card class="relative my-16 w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold text-md-on-surface" x-text="nameModal.title"></h3>
                <form @submit.prevent="submitNameModal()">
                    <input x-ref="nameInput" x-model="nameModal.value" type="text"
                        class="mt-4 w-full rounded-lg border border-md-outline bg-transparent px-3 py-2.5 text-sm text-md-on-surface focus:border-md-primary focus:ring-1 focus:ring-md-primary">
                    <div class="mt-5 flex justify-end gap-2">
                        <x-m3.button variant="text" type="button" @click="nameModal.open=false">{{ __('contacts.ui.cancel') }}</x-m3.button>
                        <x-m3.button variant="filled" type="submit">{{ __('contacts.ui.save') }}</x-m3.button>
                    </div>
                </form>
            </x-m3.card>
        </div>

        {{-- Confirm modal (delete contact / book / group) --}}
        <div x-show="confirmModal.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" @keydown.escape.window="confirmModal.open=false">
            <div class="absolute inset-0 bg-black/40" @click="confirmModal.open=false"></div>
            <x-m3.card class="relative my-24 w-full max-w-sm p-6">
                <h3 class="text-lg font-semibold text-md-on-surface">{{ __('contacts.ui.confirm_title') }}</h3>
                <p class="mt-2 text-sm text-md-on-surface-var" x-text="confirmModal.message"></p>
                <div class="mt-5 flex justify-end gap-2">
                    <x-m3.button variant="text" type="button" @click="confirmModal.open=false">{{ __('contacts.ui.cancel') }}</x-m3.button>
                    <x-m3.button variant="danger" type="button" @click="doConfirm()">{{ __('contacts.ui.delete') }}</x-m3.button>
                </div>
            </x-m3.card>
        </div>
    </div>
</x-layouts.app>
