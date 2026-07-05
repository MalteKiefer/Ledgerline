<x-layouts.app :title="__('contacts.ui.heading')">
    @php $cfg = [
        'dataUrl' => route('contacts.data'),
        'storeUrl' => route('contacts.store'),
        'contactBase' => url('contacts'),
        'booksUrl' => route('address-books.store'),
        'bookBase' => url('address-books'),
        'groupsUrl' => route('contact-groups.store'),
        'groupBase' => url('contact-groups'),
        'importUrl' => route('contacts.import'),
        'exportUrl' => route('contacts.export'),
        'settingsUrl' => route('contacts.settings'),
        'importResultLabel' => __('contacts.ui.import_result'),
        'galleryPickerUrl' => route('gallery.picker'),
        'peopleUrl' => route('gallery.people.data'),
        'peopleShowBase' => url('gallery/people'),
        'filesDataUrl' => route('files.data'),
        'filesRawBase' => url('files/raw'),
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
        'confirmDelete' => __('contacts.ui.delete_confirm'),
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
                    </li>
                </template>
            </ul>
        </div>

        {{-- Editor modal --}}
        <div x-show="editor" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="editor=false">
            <div class="absolute inset-0 bg-gray-900/40" @click="editor=false"></div>
            <div class="relative my-8 w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900" x-text="form.id ? '{{ __('contacts.ui.edit_contact') }}' : '{{ __('contacts.ui.new_contact') }}'"></h3>
                <div class="mt-4 space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                            <template x-if="form.avatar"><img :src="form.avatar" class="h-full w-full object-cover"></template>
                        </div>
                        <x-button variant="secondary" icon="pencil" class="!px-2 !py-1 !text-xs" x-show="form.id" @click="openAvatarModal()">{{ __('contacts.ui.avatar_change') }}</x-button>
                        <span class="text-[11px] text-gray-400" x-show="! form.id">{{ __('contacts.ui.avatar_after_save') }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input x-model="form.first_name" placeholder="{{ __('contacts.ui.first_name') }}" class="rounded-md border-gray-300 text-sm">
                        <input x-model="form.last_name" placeholder="{{ __('contacts.ui.last_name') }}" class="rounded-md border-gray-300 text-sm">
                    </div>
                    <input x-model="form.fn" placeholder="{{ __('contacts.ui.name_placeholder') }}" class="w-full rounded-md border-gray-300 text-sm">
                    <input x-model="form.org" placeholder="{{ __('contacts.ui.org') }}" class="w-full rounded-md border-gray-300 text-sm">
                    <input x-model="form.title" placeholder="{{ __('contacts.ui.title') }}" class="w-full rounded-md border-gray-300 text-sm">
                    <template x-for="(e,i) in form.emails" :key="'e'+i">
                        <input x-model="form.emails[i].value" type="email" placeholder="{{ __('contacts.ui.email') }}" class="w-full rounded-md border-gray-300 text-sm">
                    </template>
                    <button @click="form.emails.push({value:'',type:'home'})" class="text-xs text-gray-500 hover:text-gray-800 inline-flex items-center gap-1"><x-icon name="plus" class="h-3 w-3" /> {{ __('contacts.ui.email') }}</button>
                    <template x-for="(p,i) in form.phones" :key="'p'+i">
                        <input x-model="form.phones[i].value" placeholder="{{ __('contacts.ui.phone') }}" class="w-full rounded-md border-gray-300 text-sm">
                    </template>
                    <button @click="form.phones.push({value:'',type:'cell'})" class="text-xs text-gray-500 hover:text-gray-800 inline-flex items-center gap-1"><x-icon name="plus" class="h-3 w-3" /> {{ __('contacts.ui.phone') }}</button>
                    <label class="block text-xs text-gray-500">{{ __('contacts.ui.bday') }}
                        <input type="date" x-model="form.bday" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </label>
                    <div>
                        <span class="text-xs text-gray-500">{{ __('contacts.ui.anniversaries') }}</span>
                        <template x-for="(a,i) in form.anniversaries" :key="'a'+i">
                            <div class="mt-1 flex items-center gap-2">
                                <input type="date" x-model="form.anniversaries[i].date" class="rounded-md border-gray-300 text-sm">
                                <input x-model="form.anniversaries[i].label" placeholder="{{ __('contacts.ui.anniversary_label') }}" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                                <button type="button" @click="form.anniversaries.splice(i,1)" class="shrink-0 text-gray-400 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                            </div>
                        </template>
                        <button type="button" @click="form.anniversaries.push({date:'',label:''})" class="mt-1 text-xs text-gray-500 hover:text-gray-800 inline-flex items-center gap-1"><x-icon name="plus" class="h-3 w-3" /> {{ __('contacts.ui.anniversary') }}</button>
                    </div>
                    <textarea x-model="form.note" placeholder="{{ __('contacts.ui.note') }}" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    {{-- Groups: multi-select with autocomplete --}}
                    <div>
                        <span class="text-xs text-gray-500">{{ __('contacts.ui.groups') }}</span>
                        <div class="mt-1 rounded-md border border-gray-300 px-2 py-1.5" @click="$refs.groupInput?.focus()">
                            <div class="flex flex-wrap items-center gap-1">
                                <template x-for="gid in (form.group_ids || [])" :key="gid">
                                    <span class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                        <span x-text="groupName(gid)"></span>
                                        <button type="button" @click="removeGroupChip(gid)" class="text-gray-400 hover:text-red-600">&times;</button>
                                    </span>
                                </template>
                                <div class="relative min-w-[6rem] flex-1">
                                    <input x-ref="groupInput" x-model="groupQuery" @focus="groupOpen=true" @click="groupOpen=true"
                                        @keydown.escape.stop="groupOpen=false" @click.outside="groupOpen=false"
                                        placeholder="{{ __('contacts.ui.group_add_placeholder') }}" class="w-full border-0 p-0 text-sm focus:ring-0">
                                    <div x-show="groupOpen && filteredGroups().length" x-cloak
                                        class="absolute z-20 mt-1 max-h-40 w-56 overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                        <template x-for="g in filteredGroups()" :key="g.id">
                                            <button type="button" @click="addGroupChip(g.id)" class="block w-full px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50" x-text="g.name"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <select x-model="form.book_id" class="w-full rounded-md border-gray-300 text-sm">
                        <template x-for="b in books.filter(x=>x.owned)" :key="b.id"><option :value="b.id" x-text="b.name"></option></template>
                    </select>
                </div>
                <div class="mt-5 flex items-center justify-between">
                    <button x-show="form.id" @click="destroy()" class="text-sm text-red-600 hover:text-red-700">{{ __('contacts.ui.delete') }}</button>
                    <div class="ml-auto flex gap-2">
                        <button @click="editor=false" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __('contacts.ui.cancel') }}</button>
                        <button @click="save()" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('contacts.ui.save') }}</button>
                    </div>
                </div>
            </div>
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

        {{-- Avatar picker + crop (device / gallery / people / files) --}}
        <div x-show="avatarModal.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4" role="dialog" @keydown.escape.window="closeAvatarModal()">
            <div class="absolute inset-0 bg-gray-900/50" @click="closeAvatarModal()"></div>
            <div class="relative my-10 w-full max-w-2xl rounded-lg bg-white p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('contacts.ui.avatar_pick_title') }}</h3>
                    <button type="button" @click="closeAvatarModal()" class="text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>

                {{-- Crop step --}}
                <template x-if="cropSrc">
                    <div class="mt-4">
                        <div class="mx-auto max-h-[52vh] overflow-hidden bg-gray-50">
                            <img x-ref="cropImg" :src="cropSrc" alt="" class="block max-w-full">
                        </div>
                        <p class="mt-2 text-xs text-gray-500">{{ __('contacts.ui.avatar_crop_hint') }}</p>
                        <div class="mt-3 flex justify-end gap-2">
                            <x-button @click="cropSrc=null; destroyCropper()">{{ __('contacts.ui.cancel') }}</x-button>
                            <x-button variant="primary" x-bind:disabled="saving" @click="confirmCrop()">{{ __('contacts.ui.avatar_apply') }}</x-button>
                        </div>
                    </div>
                </template>

                {{-- Source picker --}}
                <div x-show="!cropSrc" class="mt-4">
                    <div class="flex gap-1 border-b border-gray-100 text-sm">
                        @foreach (['upload','gallery','people','files'] as $t)
                            <button type="button" @click="avatarTab('{{ $t }}')"
                                :class="avatarModal.tab==='{{ $t }}' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-800'"
                                class="-mb-px border-b-2 px-3 py-2 font-medium">{{ __('contacts.ui.avatar_tab_'.$t) }}</button>
                        @endforeach
                    </div>

                    <div class="mt-4 min-h-[12rem]">
                        <p x-show="avatarModal.loading" class="py-8 text-center text-sm text-gray-400">…</p>

                        {{-- Upload --}}
                        <div x-show="avatarModal.tab==='upload' && !avatarModal.loading">
                            <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 py-12 text-sm text-gray-600 hover:bg-gray-50">
                                <x-icon name="arrow-up-tray" class="h-6 w-6 text-gray-400" />
                                <span>{{ __('contacts.ui.avatar_choose_file') }}</span>
                                <input type="file" accept="image/*" class="hidden" @change="pickDeviceImage($event)">
                            </label>
                        </div>

                        {{-- Gallery --}}
                        <div x-show="avatarModal.tab==='gallery' && !avatarModal.loading">
                            <p x-show="!galleryPhotos.length" class="py-8 text-center text-sm text-gray-400">{{ __('contacts.ui.avatar_no_images') }}</p>
                            <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                <template x-for="p in galleryPhotos" :key="p.id">
                                    <button type="button" @click="startCrop(p.full)" class="aspect-square overflow-hidden rounded-md ring-1 ring-gray-200 hover:ring-gray-900">
                                        <img :src="p.thumb" alt="" class="h-full w-full object-cover" loading="lazy">
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- People: pick a person (filter) → choose one of their photos --}}
                        <div x-show="avatarModal.tab==='people' && !avatarModal.loading">
                            {{-- Step 1: person list --}}
                            <div x-show="!personSelected">
                                <p x-show="!peopleList.length" class="py-8 text-center text-sm text-gray-400">{{ __('contacts.ui.avatar_no_images') }}</p>
                                <div class="grid grid-cols-4 gap-3 sm:grid-cols-6">
                                    <template x-for="p in peopleList" :key="p.id">
                                        <button type="button" @click="pickPerson(p)" class="text-center">
                                            <span class="block aspect-square overflow-hidden rounded-full ring-1 ring-gray-200 hover:ring-gray-900">
                                                <img :src="p.cover" alt="" class="h-full w-full object-cover" loading="lazy">
                                            </span>
                                            <span class="mt-1 block truncate text-xs text-gray-500" x-text="p.name || ''"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            {{-- Step 2: that person's photos (crop the full image) --}}
                            <div x-show="personSelected">
                                <button type="button" @click="backToPeople()" class="mb-3 inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
                                    <x-icon name="chevron-left" class="h-4 w-4" /> <span x-text="personSelected?.name"></span>
                                </button>
                                <p x-show="!personPhotos.length" class="py-8 text-center text-sm text-gray-400">{{ __('contacts.ui.avatar_no_images') }}</p>
                                <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                    <template x-for="ph in personPhotos" :key="ph.id">
                                        <button type="button" @click="startCrop(ph.full)" class="aspect-square overflow-hidden rounded-md ring-1 ring-gray-200 hover:ring-gray-900">
                                            <img :src="ph.thumb" alt="" class="h-full w-full object-cover" loading="lazy">
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Files --}}
                        <div x-show="avatarModal.tab==='files' && !avatarModal.loading">
                            <p x-show="!filePhotos.length" class="py-8 text-center text-sm text-gray-400">{{ __('contacts.ui.avatar_no_images') }}</p>
                            <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                <template x-for="(p,i) in filePhotos" :key="i">
                                    <button type="button" @click="startCrop(p.url)" class="aspect-square overflow-hidden rounded-md ring-1 ring-gray-200 hover:ring-gray-900" :title="p.name">
                                        <img :src="p.url" alt="" class="h-full w-full object-cover" loading="lazy">
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
