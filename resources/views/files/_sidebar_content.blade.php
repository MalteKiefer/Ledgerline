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

{{-- Shared folders: iOS grouped list section --}}
<div class="border-t border-gray-100 dark:border-gray-800 pt-3 space-y-2">
    {{-- Active shared context: "back to all files" row --}}
    <template x-if="activeShared !== null">
        <button type="button" @click="exitSharedFolder(); $store.nav.closeAll && $store.nav.closeAll()"
            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-accent/5">
            <x-icon name="arrow-uturn-left" class="h-4 w-4 shrink-0 text-gray-400" />
            <span>{{ __('files.folder_exit') }}</span>
        </button>
    </template>

    <p class="px-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('files.shared_folders') }}</p>

    {{-- Shared folder rows --}}
    <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
        <template x-if="sharedFolders.length === 0 && pendingFolderInvites.length === 0">
            <p class="px-3 py-2.5 text-xs text-gray-400 dark:text-gray-500">{{ __('files.shared_folders_empty') }}</p>
        </template>

        <template x-for="f in sharedFolders" :key="f.vaultId">
            <button type="button" @click="selectSharedFolder(f.vaultId); $store.nav.closeAll && $store.nav.closeAll()"
                :class="activeShared === f.vaultId ? 'bg-accent/10 text-accent' : 'text-gray-700 dark:text-gray-300 hover:bg-accent/5'"
                class="group flex w-full items-center gap-2 px-3 py-2.5 text-sm font-medium text-left">
                <span class="ll-chip flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#3b9fd6">
                    <x-icon name="folder" class="h-4 w-4" />
                </span>
                <span class="min-w-0 flex-1 truncate" x-text="f.name"></span>
                <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                      :class="f.role === 'manage' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' : (f.role === 'edit' ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400')"
                      x-text="f.role === 'manage' ? '{{ __('files.folder_role_manage') }}' : (f.role === 'edit' ? '{{ __('files.folder_role_edit') }}' : '{{ __('files.folder_role_read') }}')"></span>
                {{-- Share / members buttons (manage only, desktop hover) --}}
                <span x-show="f.role === 'manage'" class="flex items-center gap-1 md:hidden md:group-hover:flex" @click.stop>
                    <button type="button" @click.stop="openShareFolderDialog(f.vaultId)" :title="'{{ __('files.folder_share') }}'" class="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><x-icon name="user-plus" class="h-3.5 w-3.5" /></button>
                    <button type="button" @click.stop="openManageFolderMembers(f.vaultId)" :title="'{{ __('files.folder_members') }}'" class="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><x-icon name="users" class="h-3.5 w-3.5" /></button>
                </span>
            </button>
        </template>

        {{-- Pending invites --}}
        <template x-for="inv in pendingFolderInvites" :key="inv.member_id">
            <div class="flex items-center gap-2 px-3 py-2.5 text-sm">
                <span class="ll-chip flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#e2915a">
                    <x-icon name="envelope" class="h-4 w-4" />
                </span>
                <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-300 text-xs" x-text="inv.name || '{{ __('files.folder_pending_invite') }}'"></span>
                <button type="button" @click="acceptFolderInvite(inv)"
                    class="shrink-0 rounded-lg ll-accent px-2 py-1 text-xs font-medium">{{ __('files.folder_accept') }}</button>
            </div>
        </template>
    </div>

    {{-- Create new shared folder --}}
    <form class="flex items-center gap-1"
        @submit.prevent="let n = $el.querySelector('input').value.trim(); if (n) { createSharedFolder(n); $el.querySelector('input').value = ''; }">
        <input type="text" required placeholder="{{ __('files.shared_folder_new') }}"
            class="w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-accent focus:ring-accent">
        <button type="submit" :title="'{{ __('files.shared_folder_new') }}'" :aria-label="'{{ __('files.shared_folder_new') }}'"
            class="shrink-0 rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-700 dark:text-gray-300 hover:bg-accent/5"><x-icon name="folder-plus" class="h-5 w-5" /></button>
    </form>
</div>

{{-- New folder (creates in the current folder) --}}
<div x-show="view === 'files'" class="border-t border-gray-100 dark:border-gray-800 pt-3">
    <form class="flex items-center gap-1" @submit.prevent="mkdir(newFolderName); newFolderName = ''">
        <input type="text" x-model="newFolderName" required placeholder="{{ __('files.new_folder') }}"
            class="w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-accent focus:ring-accent">
        <button type="submit" title="{{ __('files.new_folder') }}" aria-label="{{ __('files.new_folder') }}"
            class="shrink-0 rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-700 dark:text-gray-300 hover:bg-accent/5"><x-icon name="folder-plus" class="h-5 w-5" /></button>
    </form>
</div>

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
