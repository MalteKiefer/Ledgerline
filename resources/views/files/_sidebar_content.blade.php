{{-- Files sidebar (shared by the desktop rail + mobile slide-over): switch
     between the browser, favourites, recent and the trash, plus storage usage. --}}
<nav class="space-y-1">
    <button type="button" @click="view = 'files'; selected = []; $store.nav.closeAll && $store.nav.closeAll()"
        :class="view === 'files' ? 'bg-accent/10 text-accent' : 'text-gray-700 dark:text-gray-300 hover:bg-accent/5'"
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#3b9fd6"><x-icon name="folder" class="h-4 w-4" /></span>
        <span>{{ __('files.all_files') }}</span>
    </button>
    <button type="button" @click="view = 'favorites'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
        :class="view === 'favorites' ? 'bg-accent/10 text-accent' : 'text-gray-700 dark:text-gray-300 hover:bg-accent/5'"
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#d9a441"><x-icon name="star" class="h-4 w-4" /></span>
        <span class="flex-1 text-left">{{ __('files.favorites') }}</span>
        <span x-show="favCount > 0" x-cloak x-text="favCount" class="rounded-full bg-gray-200 dark:bg-gray-700 px-1.5 text-xs text-gray-600 dark:text-gray-300"></span>
    </button>
    <button type="button" @click="view = 'recent'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
        :class="view === 'recent' ? 'bg-accent/10 text-accent' : 'text-gray-700 dark:text-gray-300 hover:bg-accent/5'"
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#9e70fa"><x-icon name="clock" class="h-4 w-4" /></span>
        <span>{{ __('files.recent') }}</span>
    </button>
    <button type="button" @click="view = 'shared'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
        :class="view === 'shared' ? 'bg-accent/10 text-accent' : 'text-gray-700 dark:text-gray-300 hover:bg-accent/5'"
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#7066f5"><x-icon name="share" class="h-4 w-4" /></span>
        <span class="flex-1 text-left">{{ __('files.shared') }}</span>
        <span x-show="sharedCount > 0" x-cloak x-text="sharedCount" class="rounded-full bg-gray-200 dark:bg-gray-700 px-1.5 text-xs text-gray-600 dark:text-gray-300"></span>
    </button>
    <button type="button" @click="view = 'trash'; selected = []; cwd = null; $store.nav.closeAll && $store.nav.closeAll()"
        @dragover.prevent="if (dragItem) $event.currentTarget.classList.add('ring-2','ring-red-400')"
        @dragleave="$event.currentTarget.classList.remove('ring-2','ring-red-400')"
        @drop.prevent="$event.currentTarget.classList.remove('ring-2','ring-red-400'); if (dragItem) { trashItem(dragItem); dragItem = null; }"
        :class="view === 'trash' ? 'bg-accent/10 text-accent' : 'text-gray-700 dark:text-gray-300 hover:bg-accent/5'"
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#6b7280"><x-icon name="trash" class="h-4 w-4" /></span>
        <span class="flex-1 text-left">{{ __('files.trash') }}</span>
        <span x-show="trashCount > 0" x-cloak x-text="trashCount" class="rounded-full bg-gray-200 dark:bg-gray-700 px-1.5 text-xs text-gray-600 dark:text-gray-300"></span>
    </button>
</nav>

{{-- Storage usage: show the bar + "used of total" when a quota is set,
     otherwise just how much the user is using (quota 0 = unlimited). --}}
<div x-show="usage" x-cloak class="border-t border-gray-100 dark:border-gray-800 pt-3">
    <template x-if="usage.quota > 0">
        <div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-full bg-accent" :style="'width:'+Math.min(100, Math.round((usage.used/usage.quota)*100))+'%'"></div>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="'{{ __('files.storage_used', ['used' => '__U__', 'total' => '__T__']) }}'.replace('__U__', fmtSize(usage.used)).replace('__T__', fmtSize(usage.quota))"></p>
        </div>
    </template>
    <template x-if="! usage.quota">
        <p class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <x-icon name="server" class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
            <span x-text="'{{ __('files.storage_used_only', ['used' => '__U__']) }}'.replace('__U__', fmtSize(usage.used))"></span>
        </p>
    </template>
</div>
