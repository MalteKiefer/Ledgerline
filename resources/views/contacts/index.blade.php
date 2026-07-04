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
        {{-- Sidebar --}}
        <aside class="w-full shrink-0 space-y-4 md:w-56">
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.ui.books') }}</h2>
                    <button @click="addBook()" class="text-gray-400 hover:text-gray-700" title="{{ __('contacts.ui.new_book') }}">+</button>
                </div>
                <ul class="mt-2 space-y-1 text-sm">
                    <li><button @click="book=''" :class="book===''?'font-semibold text-gray-900':'text-gray-600'">{{ __('contacts.ui.all_books') }}</button></li>
                    <template x-for="b in books" :key="b.id">
                        <li class="group flex items-center justify-between gap-1">
                            <button @click="book=b.id" :class="book===b.id?'font-semibold text-gray-900':'text-gray-600'" x-text="b.name" class="truncate text-left"></button>
                            <span class="hidden shrink-0 gap-1 group-hover:flex">
                                <button @click="renameBook(b)" class="text-gray-400 hover:text-gray-700" title="{{ __('contacts.ui.rename_book') }}">✎</button>
                                <button @click="deleteBook(b)" class="text-gray-400 hover:text-red-600" title="{{ __('contacts.ui.delete') }}">✕</button>
                            </span>
                        </li>
                    </template>
                </ul>
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.ui.groups') }}</h2>
                    <button @click="addGroup()" class="text-gray-400 hover:text-gray-700" title="{{ __('contacts.ui.new_group') }}">+</button>
                </div>
                <ul class="mt-2 space-y-1 text-sm">
                    <li><button @click="group=''" :class="group===''?'font-semibold text-gray-900':'text-gray-600'">{{ __('contacts.ui.all_groups') }}</button></li>
                    <template x-for="g in groups" :key="g.id">
                        <li class="group flex items-center justify-between gap-1">
                            <button @click="group=g.id" :class="group===g.id?'font-semibold text-gray-900':'text-gray-600'" x-text="g.name" class="truncate text-left"></button>
                            <button @click="deleteGroup(g)" class="hidden shrink-0 text-gray-400 hover:text-red-600 group-hover:block" title="{{ __('contacts.ui.delete') }}">✕</button>
                        </li>
                    </template>
                </ul>
            </div>
            <div class="space-y-2 border-t border-gray-100 pt-3">
                <a :href="cfg.exportUrl + (book ? ('?book='+book) : '')" class="block text-sm text-gray-600 hover:text-gray-900">{{ __('contacts.ui.export') }}</a>
                <label class="block cursor-pointer text-sm text-gray-600 hover:text-gray-900">
                    {{ __('contacts.ui.import') }}
                    <input type="file" accept=".vcf,text/vcard" class="hidden" @change="importFile($event)">
                </label>
            </div>
        </aside>

        {{-- Main --}}
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <input type="search" x-model.debounce.300ms="q" placeholder="{{ __('contacts.ui.search') }}"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
                <button @click="openEditor(null)" class="shrink-0 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('contacts.ui.new_contact') }}</button>
            </div>

            <template x-if="!loading && contacts.length===0">
                <p class="mt-8 text-center text-sm text-gray-500">{{ __('contacts.ui.empty') }}</p>
            </template>

            <ul class="mt-4 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                <template x-for="c in contacts" :key="c.id">
                    <li class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer" @click="openEditor(c.id)">
                        <div class="h-9 w-9 shrink-0 overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                            <template x-if="c.avatar"><img :src="c.avatar" alt="" class="h-full w-full object-cover"></template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900" x-text="c.fn || '—'"></p>
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
                        <label class="cursor-pointer text-xs text-gray-600 hover:text-gray-900" x-show="form.id">
                            {{ __('contacts.ui.avatar') }}
                            <input type="file" accept="image/*" class="hidden" @change="uploadAvatar($event)">
                        </label>
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
                    <button @click="form.emails.push({value:'',type:'home'})" class="text-xs text-gray-500 hover:text-gray-800">+ {{ __('contacts.ui.email') }}</button>
                    <template x-for="(p,i) in form.phones" :key="'p'+i">
                        <input x-model="form.phones[i].value" placeholder="{{ __('contacts.ui.phone') }}" class="w-full rounded-md border-gray-300 text-sm">
                    </template>
                    <button @click="form.phones.push({value:'',type:'cell'})" class="text-xs text-gray-500 hover:text-gray-800">+ {{ __('contacts.ui.phone') }}</button>
                    <label class="block text-xs text-gray-500">{{ __('contacts.ui.bday') }}
                        <input type="date" x-model="form.bday" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </label>
                    <div>
                        <span class="text-xs text-gray-500">{{ __('contacts.ui.anniversaries') }}</span>
                        <template x-for="(a,i) in form.anniversaries" :key="'a'+i">
                            <div class="mt-1 flex items-center gap-2">
                                <input type="date" x-model="form.anniversaries[i].date" class="rounded-md border-gray-300 text-sm">
                                <input x-model="form.anniversaries[i].label" placeholder="{{ __('contacts.ui.anniversary_label') }}" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                                <button type="button" @click="form.anniversaries.splice(i,1)" class="shrink-0 text-gray-400 hover:text-red-600">✕</button>
                            </div>
                        </template>
                        <button type="button" @click="form.anniversaries.push({date:'',label:''})" class="mt-1 text-xs text-gray-500 hover:text-gray-800">+ {{ __('contacts.ui.anniversary') }}</button>
                    </div>
                    <textarea x-model="form.note" placeholder="{{ __('contacts.ui.note') }}" rows="2" class="w-full rounded-md border-gray-300 text-sm"></textarea>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="g in groups" :key="g.id">
                            <label class="flex items-center gap-1 text-xs">
                                <input type="checkbox" :value="g.id" x-model="form.group_ids" class="rounded border-gray-300"><span x-text="g.name"></span>
                            </label>
                        </template>
                    </div>
                    <select x-model="form.book_id" class="w-full rounded-md border-gray-300 text-sm">
                        <template x-for="b in books" :key="b.id"><option :value="b.id" x-text="b.name"></option></template>
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
    </div>
</x-layouts.app>
