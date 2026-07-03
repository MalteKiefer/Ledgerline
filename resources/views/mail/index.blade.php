<x-layouts.app :title="__('mail.title')">
  <div x-data="vaultMail({
        stale: @js(__('mail.stale')),
        saveFailed: @js(__('mail.save_failed')),
        connectFailed: @js(__('mail.connect_failed')),
        blobBase: '{{ url('/vault/blobs') }}',
        folderNames: @js([
            'inbox' => __('mail.folder_inbox'),
            'all' => __('mail.folder_all'),
            'archive' => __('mail.folder_archive'),
            'drafts' => __('mail.folder_drafts'),
            'sent' => __('mail.folder_sent'),
            'junk' => __('mail.folder_junk'),
            'trash' => __('mail.folder_trash'),
            'important' => __('mail.folder_important'),
            'flagged' => __('mail.folder_flagged'),
        ]),
     })">

    {{-- Vault not set up / locked: only the gate. --}}
    <template x-if="state === 'unconfigured' || state === 'locked'">
        <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 bg-white p-8 text-center shadow-sm">
            <x-icon name="lock-closed" class="mx-auto h-10 w-10 text-gray-400" />
            <p class="mt-4 text-sm text-gray-600" x-text="state === 'locked' ? @js(__('mail.locked_notice')) : @js(__('mail.unconfigured_notice'))"></p>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('vault-panel'))"
                class="mt-5 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                x-text="state === 'locked' ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></button>
        </div>
    </template>

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">{{ __('mail.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div>
        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ __('mail.title') }}</h1>
                <p class="mt-1 text-sm text-gray-600">{{ __('mail.subtitle') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" x-show="manifest.accounts.length" @click="refreshAll()" :disabled="refreshingAll || busyId" title="{{ __('mail.refresh_all') }}" aria-label="{{ __('mail.refresh_all') }}"
                    class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50 disabled:opacity-40"><x-icon name="arrow-path" class="h-5 w-5" ::class="refreshingAll ? 'animate-spin' : ''" /></button>
                <button type="button" @click="openAdd()" title="{{ __('mail.add_account') }}" aria-label="{{ __('mail.add_account') }}"
                    class="rounded-md bg-gray-800 p-2 text-white hover:bg-gray-700"><x-icon name="plus" class="h-5 w-5" /></button>
            </div>
        </div>

        <p x-show="error" x-cloak class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800" x-text="error"></p>

        <template x-if="manifest.accounts.length === 0">
            <p class="mt-8 rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm">{{ __('mail.empty') }}</p>
        </template>

        {{-- Account cards --}}
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <template x-for="a in sortedAccounts" :key="a.id">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" x-data="{ menu: false, open: false }">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-semibold text-gray-900" x-text="a.name"></h2>
                            <p class="truncate text-xs text-gray-500"><span x-text="a.username"></span> · <span x-text="a.host"></span>:<span x-text="a.port"></span></p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <button type="button" @click="openReader(a)" title="{{ __('mail.open_reader') }}" aria-label="{{ __('mail.open_reader') }}"
                                class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"><x-icon name="envelope" class="h-4 w-4" /></button>
                            <button type="button" @click="refresh(a)" :disabled="busyId" title="{{ __('mail.refresh') }}" aria-label="{{ __('mail.refresh') }}"
                                class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-40">
                                <x-icon name="arrow-path" class="h-4 w-4" ::class="busyId === a.id ? 'animate-spin' : ''" />
                            </button>
                            <div class="relative" @click.outside="menu = false">
                                <button type="button" @click="menu = ! menu" class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><x-icon name="ellipsis" /></button>
                                <div x-show="menu" x-cloak class="absolute right-0 z-20 mt-1 w-40 rounded-md border border-gray-200 bg-white py-1 text-left text-sm shadow-lg">
                                    <button type="button" @click="openEdit(a); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="pencil" />{{ __('mail.edit') }}</button>
                                    <button type="button" @click="confirmDelete(a); menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 hover:bg-gray-50"><x-icon name="trash" />{{ __('mail.delete') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p x-show="errors[a.id]" x-cloak class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="errors[a.id]"></p>

                    {{-- Stats (from background-synced cache, falling back to the manifest) --}}
                    <template x-if="accountStats(a)">
                        <div class="mt-4" x-data="{ get s() { return accountStats(a); } }">
                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div class="rounded-md bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('mail.stat_total') }}</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900" x-text="s.total"></div>
                                </div>
                                <div class="rounded-md bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('mail.stat_unseen') }}</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900" x-text="s.unseen"></div>
                                </div>
                                <div class="rounded-md bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('mail.stat_folders') }}</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900" x-text="(s.folders ?? []).length"></div>
                                </div>
                            </div>

                            {{-- Quota --}}
                            <div class="mt-3">
                                <div class="flex items-center justify-between text-xs text-gray-500">
                                    <span>{{ __('mail.stat_quota') }}</span>
                                    <span x-show="s.quotaLimit" x-text="@js(__('mail.quota_used_of', ['used' => '%u', 'limit' => '%l'])).replace('%u', fmtBytes(s.quotaUsed)).replace('%l', fmtBytes(s.quotaLimit))"></span>
                                    <span x-show="! s.quotaLimit">{{ __('mail.quota_unavailable') }}</span>
                                </div>
                                <div x-show="s.quotaLimit" class="mt-1 h-2 overflow-hidden rounded bg-gray-100">
                                    <div class="h-2 bg-gray-800" :style="`width: ${quotaPct(s)}%`"></div>
                                </div>
                            </div>

                            {{-- Folders --}}
                            <button type="button" @click="open = ! open" class="mt-3 text-xs text-gray-500 hover:text-gray-700" x-text="open ? '▾ {{ __('mail.stat_folders') }}' : '▸ {{ __('mail.stat_folders') }}'"></button>
                            <ul x-show="open" x-cloak class="mt-2 max-h-48 space-y-1 overflow-y-auto text-xs">
                                <template x-for="f in sortedFolders(s.folders)" :key="f.name">
                                    <li class="flex items-center justify-between gap-2 border-b border-gray-50 py-1">
                                        <span class="min-w-0 truncate text-gray-700" x-text="f.name"></span>
                                        <span class="shrink-0 text-gray-400"><span x-text="f.total"></span> · <span x-text="f.unseen"></span></span>
                                    </li>
                                </template>
                            </ul>

                            <p class="mt-3 text-xs text-gray-400" x-text="@js(__('mail.fetched_at', ['time' => '%t'])).replace('%t', fmtDateTime(s.fetchedAt))"></p>
                        </div>
                    </template>
                    <template x-if="! accountStats(a)">
                        <p class="mt-4 text-sm text-gray-500">{{ __('mail.never_fetched') }}</p>
                    </template>
                </div>
            </template>
        </div>
      </div>
    </template>

    {{-- Add / edit modal --}}
    <template x-teleport="body">
        <div x-show="dialogOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="dialogOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="dialogOpen = false"></div>
            <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900" x-text="editingId ? @js(__('mail.edit_title')) : @js(__('mail.add_title'))"></h3>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_name') }}</label>
                        <input type="text" x-model="form.name" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_host') }}</label>
                            <input type="text" x-model="form.host" placeholder="imap.example.com" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_port') }}</label>
                            <input type="number" min="1" max="65535" x-model="form.port" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_encryption') }}</label>
                        <select x-model="form.encryption" @change="form.port = form.encryption === 'starttls' ? 143 : 993"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            <option value="ssl">{{ __('mail.encryption_ssl') }}</option>
                            <option value="starttls">{{ __('mail.encryption_starttls') }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_username') }}</label>
                            <input type="text" x-model="form.username" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('mail.field_password') }}</label>
                            <input type="password" x-model="form.password" autocomplete="new-password" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" x-model="form.validateCert" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                        {{ __('mail.field_validate_cert') }}
                    </label>
                    <p class="text-xs text-gray-400">{{ __('mail.security_note') }}</p>
                    <p x-show="error" x-cloak class="text-xs text-red-600" x-text="error"></p>
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="dialogOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="saveAccount()" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('mail.save') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Delete confirm --}}
    <template x-teleport="body">
        <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="deleteOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="deleteOpen = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ __('mail.confirm_delete') }}</p>
                <div class="mt-5 flex justify-end gap-3">
                    <button type="button" @click="deleteOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="applyDelete()" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('mail.delete') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Reader overlay --}}
    <template x-teleport="body">
        <div x-show="reader.open" x-cloak class="fixed inset-0 z-[60] flex flex-col bg-white" @keydown.escape.window="reader.current ? (reader.current = null) : closeReader()">
            {{-- Busy overlay: blocks interaction while a message loads or an action runs --}}
            <div x-show="reader.busy" x-cloak class="absolute inset-0 z-[68] flex items-center justify-center bg-white/60">
                <x-icon name="arrow-path" class="h-8 w-8 animate-spin text-gray-500" />
            </div>
            {{-- Top bar --}}
            <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-2">
                <button type="button" @click="closeReader()" title="{{ __('mail.close') }}" class="rounded p-1.5 text-gray-500 hover:bg-gray-100"><x-icon name="x-mark" class="h-5 w-5" /></button>
                <span class="truncate text-sm font-semibold text-gray-900" x-text="reader.account?.name"></span>
            </div>

            <p x-show="reader.error" x-cloak class="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700" x-text="reader.error"></p>

            {{-- Two-column: folders left, messages/message right --}}
            <div class="flex min-h-0 flex-1">
                {{-- Folder sidebar --}}
                <aside class="flex w-44 shrink-0 flex-col border-r border-gray-200 md:w-64">
                    {{-- New folder --}}
                    <form class="flex items-center gap-1 border-b border-gray-100 p-2" @submit.prevent="createFolder($refs.newFolder.value); $refs.newFolder.value = ''">
                        <input type="text" x-ref="newFolder" required placeholder="{{ __('mail.new_folder') }}"
                            class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <button type="submit" title="{{ __('mail.new_folder') }}" aria-label="{{ __('mail.new_folder') }}" :disabled="reader.busy"
                            class="shrink-0 rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-50"><x-icon name="folder-plus" class="h-4 w-4" /></button>
                    </form>
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <div x-show="reader.foldersLoading && readerFolders().length === 0" class="flex items-center gap-2 px-3 py-4 text-xs text-gray-400">
                            <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />{{ __('mail.loading') }}
                        </div>
                        <template x-for="f in orderedFolders()" :key="f.path">
                            <div>
                                {{-- Non-selectable container (e.g. Gmail "[Gmail]"): just a label --}}
                                <div x-show="! f.selectable" class="truncate px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400"
                                    :style="`padding-left: ${0.75 + folderDepth(f) * 0.75}rem`" x-text="folderLabel(f)"></div>
                                {{-- Selectable folder — standard folders get a role icon; custom none --}}
                                <button type="button" x-show="f.selectable" @click="openFolder(f.path)"
                                    class="flex w-full items-center justify-between gap-2 py-2 pr-3 text-left text-sm hover:bg-gray-50"
                                    :style="`padding-left: ${0.75 + folderDepth(f) * 0.75}rem`"
                                    :class="f.path === reader.folderPath ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700'">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <svg x-show="folderIconPath(f)" class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="folderIconPath(f)" /></svg>
                                        <span class="truncate" x-text="folderLabel(f)"></span>
                                    </span>
                                    <span class="flex shrink-0 items-center gap-1 text-xs text-gray-400">
                                        <span x-show="f.unseen" class="rounded-full bg-blue-500 px-1.5 py-0.5 text-[10px] font-medium text-white" x-text="f.unseen"></span>
                                        <span x-text="f.total"></span>
                                    </span>
                                </button>
                            </div>
                        </template>
                    </div>
                </aside>

                {{-- Right pane --}}
                <div class="flex min-h-0 flex-1 flex-col">
            {{-- Message list --}}
            <div x-show="! reader.current" class="flex min-h-0 flex-1 flex-col">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-1.5 text-xs text-gray-500">
                    <span class="flex min-w-0 items-center gap-2">
                        <input type="checkbox" @change="toggleSelectAll()" :checked="allSelected" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500" aria-label="{{ __('mail.select_all') }}">
                        <span class="truncate font-medium text-gray-700" x-text="reader.folderPath"></span>
                    </span>
                    <span class="flex shrink-0 items-center gap-3">
                        <button type="button" x-show="isTrashFolder() && reader.total" @click="reader.emptyChoiceOpen = true" :disabled="reader.busy"
                            class="inline-flex items-center gap-1 rounded-md border border-red-300 px-2 py-0.5 text-red-700 hover:bg-red-50"><x-icon name="trash" class="h-3.5 w-3.5" />{{ __('mail.empty_folder') }}</button>
                        <button type="button" @click="refreshCurrentFolder()" :disabled="reader.loading" title="{{ __('mail.refresh') }}" aria-label="{{ __('mail.refresh') }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                            <x-icon name="arrow-path" class="h-4 w-4" ::class="reader.loading ? 'animate-spin' : ''" />
                        </button>
                        <span x-text="`${reader.messages.length} / ${reader.total}`"></span>
                        <button type="button" @click="toggleSort()" class="inline-flex items-center gap-1 hover:text-gray-700" title="{{ __('mail.msg_date') }}">
                            {{ __('mail.msg_date') }}
                            <x-icon name="chevron-down" x-show="reader.sortDir === 'desc'" class="h-3.5 w-3.5" />
                            <x-icon name="chevron-up" x-show="reader.sortDir === 'asc'" x-cloak class="h-3.5 w-3.5" />
                        </button>
                    </span>
                </div>

                {{-- Bulk action bar (icon-only, tooltips) --}}
                <div x-show="reader.selected.length" x-cloak class="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2 text-xs">
                    <span class="font-medium text-gray-700"><span x-text="reader.selected.length"></span> {{ __('mail.selected') }}</span>
                    <button type="button" @click="bulkAction('trash')" :disabled="reader.busy" title="{{ __('mail.action_trash') }}" aria-label="{{ __('mail.action_trash') }}" class="rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-100"><x-icon name="trash" class="h-4 w-4" /></button>
                    <button type="button" @click="bulkAction('delete')" :disabled="reader.busy" title="{{ __('mail.action_delete') }}" aria-label="{{ __('mail.action_delete') }}" class="rounded-md border border-red-300 p-1.5 text-red-700 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /></button>
                    <select @change="if ($event.target.value) { bulkAction('move', $event.target.value); $event.target.value = '' }" title="{{ __('mail.move_to_folder') }}" class="rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <option value="">{{ __('mail.move_to_folder') }}</option>
                        <template x-for="f in moveFolders()" :key="f.path"><option :value="f.path" x-text="folderLabel(f)"></option></template>
                    </select>
                    <button type="button" x-show="otherAccounts().length" @click="openTransfer()" :disabled="reader.busy" title="{{ __('mail.move_to_account') }}" aria-label="{{ __('mail.move_to_account') }}" class="rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-100"><x-icon name="share" class="h-4 w-4" /></button>
                    <button type="button" @click="bulkAction('seen')" :disabled="reader.busy" title="{{ __('mail.mark_read') }}" aria-label="{{ __('mail.mark_read') }}" class="rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-100"><x-icon name="envelope-open" class="h-4 w-4" /></button>
                    <button type="button" @click="bulkAction('unseen')" :disabled="reader.busy" title="{{ __('mail.mark_unread') }}" aria-label="{{ __('mail.mark_unread') }}" class="rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-100"><x-icon name="envelope" class="h-4 w-4" /></button>
                    <button type="button" @click="reader.selected = []" title="{{ __('mail.clear_selection') }}" aria-label="{{ __('mail.clear_selection') }}" class="ml-auto rounded-md p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"><x-icon name="x-mark" class="h-4 w-4" /></button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto">
                    <div x-show="reader.loading" class="flex items-center justify-center gap-2 px-4 py-10 text-sm text-gray-500">
                        <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />{{ __('mail.loading') }}
                    </div>
                    <p x-show="! reader.loading && reader.messages.length === 0" class="px-4 py-10 text-center text-sm text-gray-500">{{ __('mail.list_empty') }}</p>
                    <ul class="divide-y divide-gray-100">
                        <template x-for="m in sortedMessages()" :key="m.uid">
                            <li class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-gray-50" @click="openMsg(m.uid)">
                                <input type="checkbox" :value="m.uid" x-model.number="reader.selected" @click.stop class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                                <span class="h-2 w-2 shrink-0 rounded-full" :class="m.seen ? 'bg-transparent' : 'bg-blue-500'"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="truncate text-sm" :class="m.seen ? 'text-gray-700' : 'font-semibold text-gray-900'" x-text="fmtAddress(m.from) || '—'"></span>
                                    <span class="block truncate text-sm" :class="m.seen ? 'text-gray-600' : 'text-gray-900'" x-text="m.subject || @js(__('mail.no_subject'))"></span>
                                </span>
                                <span class="shrink-0 text-xs text-gray-400" x-text="fmtDateTime(m.date)"></span>
                            </li>
                        </template>
                    </ul>
                    {{-- Infinite scroll: load the next page when the sentinel comes into view. --}}
                    <div x-show="hasMoreMessages" x-intersect.margin.600px="loadMore()" class="h-10"></div>
                    <div x-show="reader.loadingMore" x-cloak class="flex items-center justify-center gap-2 py-3 text-xs text-gray-400">
                        <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />{{ __('mail.loading') }}
                    </div>
                </div>
            </div>

            {{-- Message view (x-if so children aren't evaluated when current is null) --}}
            <template x-if="reader.current">
              <div class="flex min-h-0 flex-1 flex-col">
                {{-- Actions --}}
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-2">
                    <button type="button" @click="reader.current = null" title="{{ __('mail.back_to_list') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="chevron-left" class="h-4 w-4" /></button>
                    <button type="button" @click="reader.deleteChoiceOpen = true" :disabled="reader.busy" title="{{ __('mail.delete') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="trash" class="h-4 w-4" /></button>
                    <button type="button" @click="reader.headersOpen = true" title="{{ __('mail.show_headers') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="bars-3" class="h-4 w-4" /></button>
                    <select @change="if ($event.target.value) { msgAction('move', $event.target.value); $event.target.value = '' }" class="rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <option value="">{{ __('mail.move_to_folder') }}</option>
                        <template x-for="f in moveFolders()" :key="f.path"><option :value="f.path" x-text="folderLabel(f)"></option></template>
                    </select>
                    <button type="button" x-show="otherAccounts().length" @click="openTransfer()" :disabled="reader.busy" title="{{ __('mail.move_to_account') }}" aria-label="{{ __('mail.move_to_account') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="share" class="h-4 w-4" /></button>
                    <button type="button" @click="msgAction(reader.current.seen ? 'unseen' : 'seen')" :disabled="reader.busy"
                        :title="reader.current.seen ? @js(__('mail.mark_unread')) : @js(__('mail.mark_read'))"
                        :aria-label="reader.current.seen ? @js(__('mail.mark_unread')) : @js(__('mail.mark_read'))"
                        class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50">
                        <x-icon name="envelope" x-show="reader.current.seen" />
                        <x-icon name="envelope-open" x-show="! reader.current.seen" x-cloak />
                    </button>
                    <button type="button" @click="printMsg()" title="{{ __('mail.print') }}" aria-label="{{ __('mail.print') }}" class="ml-auto rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="printer" class="h-4 w-4" /></button>
                </div>

                {{-- Headers --}}
                <div class="border-b border-gray-100 px-6 py-4">
                    <div class="flex items-center gap-2">
                        <h1 class="min-w-0 text-lg font-semibold text-gray-900" x-text="reader.current.subject || @js(__('mail.no_subject'))"></h1>
                        <x-icon name="paperclip" x-show="(reader.current.attachments ?? []).length" x-cloak class="h-4 w-4 shrink-0 text-gray-500" title="{{ __('mail.attachments') }}" />
                    </div>
                    <dl class="mt-2 space-y-0.5 text-xs text-gray-500">
                        <div><dt class="inline font-medium">{{ __('mail.msg_from') }}:</dt> <dd class="inline" x-text="fmtAddress(reader.current.from)"></dd></div>
                        <div x-show="(reader.current.to ?? []).length"><dt class="inline font-medium">{{ __('mail.msg_to') }}:</dt> <dd class="inline" x-text="(reader.current.to ?? []).map(fmtAddress).join(', ')"></dd></div>
                        <div><dt class="inline font-medium">{{ __('mail.msg_date') }}:</dt> <dd class="inline" x-text="fmtDateTime(reader.current.date)"></dd></div>
                    </dl>
                    {{-- Attachments --}}
                    <div x-show="(reader.current.attachments ?? []).length" x-cloak class="mt-3 flex flex-wrap gap-2">
                        <template x-for="att in reader.current.attachments" :key="att.id">
                            <span class="inline-flex items-center overflow-hidden rounded-md border border-gray-300 text-xs text-gray-700">
                                <button type="button" x-show="attachmentPreviewable(att)" @click="openAttachment(att)" title="{{ __('mail.view_attachment') }}" aria-label="{{ __('mail.view_attachment') }}"
                                    class="max-w-[16rem] truncate px-2 py-1 hover:bg-gray-50" x-text="att.name"></button>
                                <span x-show="! attachmentPreviewable(att)" class="max-w-[16rem] truncate px-2 py-1" x-text="att.name"></span>
                                <button type="button" x-show="attachmentPreviewable(att)" @click="openAttachment(att)" title="{{ __('mail.view_attachment') }}" aria-label="{{ __('mail.view_attachment') }}"
                                    class="border-l border-gray-300 p-1.5 hover:bg-gray-50"><x-icon name="eye" class="h-3.5 w-3.5" /></button>
                                <button type="button" @click="downloadAttachment(att)" title="{{ __('mail.download') }}" aria-label="{{ __('mail.download') }}"
                                    class="border-l border-gray-300 p-1.5 hover:bg-gray-50"><x-icon name="arrow-down-tray" class="h-3.5 w-3.5" /></button>
                                <button type="button" @click="openSaveAttachment(att)" title="{{ __('mail.save_to_files') }}" aria-label="{{ __('mail.save_to_files') }}"
                                    class="border-l border-gray-300 p-1.5 hover:bg-gray-50"><x-icon name="arrow-up-tray" class="h-3.5 w-3.5" /></button>
                            </span>
                        </template>
                    </div>
                </div>

                {{-- Remote images banner --}}
                <div x-show="messageHasBlockedImages && ! reader.imagesAllowed" x-cloak class="flex items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-6 py-2 text-xs text-amber-800">
                    <span>{{ __('mail.images_blocked') }}</span>
                    <button type="button" @click="reader.imagesAllowed = true" class="shrink-0 rounded-md border border-amber-300 px-2 py-1 font-medium hover:bg-amber-100">{{ __('mail.load_images') }}</button>
                </div>

                {{-- Body (sandboxed) --}}
                <div class="min-h-0 flex-1 overflow-hidden bg-gray-50 p-2">
                    <iframe :srcdoc="messageSrcdoc()" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" class="h-full w-full rounded border border-gray-200 bg-white"></iframe>
                </div>
              </div>
            </template>
                </div>{{-- /right pane --}}
            </div>{{-- /two-column --}}

            {{-- Delete choice: trash or permanent --}}
            <div x-show="reader.deleteChoiceOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="reader.deleteChoiceOpen = false">
                <div class="absolute inset-0 bg-gray-900/40" @click="reader.deleteChoiceOpen = false"></div>
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('mail.delete_question') }}</p>
                    <div class="mt-5 flex flex-wrap justify-end gap-3">
                        <button type="button" @click="reader.deleteChoiceOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                        <button type="button" @click="reader.deleteChoiceOpen = false; msgAction('trash')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('mail.action_trash') }}</button>
                        <button type="button" @click="reader.deleteChoiceOpen = false; msgAction('delete')" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('mail.action_delete') }}</button>
                    </div>
                </div>
            </div>

            {{-- Empty folder confirm --}}
            <div x-show="reader.emptyChoiceOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="reader.emptyChoiceOpen = false">
                <div class="absolute inset-0 bg-gray-900/40" @click="reader.emptyChoiceOpen = false"></div>
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('mail.empty_folder_confirm') }}</p>
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" @click="reader.emptyChoiceOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                        <button type="button" @click="reader.emptyChoiceOpen = false; emptyCurrentFolder()" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">{{ __('mail.empty_folder') }}</button>
                    </div>
                </div>
            </div>

            {{-- Full headers --}}
            <div x-show="reader.headersOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="reader.headersOpen = false">
                <div class="absolute inset-0 bg-gray-900/40" @click="reader.headersOpen = false"></div>
                <div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">{{ __('mail.headers_title') }}</h3>
                        <button type="button" @click="reader.headersOpen = false" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><x-icon name="x-mark" class="h-5 w-5" /></button>
                    </div>
                    <pre class="min-h-0 flex-1 overflow-auto whitespace-pre-wrap break-words px-6 py-4 text-xs text-gray-700" x-text="reader.current?.rawHeaders"></pre>
                </div>
            </div>

            {{-- Transfer to another account: pick account + target folder --}}
            <div x-show="reader.transferOpen" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="reader.transferOpen = false">
                <div class="absolute inset-0 bg-gray-900/40" @click="reader.transferOpen = false"></div>
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('mail.move_to_account') }}</h3>
                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('mail.transfer_account') }}</label>
                            <select x-model="reader.transferAccount" @change="onTransferAccountChange()" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <template x-for="a in otherAccounts()" :key="a.id"><option :value="a.id" x-text="a.name"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ __('mail.transfer_folder') }}</label>
                            <select x-model="reader.transferFolder" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                                <template x-for="f in transferFolders()" :key="f.path"><option :value="f.path" x-text="f.name"></option></template>
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" @click="reader.transferOpen = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                        <button type="button" @click="confirmTransfer()" :disabled="reader.busy" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('mail.transfer_move') }}</button>
                    </div>
                </div>
            </div>

            {{-- Save attachment into the Files vault --}}
            <div x-show="reader.saveAtt.open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="reader.saveAtt.open = false">
                <div class="absolute inset-0 bg-gray-900/40" @click="reader.saveAtt.open = false"></div>
                <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('mail.save_to_files_title') }}</h3>
                    <p class="mt-1 truncate text-xs text-gray-500" x-text="reader.saveAtt.att?.name"></p>
                    <div class="mt-4">
                        <label class="block text-xs font-medium text-gray-700">{{ __('mail.dest_folder') }}</label>
                        <select x-model="reader.saveAtt.folder" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            <option value="">{{ __('mail.root_folder') }}</option>
                            <template x-for="f in reader.filesFolders" :key="f.id"><option :value="f.id" x-text="f.label"></option></template>
                        </select>
                    </div>
                    <p x-show="reader.saveAtt.error" x-cloak class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" x-text="reader.saveAtt.error"></p>
                    <p x-show="reader.saveAtt.done" x-cloak class="mt-3 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-700">{{ __('mail.saved_to_files') }}</p>
                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" @click="reader.saveAtt.open = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                        <button type="button" @click="saveAttachmentToFiles()" :disabled="reader.saveAtt.busy || reader.saveAtt.done" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('mail.save') }}</button>
                    </div>
                </div>
            </div>

            {{-- Inline attachment preview (image / PDF) --}}
            <div x-show="reader.attView.open" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeAttachment()">
                <div class="absolute inset-0 bg-gray-900/60" @click="closeAttachment()"></div>
                <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col rounded-lg bg-white shadow-xl">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-2">
                        <span class="truncate text-sm font-medium text-gray-900" x-text="reader.attView.name"></span>
                        <div class="flex shrink-0 items-center gap-1">
                            <a :href="reader.attView.url" :download="reader.attView.name" title="{{ __('mail.download') }}" aria-label="{{ __('mail.download') }}"
                                class="rounded p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700"><x-icon name="arrow-down-tray" class="h-4 w-4" /></a>
                            <button type="button" @click="closeAttachment()" title="{{ __('mail.close') }}" aria-label="{{ __('mail.close') }}"
                                class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><x-icon name="x-mark" class="h-5 w-5" /></button>
                        </div>
                    </div>
                    <div class="min-h-0 flex-1 overflow-auto bg-gray-50 p-3">
                        <div x-show="reader.attView.loading" class="flex items-center justify-center gap-2 py-16 text-sm text-gray-400">
                            <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />{{ __('mail.loading') }}
                        </div>
                        <p x-show="reader.attView.error" x-cloak class="py-16 text-center text-sm text-red-600" x-text="reader.attView.error"></p>
                        <template x-if="reader.attView.url && reader.attView.kind === 'image'">
                            <img :src="reader.attView.url" :alt="reader.attView.name" class="mx-auto max-h-[75vh] rounded object-contain">
                        </template>
                        <template x-if="reader.attView.url && reader.attView.kind === 'pdf'">
                            <object :data="reader.attView.url" type="application/pdf" class="h-[75vh] w-full rounded"></object>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
  </div>
</x-layouts.app>
