{{-- Contacts sidebar body (books, groups, import/export). Rendered inside both
     the desktop rail and the mobile slide-over; shares the contactsPage scope.
     Material Design 3 (msym icons, md-* tokens, m3-state hover). --}}
@php
    $navBase = 'm3-state flex w-full items-center gap-3 rounded-lg px-3 h-10 text-sm cursor-pointer transition';
    $navIdle = 'text-md-on-surface-var';
    $navOn = 'bg-md-selected text-md-primary font-semibold';
@endphp

<div class="flex flex-col gap-0.5">
    <button @click="book=''; group=''; favorites=false"
        :class="(book==='' && group==='' && !favorites) ? '{{ $navOn }}' : '{{ $navIdle }}'"
        class="{{ $navBase }}">
        <span class="msym text-xl">group</span><span class="truncate">{{ __('contacts.ui.all_books') }}</span>
    </button>
    <button @click="favorites = ! favorites"
        :class="favorites ? '{{ $navOn }}' : '{{ $navIdle }}'" class="{{ $navBase }}">
        <span class="msym text-xl" :class="favorites && 'msym-fill'">star</span>{{ __('contacts.ui.favorites') }}
    </button>
</div>

<div>
    <div class="flex items-center justify-between px-3 pt-4 pb-1">
        <h2 class="text-[11px] font-semibold uppercase tracking-wider text-md-on-surface-var">{{ __('contacts.ui.books') }}</h2>
        <x-m3.icon-button name="add" size="sm" tone="standard" tooltip="{{ __('contacts.ui.new_book') }}" @click="addBook()" />
    </div>
    <div class="flex flex-col gap-0.5">
        <template x-for="b in books" :key="b.id">
            <div class="group relative flex items-center">
                <button @click="book=(book===b.id?'':b.id)" :class="book===b.id ? '{{ $navOn }}' : '{{ $navIdle }}'" class="{{ $navBase }} flex-1 min-w-0">
                    <span class="msym text-xl">contacts</span><span class="truncate" x-text="b.name"></span>
                </button>
                <span class="absolute right-1 hidden shrink-0 gap-0.5 group-hover:flex" x-show="b.owned">
                    <x-m3.icon-button name="edit" size="sm" tooltip="{{ __('contacts.ui.rename_book') }}" @click.stop="renameBook(b)" />
                    <x-m3.icon-button name="delete" size="sm" tone="danger" tooltip="{{ __('contacts.ui.delete') }}" @click.stop="deleteBook(b)" />
                </span>
            </div>
        </template>
    </div>
</div>

<div>
    <div class="flex items-center justify-between px-3 pt-4 pb-1">
        <h2 class="text-[11px] font-semibold uppercase tracking-wider text-md-on-surface-var">{{ __('contacts.ui.groups') }}</h2>
        <x-m3.icon-button name="add" size="sm" tone="standard" tooltip="{{ __('contacts.ui.new_group') }}" @click="addGroup()" />
    </div>
    <div class="flex flex-col gap-0.5">
        <template x-for="g in groups" :key="g.id">
            <div class="group relative flex items-center">
                <button @click="group = (group===g.id ? '' : g.id)" :class="group===g.id ? '{{ $navOn }}' : '{{ $navIdle }}'" class="{{ $navBase }} flex-1 min-w-0">
                    <span class="msym text-xl">label</span><span class="truncate" x-text="g.name"></span>
                </button>
                <span class="absolute right-1 hidden group-hover:flex">
                    <x-m3.icon-button name="delete" size="sm" tone="danger" tooltip="{{ __('contacts.ui.delete') }}" @click.stop="deleteGroup(g)" />
                </span>
            </div>
        </template>
    </div>
</div>

<div class="mt-2 flex flex-col gap-0.5 border-t border-md-outline-variant pt-3">
    <label class="{{ $navBase }} {{ $navIdle }}" :class="importing && 'pointer-events-none opacity-60'">
        <span class="msym text-xl">upload</span>{{ __('contacts.ui.import') }}
        <input type="file" accept=".vcf,text/vcard" class="hidden" :disabled="importing" @change="importFile($event)">
    </label>
    <a :href="cfg.exportUrl + (book ? ('?book='+book) : '')" class="{{ $navBase }} {{ $navIdle }}"><span class="msym text-xl">download</span>{{ __('contacts.ui.export') }}</a>
    <a href="{{ route('contacts.duplicates') }}" class="{{ $navBase }} {{ $navIdle }}"><span class="msym text-xl">merge</span>{{ __('contacts.ui.duplicates') }}</a>
    <div x-show="importing" x-cloak class="flex items-center gap-2 px-3 py-1 text-sm text-md-on-surface-var">
        <span class="msym text-lg animate-spin">progress_activity</span><span>{{ __('contacts.ui.importing') }}</span>
    </div>
    <div x-show="importResult" x-cloak x-transition class="px-3 text-xs text-md-on-surface-var" x-text="importResult"></div>
</div>
