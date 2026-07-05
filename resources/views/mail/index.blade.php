<x-layouts.app :title="__('mail.title')">
  <div x-data="vaultMail({
        standalone: true,
        stale: @js(__('mail.stale')),
        saveFailed: @js(__('mail.save_failed')),
        connectFailed: @js(__('mail.connect_failed')),
        archiveDeleteConfirm: @js(__('mail.archive_delete_confirm')),
        sent: @js(__('mail.sent_toast')),
        draftSaved: @js(__('mail.draft_saved')),
        sendFailed: @js(__('mail.send_failed')),
        composeNeedsTo: @js(__('mail.compose_needs_to')),
        smtpMissingWarning: @js(__('mail.smtp_missing_warning')),
        forwardedMessage: @js(__('mail.forwarded_message')),
        attachSearch: @js(__('mail.attach_search')),
        attachSearchGallery: @js(__('mail.attach_search_gallery')),
        attachSearchFiles: @js(__('mail.attach_search_files')),
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


    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 bg-red-50 p-6 text-center text-sm text-red-700">{{ __('mail.save_failed') }}</p>
    </template>

    {{-- Ready but no account selected / none configured. The reader (below)
         auto-opens over this when an account exists. --}}
    <template x-if="state === 'ready'">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('mail.title') }}</h1>
        <template x-if="manifest.accounts.length === 0">
            <div class="mt-8 rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm">
                <p>{{ __('mail.empty') }}</p>
                <a href="{{ route('settings.mail.edit') }}" class="mt-4 inline-block rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('mail.manage_accounts') }}</a>
            </div>
        </template>
        <template x-if="manifest.accounts.length > 0 && ! reader.open">
            <div class="mt-8 flex flex-col items-center gap-3">
                <p class="text-sm text-gray-500">{{ __('mail.pick_account') }}</p>
                <div class="flex flex-wrap justify-center gap-2">
                    <template x-for="a in sortedAccounts" :key="a.id">
                        <button type="button" @click="openReader(a)" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50" x-text="a.name"></button>
                    </template>
                </div>
            </div>
        </template>
      </div>
    </template>

    {{-- Reader — inline in the page (fills the content column, keeps the app nav
         visible), not a fullscreen overlay. --}}
    <div x-show="reader.open" x-cloak class="relative mt-4 flex h-[calc(100vh-9rem)] flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" @keydown.escape.window="reader.current && (reader.current = null)">
            {{-- Non-blocking activity indicator (bottom-right): a background action
                 is running but the UI stays interactive — actions apply
                 optimistically, so more operations can start while this finishes. --}}
            <div x-show="reader.busy || reader.working > 0" x-cloak class="pointer-events-none absolute bottom-3 right-3 z-[68] flex items-center gap-1.5 rounded-full bg-gray-900/80 px-2.5 py-1 text-xs text-white shadow" title="{{ __('mail.working') }}">
                <x-icon name="arrow-path" class="h-3.5 w-3.5 animate-spin" />{{ __('mail.working') }}
            </div>
            {{-- Top bar: close (only when opened as an overlay from elsewhere) --}}
            <div x-show="! standalone" class="flex items-center gap-3 border-b border-gray-200 px-4 py-2">
                <button type="button" @click="closeReader()" title="{{ __('mail.close') }}" class="rounded p-1.5 text-gray-500 hover:bg-gray-100"><x-icon name="x-mark" class="h-5 w-5" /></button>
                <span class="truncate text-sm font-semibold text-gray-900" x-text="reader.account?.name"></span>
            </div>

            <p x-show="reader.error" x-cloak class="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700" x-text="reader.error"></p>

            {{-- Two-column: folders left, messages/message right --}}
            <div x-data="{ foldersOpen: false }" class="relative flex min-h-0 flex-1">
                {{-- Folder sidebar — static rail at md+, off-canvas drawer under md --}}
                <aside class="flex w-64 shrink-0 flex-col border-r border-gray-200 bg-white md:static md:translate-x-0 max-md:absolute max-md:inset-y-0 max-md:left-0 max-md:z-20 max-md:w-72 max-md:max-w-[85%] max-md:shadow-xl max-md:transition-transform"
                    :class="foldersOpen ? 'max-md:translate-x-0' : 'max-md:-translate-x-full'">
                    {{-- Compose --}}
                    <div class="border-b border-gray-200 p-2">
                        <button type="button" @click="newCompose()" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-gray-900 px-3 text-sm font-medium text-white hover:bg-gray-800">
                            <x-icon name="pencil" class="h-4 w-4" />{{ __('mail.compose') }}
                        </button>
                    </div>
                    {{-- Account switcher (dropdown in the sidebar head) --}}
                    <div class="relative border-b border-gray-200 p-2" @click.outside="accountMenuOpen = false">
                        <button type="button" @click="accountMenuOpen = ! accountMenuOpen"
                            class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-sm font-semibold text-gray-900 hover:bg-gray-50">
                            <span class="min-w-0 flex items-center gap-1.5">
                                <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-gray-400 transition-transform" ::class="accountMenuOpen ? 'rotate-180' : ''" />
                                <span class="truncate" x-text="reader.account?.name"></span>
                            </span>
                            <span x-show="accountUnread(reader.account?.id)" class="shrink-0 rounded-full bg-blue-500 px-1.5 py-0.5 text-[10px] font-medium text-white" x-text="accountUnread(reader.account?.id)"></span>
                        </button>
                        <div x-show="accountMenuOpen" x-cloak class="absolute left-2 right-2 z-30 mt-1 overflow-hidden rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                            <template x-for="a in sortedAccounts" :key="a.id">
                                <button type="button" @click="accountMenuOpen = false; switchAccount(a.id)"
                                    class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-sm hover:bg-gray-50"
                                    :class="a.id === reader.account?.id ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700'">
                                    <span class="truncate" x-text="a.name"></span>
                                    <span x-show="accountUnread(a.id)" class="shrink-0 rounded-full bg-blue-500 px-1.5 py-0.5 text-[10px] font-medium text-white" x-text="accountUnread(a.id)"></span>
                                </button>
                            </template>
                            <a href="{{ route('settings.mail.edit') }}" class="mt-1 flex items-center gap-2 border-t border-gray-100 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                                <x-icon name="pencil" class="h-3.5 w-3.5" />{{ __('mail.manage_accounts') }}
                            </a>
                        </div>
                    </div>
                    {{-- New folder --}}
                    <form class="flex items-center gap-1 border-b border-gray-100 p-2" @submit.prevent="createFolder($refs.newFolder.value); $refs.newFolder.value = ''">
                        <input type="text" x-ref="newFolder" required placeholder="{{ __('mail.new_folder') }}"
                            class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <button type="submit" title="{{ __('mail.new_folder') }}" aria-label="{{ __('mail.new_folder') }}" :disabled="reader.busy"
                            class="shrink-0 rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-50"><x-icon name="folder-plus" class="h-4 w-4" /></button>
                    </form>
                    <button type="button" @click="openSearch(reader.account)" class="flex w-full items-center gap-2 border-b border-gray-100 px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50">
                        <x-icon name="magnifying-glass" class="h-4 w-4" />{{ __('mail.search_title') }}
                    </button>
                    <button type="button" @click="openArchive(reader.account)" class="flex w-full items-center gap-2 border-b border-gray-100 px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50">
                        <x-icon name="archive" class="h-4 w-4" />{{ __('mail.archive_title') }}
                    </button>
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <div x-show="reader.foldersLoading && readerFolders().length === 0" class="flex items-center gap-2 px-3 py-4 text-xs text-gray-400">
                            <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />{{ __('mail.loading') }}
                        </div>
                        <template x-for="f in orderedFolders()" :key="f.path">
                            <div>
                                {{-- Non-selectable container (e.g. Gmail "[Gmail]"): just a label.
                                     Only an explicit false counts — a missing flag must not turn
                                     every reloaded folder into its own section header. --}}
                                <div x-show="f.selectable === false" class="truncate px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-400"
                                    :style="{ paddingLeft: (0.75 + folderDepth(f) * 0.75) + 'rem' }" x-text="folderLabel(f)"></div>
                                {{-- Selectable folder — standard folders get a role icon; custom none --}}
                                <button type="button" x-show="f.selectable !== false" @click="openFolder(f.path); foldersOpen = false"
                                    class="flex w-full items-center justify-between gap-2 py-2 pr-3 text-left text-sm hover:bg-gray-50"
                                    :style="{ paddingLeft: (0.75 + folderDepth(f) * 0.75) + 'rem' }"
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

                {{-- Mobile backdrop for the folder drawer --}}
                <div x-show="foldersOpen" x-cloak @click="foldersOpen = false" class="absolute inset-0 z-10 bg-gray-900/30 md:hidden"></div>

                {{-- Right pane --}}
                <div class="flex min-h-0 flex-1 flex-col">
            {{-- Unified action toolbar: one layout for both the open message and
                 a multi-selection. Single-only actions (back, headers, print) are
                 hidden in multi; multi-only (count, clear) hidden in single. --}}
            <div x-show="reader.current || reader.selected.length" x-cloak class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-2">
                {{-- Group 1: navigation --}}
                <button type="button" x-show="reader.current" @click="reader.current = null" title="{{ __('mail.back_to_list') }}" aria-label="{{ __('mail.back_to_list') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="chevron-left" class="h-4 w-4" /></button>
                <span x-show="! reader.current" class="text-sm font-medium text-gray-700"><span x-text="reader.selected.length"></span> {{ __('mail.selected') }}</span>
                <span x-show="reader.current" class="mx-0.5 h-5 w-px bg-gray-200" aria-hidden="true"></span>

                {{-- Group 2: reply / forward --}}
                <button type="button" x-show="reader.current" @click="reply(false)" title="{{ __('mail.reply') }}" aria-label="{{ __('mail.reply') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
                <button type="button" x-show="reader.current" @click="reply(true)" title="{{ __('mail.reply_all') }}" aria-label="{{ __('mail.reply_all') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="arrow-uturn-left" class="h-4 w-4" /><x-icon name="arrow-uturn-left" class="-ml-2 h-4 w-4" /></button>
                <button type="button" x-show="reader.current" @click="forward()" title="{{ __('mail.forward') }}" aria-label="{{ __('mail.forward') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="arrow-uturn-right" class="h-4 w-4" /></button>
                <span x-show="reader.current" class="mx-0.5 h-5 w-px bg-gray-200" aria-hidden="true"></span>

                {{-- Group 3: read state --}}
                <button type="button" @click="act('seen')" :disabled="reader.busy" title="{{ __('mail.mark_read') }}" aria-label="{{ __('mail.mark_read') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="envelope-open" class="h-4 w-4" /></button>
                <button type="button" @click="act('unseen')" :disabled="reader.busy" title="{{ __('mail.mark_unread') }}" aria-label="{{ __('mail.mark_unread') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="envelope" class="h-4 w-4" /></button>
                <span class="mx-0.5 h-5 w-px bg-gray-200" aria-hidden="true"></span>

                {{-- Group 4: organise (move / trash / delete) --}}
                <select @change="if ($event.target.value) { act('move', $event.target.value); $event.target.value = '' }" title="{{ __('mail.move_to_folder') }}" class="rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <option value="">{{ __('mail.move_to_folder') }}</option>
                    <template x-for="f in moveFolders()" :key="f.path"><option :value="f.path" x-text="folderLabel(f)"></option></template>
                </select>
                <button type="button" x-show="otherAccounts().length" @click="openTransfer()" :disabled="reader.busy" title="{{ __('mail.move_to_account') }}" aria-label="{{ __('mail.move_to_account') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="share" class="h-4 w-4" /></button>
                <button type="button" @click="act('trash')" :disabled="reader.busy" title="{{ __('mail.action_trash') }}" aria-label="{{ __('mail.action_trash') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="trash" class="h-4 w-4" /></button>
                <button type="button" @click="reader.current ? (reader.deleteChoiceOpen = true) : bulkAction('delete')" :disabled="reader.busy" title="{{ __('mail.action_delete') }}" aria-label="{{ __('mail.action_delete') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="x-circle" class="h-4 w-4" /></button>

                {{-- Group 5: utilities (right-aligned) --}}
                <button type="button" x-show="reader.current" @click="reader.headersOpen = true" title="{{ __('mail.show_headers') }}" aria-label="{{ __('mail.show_headers') }}" class="ml-auto rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="bars-3" class="h-4 w-4" /></button>
                <a x-show="reader.current && reader.current.archiveId" x-cloak :href="'/mail/archive/message/' + (reader.current?.archiveId ?? '') + '/download'"
                    title="{{ __('mail.download_eml') }}" aria-label="{{ __('mail.download_eml') }}" class="inline-flex items-center rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="arrow-down-tray" class="h-4 w-4" /></a>
                <button type="button" x-show="! reader.current && reader.selected.length" x-cloak @click="downloadSelectedMail()" title="{{ __('mail.download_selected') }}" aria-label="{{ __('mail.download_selected') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="arrow-down-tray" class="h-4 w-4" /></button>
                <button type="button" x-show="reader.current" @click="printMsg()" title="{{ __('mail.print') }}" aria-label="{{ __('mail.print') }}" class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="printer" class="h-4 w-4" /></button>
                <button type="button" x-show="! reader.current" @click="reader.selected = []" title="{{ __('mail.clear_selection') }}" aria-label="{{ __('mail.clear_selection') }}" class="ml-auto rounded-md border border-gray-300 p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"><x-icon name="x-mark" class="h-4 w-4" /></button>
            </div>
            {{-- Message list --}}
            <div x-show="! reader.current" class="flex min-h-0 flex-1 flex-col">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-1.5 text-xs text-gray-500">
                    <span class="flex min-w-0 items-center gap-2">
                        <button type="button" @click="foldersOpen = true" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border border-gray-300 text-gray-700 md:hidden" title="{{ __('common.sections') }}" aria-label="{{ __('common.sections') }}"><x-icon name="bars-3" class="h-4 w-4" /></button>
                        <input type="checkbox" @change="toggleSelectAll()" :checked="allSelected" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500" aria-label="{{ __('mail.select_all') }}">
                        <span class="truncate font-medium text-gray-700" x-text="currentFolderLabel()"></span>
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
                                <span x-show="m.archived" x-cloak class="shrink-0 text-gray-400" title="{{ __('mail.archived_badge') }}"><x-icon name="archive" class="h-3.5 w-3.5" /></span>
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
                {{-- Headers --}}
                <div class="border-b border-gray-100 px-6 py-4">
                    <div class="flex items-center gap-2">
                        <h1 class="min-w-0 text-lg font-semibold text-gray-900" x-text="reader.current.subject || @js(__('mail.no_subject'))"></h1>
                        <x-icon name="paperclip" x-show="(reader.current.attachments ?? []).length" x-cloak class="h-4 w-4 shrink-0 text-gray-500" title="{{ __('mail.attachments') }}" />
                        <x-icon name="archive" x-show="reader.current.archived" x-cloak class="h-4 w-4 shrink-0 text-gray-400" title="{{ __('mail.archived_badge') }}" />
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
                                <button type="button" x-show="isPdfAttachment(att) && $store.paperless.configured" @click="attachmentToPaperless(att)" title="{{ __('paperless.send_to_paperless') }}" aria-label="{{ __('paperless.send_to_paperless') }}"
                                    class="border-l border-gray-300 p-1.5 hover:bg-gray-50"><x-icon name="share" class="h-3.5 w-3.5" /></button>
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
                                <template x-for="f in transferFolders()" :key="f.path"><option :value="f.path" x-text="folderLabel(f)"></option></template>
                            </select>
                        </div>
                    </div>

                    {{-- Failure detail (why the move failed) — stays visible; modal doesn't close on error --}}
                    <div x-show="reader.transferError" x-cloak class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        <p class="font-medium">{{ __('mail.transfer_failed') }}</p>
                        <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap break-words font-mono text-[11px] text-red-800" x-text="reader.transferError"></pre>
                    </div>

                    <div class="mt-5 flex justify-end gap-3">
                        <button type="button" @click="reader.transferOpen = false" :disabled="reader.busy" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">{{ __('common.cancel') }}</button>
                        <button type="button" @click="confirmTransfer()" :disabled="reader.busy" class="inline-flex items-center gap-2 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">
                            <x-icon name="arrow-path" x-show="reader.busy" x-cloak class="h-4 w-4 animate-spin" />
                            <span x-text="reader.busy ? @js(__('mail.transferring')) : @js(__('mail.transfer_move'))"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Save attachment into Files --}}
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

    {{-- Local archive: server-deleted mail, restore / delete permanently --}}
    <template x-teleport="body">
        <div x-show="archive.open" x-cloak class="fixed inset-0 z-[1090] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeArchive()">
            <div class="absolute inset-0 bg-gray-900/60" @click="closeArchive()"></div>
            <div class="relative flex max-h-[92vh] w-full max-w-3xl flex-col rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('mail.archive_title') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('mail.archive_hint') }}</p>
                    </div>
                    <button type="button" @click="closeArchive()" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('common.close') }}"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>

                <div class="min-h-0 flex-1 overflow-auto">
                    <div x-show="archive.loading" class="flex items-center justify-center gap-2 py-10 text-sm text-gray-500"><x-icon name="arrow-path" class="h-4 w-4 animate-spin" />{{ __('mail.loading') }}</div>
                    <p x-show="! archive.loading && ! archive.messages.length" x-cloak class="py-10 text-center text-sm text-gray-500">{{ __('mail.archive_empty') }}</p>

                    {{-- Message view --}}
                    <template x-if="archive.viewing">
                        <div class="border-b border-gray-100 p-5">
                            <button type="button" @click="archive.viewing = null" class="mb-3 text-xs text-gray-500 hover:text-gray-700">&larr; {{ __('mail.back_to_list') }}</button>
                            <h4 class="text-base font-semibold text-gray-900" x-text="archive.viewing.subject || '—'"></h4>
                            <p class="mt-1 text-xs text-gray-500"><span x-text="archive.viewing.from?.name || archive.viewing.from?.email || archive.viewing.from"></span></p>
                            <div x-show="archive.viewLoading" class="py-6 text-sm text-gray-500">{{ __('mail.loading') }}</div>
                            <template x-if="(archive.viewing.attachments ?? []).length">
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <template x-for="att in archive.viewing.attachments" :key="att.id">
                                        <a :href="archivedAttachmentUrl(att.id)" class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50"><x-icon name="arrow-down-tray" class="h-3.5 w-3.5" /><span x-text="att.name"></span></a>
                                    </template>
                                </div>
                            </template>
                            {{-- Rendered in a sandboxed iframe (no scripts) with sanitised
                                 HTML + strict CSP — email HTML is untrusted. --}}
                            <iframe x-show="! archive.viewLoading" :srcdoc="archiveSrcdoc()" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" class="mt-4 h-[50vh] w-full rounded border border-gray-200 bg-white"></iframe>
                        </div>
                    </template>

                    {{-- List --}}
                    <ul x-show="! archive.viewing" class="divide-y divide-gray-100">
                        <template x-for="m in archive.messages" :key="m.id">
                            <li class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50">
                                <button type="button" @click="viewArchived(m)" class="min-w-0 flex-1 text-left">
                                    <p class="truncate text-sm font-medium text-gray-900" x-text="m.subject || '—'"></p>
                                    <p class="truncate text-xs text-gray-500"><span x-text="m.from"></span> · <span x-text="m.folder"></span></p>
                                </button>
                                <button type="button" @click="restoreArchived(m)" title="{{ __('mail.archive_restore') }}" class="rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-50"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
                                <button type="button" @click="deleteArchived(m)" title="{{ __('mail.archive_delete') }}" class="rounded-md border border-red-300 p-1.5 text-red-700 hover:bg-red-50"><x-icon name="trash" class="h-4 w-4" /></button>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    </template>

    {{-- Mail search over the whole local archive --}}
    <template x-teleport="body">
        <div x-show="search.open" x-cloak class="fixed inset-0 z-[1090] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeSearch()">
            <div class="absolute inset-0 bg-gray-900/60" @click="closeSearch()"></div>
            <div class="relative flex max-h-[92vh] w-full max-w-3xl flex-col rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('mail.search_title') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('mail.search_hint') }}</p>
                    </div>
                    <button type="button" @click="closeSearch()" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('common.close') }}"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>

                <form @submit.prevent="runSearch()" class="border-b border-gray-100 p-4">
                    <input type="search" x-model="search.q" placeholder="{{ __('mail.search_placeholder') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <div class="mt-3 flex flex-wrap items-end gap-3">
                        <label class="text-xs text-gray-600">{{ __('mail.search_from_date') }}
                            <input type="datetime-local" x-model="search.dateFrom" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </label>
                        <label class="text-xs text-gray-600">{{ __('mail.search_to_date') }}
                            <input type="datetime-local" x-model="search.dateTo" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" x-model="search.hasAttachment" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                            {{ __('mail.search_has_attachment') }}
                        </label>
                        <button type="submit" class="ml-auto rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('mail.search_run') }}</button>
                    </div>
                </form>

                <div class="min-h-0 flex-1 overflow-auto">
                    <p x-show="search.error" x-cloak class="px-5 py-2 text-sm text-red-600" x-text="search.error"></p>
                    <div x-show="search.loading" class="flex items-center justify-center gap-2 py-10 text-sm text-gray-500"><x-icon name="arrow-path" class="h-4 w-4 animate-spin" />{{ __('mail.loading') }}</div>
                    <p x-show="! search.loading && ! search.ran" x-cloak class="py-10 text-center text-sm text-gray-500">{{ __('mail.search_prompt') }}</p>
                    <p x-show="! search.loading && search.ran && ! search.results.length" x-cloak class="py-10 text-center text-sm text-gray-500">{{ __('mail.search_empty') }}</p>

                    {{-- Result list — clicking a hit closes the modal and opens the
                         message in the normal reader (not a modal viewer). --}}
                    <ul class="divide-y divide-gray-100">
                        <template x-for="m in search.results" :key="m.id">
                            <li class="px-5 py-3 hover:bg-gray-50">
                                <button type="button" @click="viewSearchResult(m)" class="block w-full min-w-0 text-left">
                                    <div class="flex items-center gap-2">
                                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900" x-text="m.subject || '—'"></p>
                                        <span x-show="m.hasAttachments" class="shrink-0 text-gray-400"><x-icon name="paperclip" class="h-4 w-4" /></span>
                                        <span class="shrink-0 text-xs text-gray-400" x-text="fmtDateTime(m.date)"></span>
                                    </div>
                                    <p class="truncate text-xs text-gray-500"><span x-text="m.from"></span> · <span x-text="m.folder"></span></p>
                                    <p class="truncate text-xs text-gray-400" x-text="m.preview"></p>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    </template>

    @include('_paperless_modal')

    {{-- Compose modal (full-screen on mobile) --}}
    <template x-teleport="body">
        <div x-show="compose.open" x-cloak class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-0 sm:p-4" role="dialog" aria-modal="true" @keydown.escape.window="compose.open = false">
            <div class="absolute inset-0 bg-gray-900/50" @click="compose.open = false"></div>
            <div class="relative flex min-h-full w-full flex-col bg-white shadow-xl sm:my-8 sm:min-h-0 sm:max-w-4xl sm:rounded-lg">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('mail.compose') }}</h3>
                    <button type="button" @click="compose.open = false" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-500 hover:bg-gray-50" aria-label="{{ __('common.cancel') }}"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    <template x-if="manifest.accounts.length > 1">
                        <select x-model.number="compose.accountId" @change="onComposeAccountChange()" class="w-full rounded-md border-gray-300 text-sm">
                            <template x-for="a in sortedAccounts" :key="a.id"><option :value="a.id" x-text="a.name"></option></template>
                        </select>
                    </template>
                    {{-- Sender identity (shown only when the account has more than one). --}}
                    <template x-if="_identities(_account(compose.accountId)).length > 1">
                        <div class="flex items-center gap-2">
                            <label class="w-12 shrink-0 text-sm text-gray-500">{{ __('mail.from_identity') }}</label>
                            <select x-model.number="compose.identityId" @change="onComposeIdentityChange()" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                                <template x-for="i in _identities(_account(compose.accountId))" :key="i.id">
                                    <option :value="i.id" x-text="(i.fromName ? i.fromName + ' <' + i.fromEmail + '>' : i.fromEmail)"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                    <div x-show="compose.accountId && ! (_account(compose.accountId)?.smtpConfigured)" x-cloak
                        class="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        <x-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ __('mail.smtp_missing_warning') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="w-12 shrink-0 text-sm text-gray-500">{{ __('mail.to') }}</label>
                        <div class="relative min-w-0 flex-1">
                            <input type="text" x-model="compose.to" @input="recipientInput('to', $event)" @keydown="recipientKeydown('to', $event)" @blur="recipientBlur('to')" autocomplete="off" class="w-full rounded-md border-gray-300 text-sm" placeholder="a@b.com, c@d.com">
                            @include('mail._recipient_suggest', ['field' => 'to'])
                        </div>
                        <button type="button" @click="compose.showCc = ! compose.showCc" class="shrink-0 text-xs font-medium text-gray-500 hover:text-gray-800">{{ __('mail.add_cc') }}</button>
                    </div>
                    <div x-show="compose.showCc" x-cloak class="space-y-2">
                        <div class="flex items-center gap-2">
                            <label class="w-12 shrink-0 text-sm text-gray-500">{{ __('mail.cc') }}</label>
                            <div class="relative min-w-0 flex-1">
                                <input type="text" x-model="compose.cc" @input="recipientInput('cc', $event)" @keydown="recipientKeydown('cc', $event)" @blur="recipientBlur('cc')" autocomplete="off" class="w-full rounded-md border-gray-300 text-sm">
                                @include('mail._recipient_suggest', ['field' => 'cc'])
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="w-12 shrink-0 text-sm text-gray-500">{{ __('mail.bcc') }}</label>
                            <div class="relative min-w-0 flex-1">
                                <input type="text" x-model="compose.bcc" @input="recipientInput('bcc', $event)" @keydown="recipientKeydown('bcc', $event)" @blur="recipientBlur('bcc')" autocomplete="off" class="w-full rounded-md border-gray-300 text-sm">
                                @include('mail._recipient_suggest', ['field' => 'bcc'])
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="w-12 shrink-0 text-sm text-gray-500">{{ __('mail.subject') }}</label>
                        <input type="text" x-model="compose.subject" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
                    </div>

                    @include('mail._compose_editor')

                    {{-- Attachments --}}
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-md border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <x-icon name="arrow-up-tray" class="h-4 w-4" />{{ __('mail.attach_upload') }}
                                <input type="file" multiple class="hidden" @change="addUploads($event)">
                            </label>
                            <button type="button" @click="openAttachPicker('gallery')" class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50"><x-icon name="photo" class="h-4 w-4" />{{ __('mail.attach_gallery') }}</button>
                            <button type="button" @click="openAttachPicker('files')" class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50"><x-icon name="files" class="h-4 w-4" />{{ __('mail.attach_files') }}</button>
                        </div>
                        <ul class="mt-2 space-y-1" x-show="compose.uploads.length || compose.refs.length" x-cloak>
                            <template x-for="(f, i) in compose.uploads" :key="'u'+i">
                                <li class="flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1 text-xs">
                                    <span class="truncate" x-text="f.name"></span>
                                    <button type="button" @click="removeUpload(i)" class="text-gray-400 hover:text-red-600"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                                </li>
                            </template>
                            <template x-for="(r, i) in compose.refs" :key="'r'+i">
                                <li class="flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1 text-xs">
                                    <span class="truncate" x-text="r.name"></span>
                                    <button type="button" @click="removeRef(i)" class="text-gray-400 hover:text-red-600"><x-icon name="x-mark" class="h-3.5 w-3.5" /></button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <p x-show="compose.error" x-cloak class="text-xs text-red-600" x-text="compose.error"></p>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-4 py-3">
                    <button type="button" @click="saveDraft()" :disabled="compose.sending" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60">{{ __('mail.save_draft') }}</button>
                    <button type="button" @click="sendCompose()" :disabled="compose.sending || ! (_account(compose.accountId)?.smtpConfigured)" class="inline-flex items-center gap-1.5 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-60"><x-icon name="arrow-uturn-right" class="h-4 w-4" />{{ __('mail.send') }}</button>
                </div>
            </div>
        </div>
    </template>

    {{-- Attachment picker (gallery / files) --}}
    <template x-teleport="body">
        <div x-show="attachPicker.open" x-cloak class="fixed inset-0 z-[75] flex items-start justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" @keydown.escape.window="attachPicker.open = false">
            <div class="absolute inset-0 bg-gray-900/50" @click="attachPicker.open = false"></div>
            <div class="relative my-10 w-full max-w-2xl rounded-lg bg-white p-5 shadow-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('mail.attach_pick') }}</h3>
                    <button type="button" @click="attachPicker.open = false" class="text-gray-400 hover:text-gray-600"><x-icon name="x-mark" class="h-5 w-5" /></button>
                </div>
                {{-- Search: gallery re-queries the server (album/person/text/id);
                     files filter the loaded list client-side. --}}
                <div class="mt-4">
                    <input type="text" x-model="attachPicker.q"
                        @input.debounce.250ms="attachPicker.source === 'gallery' ? searchAttachGallery() : null"
                        :placeholder="attachPicker.source === 'gallery' ? labels.attachSearchGallery : labels.attachSearchFiles"
                        :aria-label="labels.attachSearch"
                        class="w-full rounded-md border-gray-300 text-sm">
                </div>
                <p x-show="attachPicker.loading" x-cloak class="mt-4 text-sm text-gray-500">…</p>

                {{-- Gallery: thumbnail-only grid, selection shown via ring. --}}
                <template x-if="attachPicker.source === 'gallery'">
                    <div class="mt-4 grid max-h-[55vh] grid-cols-3 gap-2 overflow-y-auto sm:grid-cols-4">
                        <template x-for="it in attachPicker.items" :key="it.id">
                            <button type="button" @click="togglePick(it.id)"
                                class="relative aspect-square overflow-hidden rounded-md ring-2 ring-offset-1 focus:outline-none"
                                :class="attachPicker.chosen.includes(it.id) ? 'ring-gray-900' : 'ring-transparent hover:ring-gray-300'"
                                :aria-pressed="attachPicker.chosen.includes(it.id)">
                                <img :src="it.thumb" alt="" loading="lazy" class="h-full w-full object-cover">
                                <span x-show="attachPicker.chosen.includes(it.id)" x-cloak
                                    class="absolute right-1 top-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-900 text-white">
                                    <x-icon name="check" class="h-3 w-3" />
                                </span>
                            </button>
                        </template>
                    </div>
                </template>

                {{-- Files: name list (no thumbnails), client-side filtered. --}}
                <template x-if="attachPicker.source === 'files'">
                    <div class="mt-4 grid max-h-[55vh] grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2">
                        <template x-for="it in filteredAttachFiles" :key="it.id">
                            <button type="button" @click="togglePick(it.id)" class="flex items-center gap-2 rounded-md border p-2 text-left text-sm" :class="attachPicker.chosen.includes(it.id) ? 'border-gray-900 bg-gray-50' : 'border-gray-200'">
                                <x-icon name="document-text" class="h-5 w-5 shrink-0 text-gray-400" />
                                <span class="min-w-0 truncate" x-text="it.name"></span>
                            </button>
                        </template>
                    </div>
                </template>

                <p x-show="! attachPicker.loading && (attachPicker.source === 'gallery' ? attachPicker.items.length === 0 : filteredAttachFiles.length === 0)" x-cloak
                    class="mt-4 text-sm text-gray-500">{{ __('mail.attach_no_results') }}</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="attachPicker.open = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                    <button type="button" @click="confirmAttachPicker()" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">{{ __('mail.attach_done') }} <span x-show="attachPicker.chosen.length" x-text="'('+attachPicker.chosen.length+')'"></span></button>
                </div>
            </div>
        </div>
    </template>
  </div>
</x-layouts.app>
