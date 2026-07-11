<x-layouts.app :title="__('contacts.title')">
  <div x-data="contacts({
        uploadUrl: '{{ route('contacts.upload') }}',
        rawBase: '{{ url('/contacts/raw') }}',
        blobBase: '{{ route('contacts.blob.destroy', ['blob' => '__id__']) }}',
        reconcileUrl: '{{ route('contacts.blobs.reconcile') }}',
        usageUrl: '{{ route('contacts.usage') }}',
        token: '{{ csrf_token() }}',
     }, {
        unnamed: @js(__('contacts.unnamed')),
        deleteConfirm: @js(__('contacts.delete_confirm')),
        emptyTrashConfirm: @js(__('contacts.empty_trash_confirm')),
        avatarFailed: @js(__('contacts.avatar_failed')),
        imported: @js(__('contacts.imported')),
        importFailed: @js(__('contacts.import_failed')),
     })">

    {{-- Zero-knowledge gate: contacts decrypt with the vault key. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <template x-if="state === 'locked'">
        <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 text-center">
            <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400"
               x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
            <button type="button" @click="$dispatch('vault-panel')"
                class="mt-5 inline-flex min-h-11 items-center gap-1.5 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                <x-icon name="lock-open" class="h-4 w-4" />
                <span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span>
            </button>
        </div>
    </template>

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-6 text-center text-sm text-red-700 dark:text-red-300">{{ __('contacts.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div class="flex h-[calc(100dvh-11rem)] gap-4 md:h-[calc(100vh-10rem)]">
        {{-- List pane --}}
        <aside class="w-full flex-col rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm md:w-80 md:shrink-0"
            :class="current ? 'hidden md:flex' : 'flex'">
            <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 p-3">
                <input type="search" x-model="query" placeholder="{{ __('contacts.search') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                <x-button variant="primary" icon="plus" class="shrink-0 !gap-0" title="{{ __('contacts.new') }}" @click="newContact()"></x-button>
            </div>
            <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-800 px-3 py-2 text-xs">
                <button type="button" @click="view = 'active'" :class="view === 'active' ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'">{{ __('contacts.all') }}</button>
                <button type="button" @click="onlyFav = ! onlyFav" x-show="view === 'active'" :class="onlyFav ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'">{{ __('contacts.favorites') }}</button>
                <button type="button" @click="view = 'trash'" :class="view === 'trash' ? 'font-semibold text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'">{{ __('contacts.trash') }} (<span x-text="trashCount"></span>)</button>
                <button type="button" x-show="view === 'trash' && trashCount" @click="emptyTrash()" class="ml-auto text-red-600 hover:text-red-700">{{ __('contacts.empty_trash') }}</button>
            </div>
            <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-800 px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                <label class="inline-flex cursor-pointer items-center gap-1 hover:text-gray-800 dark:hover:text-gray-200" title="{{ __('contacts.import') }}">
                    <x-icon name="arrow-up-tray" class="h-3.5 w-3.5" />{{ __('contacts.import') }}
                    <input type="file" accept=".vcf,text/vcard" class="hidden" @change="importFile($event)">
                </label>
                <button type="button" x-show="contacts.some(c => ! c.trashed)" @click="exportAll()" class="inline-flex items-center gap-1 hover:text-gray-800 dark:hover:text-gray-200" title="{{ __('contacts.export_all') }}"><x-icon name="arrow-down-tray" class="h-3.5 w-3.5" />{{ __('contacts.export_all') }}</button>
                <span x-show="importing" x-cloak class="text-gray-400">…</span>
                <div class="ml-auto flex items-center gap-1.5">
                    <select x-model="sortBy" @change="_savePrefs()" title="{{ __('contacts.sort') }}" class="rounded border-gray-200 dark:border-gray-700 dark:bg-gray-800 py-0.5 pl-1.5 pr-6 text-xs text-gray-600 dark:text-gray-300 focus:border-gray-400 focus:ring-0">
                        <option value="name">{{ __('contacts.sort_name') }}</option>
                        <option value="first">{{ __('contacts.sort_first') }}</option>
                        <option value="last">{{ __('contacts.sort_last') }}</option>
                        <option value="updated">{{ __('contacts.sort_updated') }}</option>
                    </select>
                    <select x-model="nameFormat" @change="_savePrefs()" title="{{ __('contacts.name_order') }}" class="rounded border-gray-200 dark:border-gray-700 dark:bg-gray-800 py-0.5 pl-1.5 pr-6 text-xs text-gray-600 dark:text-gray-300 focus:border-gray-400 focus:ring-0">
                        <option value="first">{{ __('contacts.name_first_last') }}</option>
                        <option value="last">{{ __('contacts.name_last_first') }}</option>
                    </select>
                </div>
            </div>
            <div x-show="allCategories.length" class="flex flex-wrap gap-1 border-b border-gray-100 dark:border-gray-800 p-2">
                <template x-for="t in allCategories" :key="t">
                    <button type="button" @click="activeTag = (activeTag === t ? '' : t)" class="rounded px-2 py-0.5 text-xs" :class="activeTag === t ? 'bg-gray-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300'" x-text="t"></button>
                </template>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto">
                <template x-for="c in filtered" :key="c.id">
                    <button type="button" @click="open(c)" class="flex w-full items-center gap-3 border-b border-gray-50 dark:border-gray-800/50 px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-800" :class="currentId === c.id ? 'bg-gray-50 dark:bg-gray-800' : ''">
                        <span class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 text-xs font-semibold text-gray-500 dark:text-gray-400" x-init="$nextTick(() => c.avatarRef && avatarFor(c))">
                            <img x-show="c.avatarRef && avatarUrls[c.avatarRef]" :src="c.avatarRef && avatarUrls[c.avatarRef]" class="h-full w-full object-cover">
                            <span x-show="! (c.avatarRef && avatarUrls[c.avatarRef])" x-text="initials(c)"></span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span class="truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="displayName(c)"></span>
                                <x-icon name="star-solid" class="h-3 w-3 shrink-0 text-amber-400" x-show="c.favorite" x-cloak />
                            </span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="c.org || (c.emails ?? [])[0]?.value || (c.phones ?? [])[0]?.value || ''"></span>
                        </span>
                    </button>
                </template>
                <p x-show="! filtered.length" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('contacts.empty') }}</p>
            </div>
        </aside>

        {{-- Editor pane --}}
        <section class="min-w-0 flex-1 overflow-y-auto" :class="current ? '' : 'hidden md:block'">
            <template x-if="! current">
                <div class="flex h-full items-center justify-center rounded-lg border border-dashed border-gray-300 dark:border-gray-700 text-sm text-gray-400 dark:text-gray-500">{{ __('contacts.pick') }}</div>
            </template>
            <template x-if="current">
              <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
                <button type="button" @click="close()" class="mb-3 inline-flex min-h-11 w-max items-center gap-1 text-sm text-gray-600 dark:text-gray-400 md:hidden"><x-icon name="chevron-left" class="h-4 w-4" />{{ __('common.back') }}</button>

                {{-- Header: avatar + name + actions --}}
                <div class="flex items-start gap-4">
                    <div class="relative">
                        <span class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 text-lg font-semibold text-gray-500 dark:text-gray-400" x-init="$nextTick(() => current.avatarRef && avatarFor(current))">
                            <img x-show="current.avatarRef && avatarUrls[current.avatarRef]" :src="current.avatarRef && avatarUrls[current.avatarRef]" class="h-full w-full object-cover">
                            <span x-show="! (current.avatarRef && avatarUrls[current.avatarRef])" x-text="initials(current)"></span>
                        </span>
                        <label x-show="editing" class="absolute -bottom-1 -right-1 flex h-6 w-6 cursor-pointer items-center justify-center rounded-full bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 shadow" title="{{ __('contacts.avatar') }}">
                            <x-icon name="camera" class="h-3.5 w-3.5" />
                            <input type="file" accept="image/*" class="hidden" @change="pickAvatar($event)">
                        </label>
                    </div>
                    <div class="min-w-0 flex-1">
                        <input x-show="editing" type="text" x-model="current.fn" @input.debounce.600ms="save()" placeholder="{{ __('contacts.name') }}" class="w-full border-0 border-b border-gray-100 dark:border-gray-800 dark:bg-transparent px-0 text-lg font-semibold text-gray-900 dark:text-gray-100 focus:border-gray-400 focus:ring-0">
                        <h2 x-show="! editing" class="truncate text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="displayName(current)"></h2>
                        <p x-show="! editing && (current.title || current.org)" x-cloak class="truncate text-sm text-gray-500 dark:text-gray-400" x-text="[current.title, current.org].filter(Boolean).join(' · ')"></p>
                        <div class="mt-1 flex items-center gap-2">
                            <button type="button" @click="toggleFavorite(current)" :class="current.favorite ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'" title="{{ __('contacts.favorite') }}"><x-icon name="star" class="h-4 w-4" /></button>
                            <button type="button" x-show="editing && current.avatarRef" @click="removeAvatar(current)" class="text-xs text-gray-400 hover:text-red-600">{{ __('contacts.remove_avatar') }}</button>
                            <span class="ml-auto flex items-center gap-1">
                                <button type="button" x-show="! editing && view !== 'trash'" @click="startEdit()" title="{{ __('contacts.edit') }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="pencil" class="h-4 w-4" /></button>
                                <button type="button" x-show="editing" @click="save(); editing = false" class="rounded-md bg-gray-900 dark:bg-gray-100 px-3 py-1 text-xs font-medium text-white dark:text-gray-900">{{ __('contacts.done') }}</button>
                                <button type="button" @click="exportOne(current)" title="{{ __('contacts.export') }}" class="rounded p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"><x-icon name="arrow-down-tray" class="h-4 w-4" /></button>
                                <template x-if="view === 'trash'">
                                    <span class="flex gap-1">
                                        <button type="button" @click="restore(current)" title="{{ __('contacts.restore') }}" class="rounded p-1 text-gray-400 hover:text-gray-700"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
                                        <button type="button" @click="remove(current)" title="{{ __('contacts.delete_forever') }}" class="rounded p-1 text-gray-400 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
                                    </span>
                                </template>
                                <button type="button" x-show="view !== 'trash'" @click="trash(current)" title="{{ __('contacts.to_trash') }}" class="rounded p-1 text-gray-400 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button>
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Read-only view --}}
                <div x-show="! editing" x-cloak class="mt-5 space-y-4 text-sm">
                    <template x-if="(current.emails||[]).length"><div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.emails') }}</p>
                        <template x-for="(e, i) in current.emails" :key="i"><p class="mt-0.5"><a :href="'mailto:' + e.value" class="text-gray-800 dark:text-gray-200 hover:underline" x-text="e.value"></a> <span class="text-xs text-gray-400" x-text="e.type"></span></p></template></div></template>
                    <template x-if="(current.phones||[]).length"><div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.phones') }}</p>
                        <template x-for="(p, i) in current.phones" :key="i"><p class="mt-0.5"><a :href="'tel:' + p.value" class="text-gray-800 dark:text-gray-200 hover:underline" x-text="p.value"></a> <span class="text-xs text-gray-400" x-text="p.type"></span></p></template></div></template>
                    <template x-if="(current.impp||[]).length"><div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.impp') }}</p>
                        <template x-for="(m, i) in current.impp" :key="i"><p class="mt-0.5 text-gray-800 dark:text-gray-200" x-text="m.value"></p></template></div></template>
                    <template x-if="(current.addresses||[]).length"><div><p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.addresses') }}</p>
                        <template x-for="(a, i) in current.addresses" :key="i"><div class="mt-0.5"><template x-for="line in addressLines(a)" :key="line"><span class="block text-gray-800 dark:text-gray-200" x-text="line"></span></template><span class="text-xs text-gray-400" x-text="a.type"></span></div></template></div></template>
                    <template x-if="current.bday || current.anniversary"><div class="flex gap-6">
                        <p x-show="current.bday"><span class="text-xs text-gray-400">{{ __('contacts.birthday') }}: </span><span class="text-gray-800 dark:text-gray-200" x-text="current.bday"></span></p>
                        <p x-show="current.anniversary"><span class="text-xs text-gray-400">{{ __('contacts.anniversary') }}: </span><span class="text-gray-800 dark:text-gray-200" x-text="current.anniversary"></span></p></div></template>
                    <template x-if="(current.categories||[]).length"><div class="flex flex-wrap gap-1"><template x-for="g in current.categories" :key="g"><span class="rounded bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs text-gray-700 dark:text-gray-300" x-text="g"></span></template></div></template>
                    <template x-if="current.note"><p class="whitespace-pre-wrap text-gray-700 dark:text-gray-300" x-text="current.note"></p></template>
                </div>

                {{-- Editor form --}}
                <div x-show="editing" x-cloak>

                {{-- Name parts --}}
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <input type="text" x-model="current.prefix" @input.debounce.600ms="save()" placeholder="{{ __('contacts.prefix') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="text" x-model="current.first" @input.debounce.600ms="save()" placeholder="{{ __('contacts.first_name') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="text" x-model="current.middle" @input.debounce.600ms="save()" placeholder="{{ __('contacts.middle_name') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="text" x-model="current.suffix" @input.debounce.600ms="save()" placeholder="{{ __('contacts.suffix') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="text" x-model="current.last" @input.debounce.600ms="save()" placeholder="{{ __('contacts.last_name') }}" class="col-span-2 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="text" x-model="current.nickname" @input.debounce.600ms="save()" placeholder="{{ __('contacts.nickname') }}" class="col-span-2 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </div>

                {{-- Work: company, department, title, role --}}
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <input type="text" x-model="current.org" @input.debounce.600ms="save()" placeholder="{{ __('contacts.org') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="text" x-model="current.department" @input.debounce.600ms="save()" placeholder="{{ __('contacts.department') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="text" x-model="current.title" @input.debounce.600ms="save()" placeholder="{{ __('contacts.job_title') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="text" x-model="current.role" @input.debounce.600ms="save()" placeholder="{{ __('contacts.role') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </div>

                {{-- Emails --}}
                <div class="mt-5">
                    <div class="mb-1 flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.emails') }}</span><button type="button" @click="addEmail()" class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">+ {{ __('contacts.add') }}</button></div>
                    <template x-for="(e, i) in current.emails" :key="i">
                        <div class="mb-1.5 flex items-center gap-2">
                            <select x-model="e.type" @change="save()" class="w-24 shrink-0 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500"><option value="home">{{ __('contacts.type_home') }}</option><option value="work">{{ __('contacts.type_work') }}</option><option value="other">{{ __('contacts.type_other') }}</option></select>
                            <input type="email" x-model="e.value" @input.debounce.600ms="save()" placeholder="name@example.com" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            <button type="button" @click="removeRow(current.emails, i)" class="shrink-0 text-gray-400 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                    </template>
                </div>

                {{-- Phones --}}
                <div class="mt-4">
                    <div class="mb-1 flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.phones') }}</span><button type="button" @click="addPhone()" class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">+ {{ __('contacts.add') }}</button></div>
                    <template x-for="(p, i) in current.phones" :key="i">
                        <div class="mb-1.5 flex items-center gap-2">
                            <select x-model="p.type" @change="save()" class="w-24 shrink-0 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500"><option value="cell">{{ __('contacts.type_cell') }}</option><option value="home">{{ __('contacts.type_home') }}</option><option value="work">{{ __('contacts.type_work') }}</option><option value="other">{{ __('contacts.type_other') }}</option></select>
                            <input type="tel" x-model="p.value" @input.debounce.600ms="save()" placeholder="+49 …" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            <button type="button" @click="removeRow(current.phones, i)" class="shrink-0 text-gray-400 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                    </template>
                </div>

                {{-- Messaging (IMPP) --}}
                <div class="mt-4">
                    <div class="mb-1 flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.impp') }}</span><button type="button" @click="addImpp()" class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">+ {{ __('contacts.add') }}</button></div>
                    <template x-for="(m, i) in current.impp" :key="i">
                        <div class="mb-1.5 flex items-center gap-2">
                            <select x-model="m.type" @change="save()" class="w-24 shrink-0 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500"><option value="home">{{ __('contacts.type_home') }}</option><option value="work">{{ __('contacts.type_work') }}</option><option value="other">{{ __('contacts.type_other') }}</option></select>
                            <input type="text" x-model="m.value" @input.debounce.600ms="save()" placeholder="xmpp:… / matrix:…" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            <button type="button" @click="removeRow(current.impp, i)" class="shrink-0 text-gray-400 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                    </template>
                </div>

                {{-- Addresses --}}
                <div class="mt-4">
                    <div class="mb-1 flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.addresses') }}</span><button type="button" @click="addAddress()" class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">+ {{ __('contacts.add') }}</button></div>
                    <template x-for="(a, i) in current.addresses" :key="i">
                        <div class="mb-2 rounded-md border border-gray-100 dark:border-gray-800 p-2">
                            <div class="flex items-center gap-2">
                                <select x-model="a.type" @change="save()" class="w-24 shrink-0 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500"><option value="home">{{ __('contacts.type_home') }}</option><option value="work">{{ __('contacts.type_work') }}</option><option value="other">{{ __('contacts.type_other') }}</option></select>
                                <input type="text" x-model="a.street" @input.debounce.600ms="save()" placeholder="{{ __('contacts.street') }}" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <button type="button" @click="removeRow(current.addresses, i)" class="shrink-0 text-gray-400 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                            </div>
                            <div class="mt-1.5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <input type="text" x-model="a.zip" @input.debounce.600ms="save()" placeholder="{{ __('contacts.zip') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <input type="text" x-model="a.city" @input.debounce.600ms="save()" placeholder="{{ __('contacts.city') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <input type="text" x-model="a.region" @input.debounce.600ms="save()" placeholder="{{ __('contacts.region') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <input type="text" x-model="a.country" @input.debounce.600ms="save()" placeholder="{{ __('contacts.country') }}" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Birthday + note + categories --}}
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.birthday') }}
                        <input type="date" x-model="current.bday" @change="save()" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </label>
                    <label class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.anniversary') }}
                        <input type="date" x-model="current.anniversary" @change="save()" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </label>
                    <label class="text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.categories') }}
                        <input type="text" x-model="tagsValue" @change="save()" placeholder="{{ __('contacts.categories_hint') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </label>
                </div>
                <textarea x-model="current.note" @input.debounce.600ms="save()" placeholder="{{ __('contacts.note') }}" class="mt-3 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500" rows="3"></textarea>
                </div>{{-- /editor form --}}

                {{-- Linked gallery person --}}
                <div class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-3">
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.linked_person') }}</span>
                    <div x-show="current.personId" x-cloak class="mt-1 flex items-center gap-2 text-sm">
                        <x-icon name="user" class="h-4 w-4 text-gray-400" />
                        <span class="text-gray-800 dark:text-gray-200" x-text="linkedPersonName || '{{ __('contacts.linked_person') }}'"></span>
                        <a :href="galleryHref(current)" class="text-gray-500 hover:underline">{{ __('contacts.show_photos') }}</a>
                        <button type="button" @click="unlinkPerson()" class="text-gray-400 hover:text-red-600">{{ __('contacts.unlink') }}</button>
                    </div>
                    <button type="button" x-show="! current.personId" @click="openPersonPicker()" class="mt-1 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800 dark:hover:text-gray-200"><x-icon name="users" class="h-4 w-4" />{{ __('contacts.link_person') }}</button>
                </div>
              </div>
            </template>
        </section>
      </div>

      {{-- Person picker: loads the gallery manifest lazily --}}
      <div x-show="personPicker" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closePersonPicker()">
        <div class="absolute inset-0 bg-black/60" @click="closePersonPicker()"></div>
        <div class="relative w-full max-w-lg rounded-lg bg-white dark:bg-gray-900 p-4 shadow-xl">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('contacts.link_person_heading') }}</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.link_person_hint') }}</p>
            <p x-show="! personSuggestions().length" x-cloak class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('contacts.link_person_none') }}</p>
            <div class="mt-3 grid max-h-80 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4">
                <template x-for="pp in personSuggestions()" :key="pp.id">
                    <button type="button" @click="linkPerson(pp)" class="group flex flex-col items-center focus:outline-none">
                        <span class="relative h-16 w-16 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700 group-hover:ring-gray-900 dark:group-hover:ring-gray-100 flex items-center justify-center text-sm font-semibold text-gray-500" x-init="$nextTick(() => personCoverUrl(pp))">
                            <img x-show="_personCovers[(pp.faces?.[0]?.cropRef)]" :src="_personCovers[(pp.faces?.[0]?.cropRef)]" class="h-full w-full object-cover">
                            <span x-show="! _personCovers[(pp.faces?.[0]?.cropRef)]" x-text="personInitials(pp)"></span>
                        </span>
                        <span class="mt-1 max-w-full truncate text-xs text-gray-700 dark:text-gray-300" x-text="pp.name || '{{ __('contacts.unnamed') }}'"></span>
                    </button>
                </template>
            </div>
            <div class="mt-4 flex justify-end">
                <x-button variant="secondary" type="button" @click="closePersonPicker()">{{ __('common.cancel') }}</x-button>
            </div>
        </div>
      </div>
    </template>
  </div>
</x-layouts.app>
