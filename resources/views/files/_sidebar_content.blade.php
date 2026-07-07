{{-- Files sidebar (shared by the desktop rail + mobile slide-over): switch
     between the browser, favourites, recent and the trash, plus storage usage. --}}
<nav class="space-y-1">
    <button type="button" @click="view = 'files'; selected = []; $store.nav.closeAll && $store.nav.closeAll()"
        :class="view === 'files' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
        <x-icon name="folder" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
        <span>{{ __('files.all_files') }}</span>
    </button>
    <button type="button" @click="view = 'favorites'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
        :class="view === 'favorites' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
        <x-icon name="star" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
        <span class="flex-1 text-left">{{ __('files.favorites') }}</span>
        <span x-show="favCount > 0" x-cloak x-text="favCount" class="rounded-full bg-gray-200 dark:bg-gray-700 px-1.5 text-xs text-gray-600 dark:text-gray-300"></span>
    </button>
    <button type="button" @click="view = 'recent'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
        :class="view === 'recent' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
        <x-icon name="clock" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
        <span>{{ __('files.recent') }}</span>
    </button>
    <button type="button" @click="view = 'trash'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
        @dragover.prevent="if (dragItem) $event.currentTarget.classList.add('ring-2','ring-red-400')"
        @dragleave="$event.currentTarget.classList.remove('ring-2','ring-red-400')"
        @drop.prevent="$event.currentTarget.classList.remove('ring-2','ring-red-400'); if (dragItem) { trashItem(dragItem); dragItem = null; }"
        :class="view === 'trash' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
        <x-icon name="trash" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
        <span class="flex-1 text-left">{{ __('files.trash') }}</span>
        <span x-show="trashCount > 0" x-cloak x-text="trashCount" class="rounded-full bg-gray-200 dark:bg-gray-700 px-1.5 text-xs text-gray-600 dark:text-gray-300"></span>
    </button>
</nav>

{{-- New folder (creates in the current folder) --}}
<div x-show="view === 'files'" class="border-t border-gray-100 dark:border-gray-800 pt-3">
    <form class="flex items-center gap-1" @submit.prevent="mkdir(newFolderName); newFolderName = ''">
        <input type="text" x-model="newFolderName" required placeholder="{{ __('files.new_folder') }}"
            class="w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
        <button type="submit" title="{{ __('files.new_folder') }}" aria-label="{{ __('files.new_folder') }}"
            class="shrink-0 rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="folder-plus" class="h-5 w-5" /></button>
    </form>
</div>

{{-- Storage usage --}}
<div x-show="usage.quota > 0" x-cloak class="border-t border-gray-100 dark:border-gray-800 pt-3">
    <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
        <div class="h-full bg-gray-700" :style="'width:'+Math.min(100, Math.round((usage.used/usage.quota)*100))+'%'"></div>
    </div>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="'{{ __('files.storage_used', ['used' => '__U__', 'total' => '__T__']) }}'.replace('__U__', fmtSize(usage.used)).replace('__T__', fmtSize(usage.quota))"></p>
</div>
