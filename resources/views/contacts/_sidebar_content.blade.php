{{-- Contacts sidebar body (books, groups, import/export). Rendered inside both
     the desktop rail and the mobile slide-over; shares the contactsPage scope. --}}
<div>
    <div class="flex items-center justify-between">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.ui.books') }}</h2>
        <button @click="addBook()" class="inline-flex min-h-9 min-w-9 items-center justify-center text-gray-400 hover:text-gray-700" title="{{ __('contacts.ui.new_book') }}"><x-icon name="plus" class="h-4 w-4" /></button>
    </div>
    <ul class="mt-2 space-y-1 text-sm">
        <li><button @click="book=''" :class="book===''?'font-semibold text-gray-900':'text-gray-600'">{{ __('contacts.ui.all_books') }}</button></li>
        <template x-for="b in books" :key="b.id">
            <li class="group flex items-center justify-between gap-1">
                <button @click="book=b.id" :class="book===b.id?'font-semibold text-gray-900':'text-gray-600'" x-text="b.name" class="truncate text-left"></button>
                <span class="flex shrink-0 gap-1 md:hidden md:group-hover:flex" x-show="b.owned">
                    <button @click="openShare('address-books', b.id, b.name)" class="inline-flex min-h-9 min-w-9 items-center justify-center text-gray-400 hover:text-gray-700" title="{{ __('shares.share') }}"><x-icon name="share" class="h-4 w-4" /></button>
                    <button @click="renameBook(b)" class="inline-flex min-h-9 min-w-9 items-center justify-center text-gray-400 hover:text-gray-700" title="{{ __('contacts.ui.rename_book') }}"><x-icon name="pencil" class="h-4 w-4" /></button>
                    <button @click="deleteBook(b)" class="inline-flex min-h-9 min-w-9 items-center justify-center text-gray-400 hover:text-red-600" title="{{ __('contacts.ui.delete') }}"><x-icon name="x-mark" class="h-4 w-4" /></button>
                </span>
            </li>
        </template>
    </ul>
</div>
<div>
    <div class="flex items-center justify-between">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('contacts.ui.groups') }}</h2>
        <button @click="addGroup()" class="inline-flex min-h-9 min-w-9 items-center justify-center text-gray-400 hover:text-gray-700" title="{{ __('contacts.ui.new_group') }}"><x-icon name="plus" class="h-4 w-4" /></button>
    </div>
    <ul class="mt-2 space-y-1 text-sm">
        <li><button @click="group=''" :class="group===''?'font-semibold text-gray-900':'text-gray-600'">{{ __('contacts.ui.all_groups') }}</button></li>
        <template x-for="g in groups" :key="g.id">
            <li class="group flex items-center justify-between gap-1">
                <button @click="group = (group===g.id ? '' : g.id)" :class="group===g.id?'font-semibold text-gray-900':'text-gray-600'" x-text="g.name" class="truncate text-left"></button>
                <button @click="deleteGroup(g)" class="inline-flex min-h-9 min-w-9 shrink-0 items-center justify-center text-gray-400 hover:text-red-600 md:hidden md:group-hover:inline-flex" title="{{ __('contacts.ui.delete') }}"><x-icon name="x-mark" class="h-4 w-4" /></button>
            </li>
        </template>
    </ul>
</div>
<div class="space-y-2 border-t border-gray-100 pt-3">
    <div class="flex gap-2">
        <label class="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            :class="importing && 'pointer-events-none opacity-60'" title="{{ __('contacts.ui.import') }}">
            <x-icon name="arrow-up-tray" class="h-4 w-4" />
            <span>{{ __('contacts.ui.import') }}</span>
            <input type="file" accept=".vcf,text/vcard" class="hidden" :disabled="importing" @change="importFile($event)">
        </label>
        <a :href="cfg.exportUrl + (book ? ('?book='+book) : '')"
            class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            title="{{ __('contacts.ui.export') }}">
            <x-icon name="document-arrow-down" class="h-4 w-4" />
            <span>{{ __('contacts.ui.export') }}</span>
        </a>
    </div>
    <a href="{{ route('contacts.duplicates') }}"
        class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
        <x-icon name="arrows-pointing-in" class="h-4 w-4" />
        <span>{{ __('contacts.ui.duplicates') }}</span>
    </a>
    <div x-show="importing" x-cloak class="flex items-center gap-2 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">
        <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />
        <span>{{ __('contacts.ui.importing') }}</span>
    </div>
    <div x-show="importResult" x-cloak x-transition
        class="rounded-md bg-green-50 px-3 py-2 text-xs text-green-800" x-text="importResult"></div>
</div>
