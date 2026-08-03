<x-layouts.app :title="__('mail.title')">
  <div class="mx-auto w-full max-w-7xl" x-data="{ tab: 'read' }">

    <x-page-heading :title="__('mail.title')" :subtitle="__('mail.subheading')" />

    {{-- Tabs: reading vs. account management --}}
    <div class="mt-4 inline-flex rounded-xl bg-black/[0.04] dark:bg-white/[0.06] p-0.5 text-sm">
        <button type="button" @click="tab = 'read'"
                class="rounded-lg px-4 py-1.5 font-medium transition"
                :class="tab === 'read' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'">
            {{ __('mail.read_tab') }}
        </button>
        <button type="button" @click="tab = 'accounts'"
                class="rounded-lg px-4 py-1.5 font-medium transition"
                :class="tab === 'accounts' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'">
            {{ __('mail.accounts_tab') }}
        </button>
    </div>

    {{-- ============================ READING TAB ============================ --}}
    <div x-show="tab === 'read'" class="mt-5" x-data="mailArchive({
            messagesUrl: '{{ route('mail.messages.index') }}',
            accountsUrl: '{{ route('mail.accounts.index') }}',
            rawBase: '{{ route('mail.raw', ['blob' => '__id__']) }}',
            pushbackBase: '{{ route('mail.messages.pushback', ['message' => '__id__']) }}',
            envelopeBase: '{{ route('mail.messages.envelope', ['message' => '__id__']) }}',
            trashBase: '{{ route('mail.messages.trash') }}',
            restoreBase: '{{ route('mail.messages.restore') }}',
            loadFailed: @js(__('mail.load_failed')),
            decryptFailed: @js(__('mail.decrypt_failed')),
            unknown: @js(__('mail.unknown')),
            noSubject: @js(__('mail.no_subject')),
            pushConfirmMsg: @js(__('mail.push_confirm')),
            pushFailed: @js(__('mail.push_failed')),
            pushDone: @js(__('mail.push_done')),
            pushBulkDone: @js(__('mail.push_bulk_done')),
            trashConfirm: @js(__('mail.trash_confirm')),
            trashDone: @js(__('mail.trash_done')),
            restoreDone: @js(__('mail.restore_done')),
            trashFailed: @js(__('mail.trash_failed')),
         })">

        <div x-show="!unlocked" x-cloak>
            <x-alert variant="info">
                <span>{{ __('mail.locked_hint') }}</span>
                <button type="button" class="ml-1 font-medium text-accent underline" @click="unlock()">{{ __('mail.unlock') }}</button>
            </x-alert>
        </div>

        <template x-if="unlocked">
        <div>
            {{-- Filters --}}
            <div class="mb-4 flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-gray-500">{{ __('mail.col_mailbox') }}</label>
                    <select x-model="fAccount" class="rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] py-1.5 text-sm focus:border-accent focus:ring-accent">
                        <option value="">{{ __('mail.filter_all_accounts') }}</option>
                        <template x-for="a in accounts" :key="a.id">
                            <option :value="a.id" x-text="a.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-gray-500">{{ __('mail.col_folder') }}</label>
                    <select x-model="fFolder" class="rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] py-1.5 text-sm focus:border-accent focus:ring-accent">
                        <option value="">{{ __('mail.filter_all_folders') }}</option>
                        <template x-for="f in folders" :key="f">
                            <option :value="f" x-text="f"></option>
                        </template>
                    </select>
                </div>
                <div class="min-w-[16rem] flex-1">
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-gray-500">{{ __('mail.filter_search_label') }}</label>
                    <input type="search" x-model="fText" placeholder="{{ __('mail.filter_search_ph') }}"
                           class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] py-1.5 text-sm focus:border-accent focus:ring-accent">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-gray-500">{{ __('mail.date_from') }}</label>
                    <input type="date" x-model="fFrom" class="rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] py-1.5 text-sm focus:border-accent focus:ring-accent">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-gray-500">{{ __('mail.date_to') }}</label>
                    <input type="date" x-model="fTo" class="rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] py-1.5 text-sm focus:border-accent focus:ring-accent">
                </div>
                <x-button variant="secondary" size="sm" x-show="filtersActive" x-cloak @click="resetFilters()">{{ __('common.reset') }}</x-button>
            </div>

            {{-- Archive vs. trash (soft-hidden) view --}}
            <div class="mb-3 flex items-center justify-between gap-3">
                <div class="inline-flex rounded-xl bg-black/[0.04] dark:bg-white/[0.06] p-0.5 text-sm">
                    <button type="button" @click="showTrash = false" class="rounded-lg px-3 py-1 font-medium transition" :class="!showTrash ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('mail.archive_toggle') }}</button>
                    <button type="button" @click="showTrash = true" class="rounded-lg px-3 py-1 font-medium transition" :class="showTrash ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('mail.trash_toggle') }}</button>
                </div>
                <span class="text-xs text-gray-400">{{ __('mail.trash_immutable') }}</span>
            </div>

            {{-- Bulk action bar --}}
            <div x-show="selectedCount > 0" x-cloak class="mb-3 flex flex-wrap items-center gap-3 rounded-xl bg-accent/10 px-3 py-2 text-sm">
                <span class="font-medium text-accent" x-text="'{{ __('mail.bulk_selected') }}'.replace(':n', selectedCount)"></span>
                <div class="flex flex-wrap gap-2">
                    <x-button variant="secondary" size="sm" ::disabled="bulkBusy" @click="bulkPushBack()">
                        <x-icon name="arrow-up-tray" class="mr-1 h-4 w-4" />{{ __('mail.push_back') }}
                    </x-button>
                    <x-button variant="danger" size="sm" x-show="!showTrash" @click="bulkTrash()">{{ __('mail.trash_action') }}</x-button>
                    <x-button variant="secondary" size="sm" x-show="showTrash" x-cloak @click="bulkRestore()">{{ __('mail.restore_action') }}</x-button>
                </div>
                <span x-show="bulkBusy" x-cloak class="tabular-nums text-xs text-gray-500" x-text="`${progress}/${progressTotal}`"></span>
                <button type="button" class="ml-auto text-xs text-gray-500 underline" @click="clearSelection()">{{ __('mail.bulk_clear') }}</button>
            </div>

            <div class="ll-card !p-0 overflow-hidden">
                <div x-show="loading" class="flex items-center justify-center gap-2 p-8 text-sm text-gray-500">
                    <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />
                    <span>{{ __('mail.decrypting') }}</span>
                    <span class="tabular-nums" x-text="`${progress}/${progressTotal}`"></span>
                </div>
                <x-alert variant="error" x-show="error" x-cloak x-text="error" class="m-4" />

                <template x-if="!loading && !error && cache.length === 0">
                    <x-empty-state icon="envelope" class="p-10">{{ __('mail.archive_empty') }}</x-empty-state>
                </template>

                <template x-if="!loading && cache.length > 0">
                <div>
                    <div class="overflow-x-auto">
                        <table class="w-full table-fixed text-left text-sm">
                            <colgroup>
                                <col style="width:4%"><col style="width:8%"><col style="width:10%"><col style="width:17%">
                                <col style="width:17%"><col style="width:31%"><col style="width:9%"><col style="width:4%">
                            </colgroup>
                            <thead class="border-b border-black/[0.06] dark:border-white/10 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-3 py-2.5"><input type="checkbox" @click="toggleSelectAllPage()" :checked="allPageSelected" class="rounded border-gray-300 dark:border-gray-700 text-accent focus:ring-accent"></th>
                                    <th class="px-3 py-2.5 font-medium">{{ __('mail.col_folder') }}</th>
                                    <th class="px-3 py-2.5 font-medium">{{ __('mail.col_mailbox') }}</th>
                                    <th class="px-3 py-2.5 font-medium">{{ __('mail.col_from') }}</th>
                                    <th class="px-3 py-2.5 font-medium">{{ __('mail.col_to') }}</th>
                                    <th class="px-3 py-2.5 font-medium">{{ __('mail.col_subject') }}</th>
                                    <th class="px-3 py-2.5 font-medium">{{ __('mail.col_date') }}</th>
                                    <th class="px-3 py-2.5 text-center font-medium"><x-icon name="paper-clip" class="mx-auto h-4 w-4" /></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-black/[0.06] dark:divide-white/10">
                                <template x-for="r in pageRows" :key="r.id">
                                    <tr class="cursor-pointer hover:bg-accent/5" :class="{ 'opacity-50': !r.ok, 'bg-accent/5': isSelected(r.id) }" @click="openMessage(r)">
                                        <td class="px-3 py-2.5" @click.stop><input type="checkbox" :checked="isSelected(r.id)" @change="toggleSelect(r.id)" class="rounded border-gray-300 dark:border-gray-700 text-accent focus:ring-accent"></td>
                                        <td class="truncate px-3 py-2.5"><x-badge variant="gray" x-text="r.folder"></x-badge></td>
                                        <td class="truncate px-3 py-2.5 text-gray-500" x-text="r.mailbox" :title="r.mailbox"></td>
                                        <td class="truncate px-3 py-2.5" x-text="r.from" :title="r.from"></td>
                                        <td class="truncate px-3 py-2.5 text-gray-500" x-text="r.to" :title="r.to"></td>
                                        <td class="truncate px-3 py-2.5 text-gray-900 dark:text-gray-100" :class="r.seen ? 'font-medium' : 'font-bold'" :title="r.subject">
                                            <span x-show="r.spam" x-cloak class="mr-1 align-middle"><x-badge variant="error">{{ __('mail.spam') }}</x-badge></span>
                                            <span x-text="r.subject"></span>
                                        </td>
                                        <td class="truncate px-3 py-2.5 text-gray-500 tabular-nums" x-text="r.dateLabel" :title="r.dateLabel"></td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span x-show="r.hasAttachment"><x-icon name="paper-clip" class="mx-auto h-4 w-4 text-gray-400" /></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Footer: counts + capped note + pagination --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-black/[0.06] dark:border-white/10 px-4 py-3 text-sm">
                        <span class="text-gray-500">
                            <span x-text="`${filtered.length} · ${page}/${lastPage}`"></span>
                            <span x-show="capped" x-cloak class="ml-2 text-amber-600 dark:text-amber-400">{{ __('mail.capped_note', ['n' => 3000]) }}</span>
                        </span>
                        <div class="flex gap-2" x-show="lastPage > 1">
                            <x-button variant="secondary" size="sm" ::disabled="page <= 1" @click="goto(page - 1)">{{ __('common.previous') }}</x-button>
                            <x-button variant="secondary" size="sm" ::disabled="page >= lastPage" @click="goto(page + 1)">{{ __('common.next') }}</x-button>
                        </div>
                    </div>
                </div>
                </template>
            </div>
        </div>
        </template>

        {{-- Message / attachments modal --}}
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeMessage()">
                <div class="absolute inset-0 bg-gray-900/50" @click="closeMessage()"></div>
                <div class="relative flex max-h-[90vh] w-full max-w-4xl flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
                    <div class="flex items-start justify-between gap-3 border-b border-gray-100 dark:border-gray-800 px-5 py-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-gray-900 dark:text-gray-100" x-text="open?.subject"></h3>
                            <p class="mt-0.5 truncate text-xs text-gray-500" x-text="`${open?.from} → ${open?.to} · ${open?.dateLabel}`"></p>
                            <p class="mt-1 flex flex-wrap items-center gap-1.5">
                                <span x-show="pgp === 'ok'"><x-badge variant="success"><x-icon name="lock-open" class="mr-1 inline h-3 w-3" />{{ __('mail.pgp_ok') }}</x-badge></span>
                                <span x-show="pgp === 'nokey'"><x-badge variant="warning">{{ __('mail.pgp_nokey') }}</x-badge></span>
                                <span x-show="pgp === 'fail'"><x-badge variant="error">{{ __('mail.pgp_failed') }}</x-badge></span>
                                <span x-show="spam"><x-badge variant="error">{{ __('mail.spam') }}</x-badge></span>
                                <template x-for="k in ['spf','dkim','dmarc']" :key="k">
                                    <span x-show="auth[k]"
                                          class="rounded px-1.5 py-0.5 text-[11px] font-medium"
                                          :class="auth[k] === 'pass' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : ((auth[k] === 'fail' || auth[k] === 'hardfail') ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300')"
                                          x-text="k.toUpperCase() + ': ' + auth[k]"></span>
                                </template>
                            </p>
                        </div>
                        <x-icon-button name="x-mark" tone="gray" size="sm" @click="closeMessage()" :aria-label="__('common.close')" />
                    </div>

                    <div class="min-h-0 flex-1 overflow-auto p-5">
                        <div x-show="openLoading" class="flex items-center justify-center gap-2 py-10 text-sm text-gray-500">
                            <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />{{ __('mail.decrypting') }}
                        </div>
                        <x-alert variant="error" x-show="openError" x-cloak x-text="openError" />

                        <template x-if="msg && !openLoading">
                        <div>
                            {{-- Attachments --}}
                            <template x-if="msg.attachments.length">
                            <div class="mb-5">
                                <p class="mb-2 text-[11px] uppercase tracking-wide text-gray-500"><span x-text="msg.attachments.length"></span> {{ __('mail.attachments') }}</p>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <template x-for="(att, i) in msg.attachments" :key="i">
                                        <div class="flex items-center gap-2.5 rounded-xl border border-black/[0.06] dark:border-white/10 bg-black/[0.02] dark:bg-white/[0.03] px-3 py-2">
                                            <span class="ll-chip flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" style="background:#6b7280">
                                                <x-icon name="paper-clip" class="h-4 w-4 text-white" />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm text-gray-900 dark:text-gray-100" x-text="att.filename" :title="att.filename"></p>
                                                <p class="truncate text-xs text-gray-400" x-text="fmtSize(att.size)"></p>
                                            </div>
                                            <x-icon-button name="eye" tone="gray" size="sm" x-show="canView(att)" @click="viewAttachment(att)" :aria-label="__('mail.att_view')" />
                                            <x-icon-button name="arrow-down-tray" tone="gray" size="sm" @click="downloadAttachment(att)" :aria-label="__('mail.att_download')" />
                                        </div>
                                    </template>
                                </div>
                            </div>
                            </template>

                            {{-- Controls: load remote content for this mail, show original headers --}}
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <x-button variant="secondary" size="sm" icon="photo" x-show="canLoadRemote" @click="loadRemoteOnce()">{{ __('mail.load_remote') }}</x-button>
                                <x-button variant="secondary" size="sm" icon="document-text" @click="showHeaders = !showHeaders">
                                    <span x-show="!showHeaders">{{ __('mail.headers') }}</span>
                                    <span x-show="showHeaders" x-cloak>{{ __('common.close') }}</span>
                                </x-button>
                            </div>
                            <pre x-show="showHeaders" x-cloak x-text="rawHead" class="mb-4 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded-lg border border-black/[0.06] dark:border-white/10 bg-black/[0.02] dark:bg-white/[0.03] p-3 font-mono text-[11px] text-gray-700 dark:text-gray-300"></pre>

                            {{-- Body: sandboxed iframe (scripts on), sanitized HTML (scripts off), else plain text --}}
                            <iframe x-show="bodyFrame" x-cloak :srcdoc="bodyFrame" sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" class="h-[62vh] w-full rounded-lg border border-black/[0.06] dark:border-white/10 bg-white"></iframe>
                            <div x-show="bodyHtml && !bodyFrame" x-html="bodyHtml" class="ll-mail-body text-sm text-gray-800 dark:text-gray-200"></div>
                            <pre x-show="!bodyHtml && !bodyFrame" class="whitespace-pre-wrap break-words font-sans text-sm text-gray-800 dark:text-gray-200" x-text="bodyText || @js(__('mail.no_body'))"></pre>
                        </div>
                        </template>
                    </div>

                    {{-- Footer: trash/restore + push back to origin server --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-800 px-5 py-3">
                        <div class="flex gap-2">
                            <x-button variant="danger" size="sm" x-show="!open?.trashed" @click="trashOpen()">
                                <x-icon name="trash" class="mr-1 h-4 w-4" />{{ __('mail.trash_action') }}
                            </x-button>
                            <x-button variant="secondary" size="sm" x-show="open?.trashed" x-cloak @click="restoreOpen()">
                                {{ __('mail.restore_action') }}
                            </x-button>
                        </div>
                        <x-button variant="secondary" size="sm" ::disabled="pushing || openLoading || !msg" @click="pushBack()">
                            <x-icon name="arrow-up-tray" class="mr-1 h-4 w-4" />{{ __('mail.push_back') }}
                        </x-button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ============================ ACCOUNTS TAB ============================ --}}
    <div x-show="tab === 'accounts'" x-cloak class="mt-5" x-data="mailAccounts({
            accountsUrl: '{{ route('mail.accounts.index') }}',
            updateBase: '{{ route('mail.accounts.update', ['account' => '__id__']) }}',
            deleteBase: '{{ route('mail.accounts.destroy', ['account' => '__id__']) }}',
            syncBase: '{{ route('mail.accounts.sync', ['account' => '__id__']) }}',
            cancelBase: '{{ route('mail.accounts.sync-cancel', ['account' => '__id__']) }}',
            statusBase: '{{ route('mail.accounts.status', ['account' => '__id__']) }}',
            loadFailed: @js(__('mail.load_failed')),
            saveFailed: @js(__('mail.save_failed')),
            deleteFailed: @js(__('mail.delete_failed')),
            syncFailed: @js(__('mail.sync_failed')),
            syncBusy: @js(__('mail.sync_busy')),
            cancelFailed: @js(__('mail.cancel_failed')),
            deleteConfirm: @js(__('mail.delete_confirm')),
            neverSynced: @js(__('mail.never_synced')),
            lastSynced: @js(__('mail.last_synced')),
            messageCount: @js(__('mail.message_count')),
         })">

        <div class="mb-4 flex justify-end">
            <x-button variant="primary" icon="plus" @click="openCreate()">{{ __('mail.add_account') }}</x-button>
        </div>

        <x-alert variant="error" x-show="error" x-cloak class="mb-4" x-text="error" />

        <p x-show="loading" class="text-sm text-gray-500 dark:text-gray-400">{{ __('mail.loading') }}</p>

        <x-empty-state icon="envelope" x-show="! loading && accounts.length === 0" class="mt-10 py-10">
            {{ __('mail.empty') }}
        </x-empty-state>

        <div x-show="! loading && accounts.length > 0" x-cloak
             class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            <template x-for="a in accounts" :key="a.id">
                <div class="flex items-start gap-3 px-4 py-3">
                    <span class="ll-chip mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :style="{ background: statusTint(a) }">
                        <x-icon name="envelope" class="h-5 w-5 text-white" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="min-w-0 truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="a.name"></span>
                            <x-badge variant="gray" x-show="a.status === 'idle'">{{ __('mail.status_idle') }}</x-badge>
                            <x-badge variant="warning" x-show="a.status === 'syncing'">{{ __('mail.status_syncing') }}</x-badge>
                            <x-badge variant="error" x-show="a.status === 'error'">{{ __('mail.status_error') }}</x-badge>
                            <x-badge variant="gray" x-show="! a.enabled">{{ __('mail.disabled') }}</x-badge>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400" x-text="a.host + ':' + a.port + ' · ' + a.username"></p>
                        <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] tabular-nums text-gray-400 dark:text-gray-500">
                            <span x-text="lastSyncedLabel(a)"></span>
                            <span x-text="messageCountLabel(a)"></span>
                        </p>
                        <x-alert variant="error" x-show="a.status === 'error' && a.last_error" x-cloak class="mt-2 !px-2.5 !py-1.5 text-xs" x-text="a.last_error" />
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <x-button variant="secondary" size="sm" x-show="a.status !== 'syncing'" @click="syncNow(a)">
                            {{ __('mail.sync_now') }}
                        </x-button>
                        <x-button variant="danger" size="sm" x-show="a.status === 'syncing'" x-cloak @click="cancelSync(a)">
                            {{ __('mail.sync_cancel') }}
                        </x-button>
                        <x-action-menu :aria-label="__('common.actions')">
                            <x-action-menu-item icon="pencil" @click="openEdit(a)">{{ __('common.edit') }}</x-action-menu-item>
                            <x-action-menu-item icon="trash" danger @click="remove(a)">{{ __('common.delete') }}</x-action-menu-item>
                        </x-action-menu>
                    </div>
                </div>
            </template>
        </div>

        {{-- Add/edit account modal --}}
        <template x-teleport="body">
            <div x-show="modalOpen" x-cloak class="fixed inset-0 z-[1050] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeModal()">
                <div class="absolute inset-0 bg-gray-900/50" @click="closeModal()"></div>
                <div class="relative flex max-h-[92vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl" x-show="form">
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-3">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="form?.id ? @js(__('mail.edit_account')) : @js(__('mail.new_account'))"></h3>
                        <x-icon-button name="x-mark" tone="gray" size="sm" @click="closeModal()" :aria-label="__('common.close')" />
                    </div>
                    <template x-if="form">
                    <div class="min-h-0 flex-1 space-y-4 overflow-auto p-5">
                        <x-alert variant="error" x-show="saveError" x-cloak x-text="saveError" />

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mail.name') }}</label>
                            <input type="text" x-model="form.name" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mail.host') }}</label>
                                <input type="text" x-model="form.host" autocomplete="off" placeholder="{{ __('mail.host_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mail.port') }}</label>
                                <input type="number" min="1" max="65535" x-model.number="form.port" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mail.username') }}</label>
                            <input type="text" x-model="form.username" autocomplete="off" placeholder="{{ __('mail.username_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mail.password') }}</label>
                            <input type="password" x-model="form.password" autocomplete="new-password" placeholder="{{ __('mail.password_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                            <p x-show="form.id" x-cloak class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('mail.password_hint') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mail.encryption') }}</label>
                            <select x-model="form.encryption" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                                <option value="ssl">{{ __('mail.encryption_ssl') }}</option>
                                <option value="tls">{{ __('mail.encryption_tls') }}</option>
                                <option value="starttls">{{ __('mail.encryption_starttls') }}</option>
                                <option value="none">{{ __('mail.encryption_none') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mail.folders') }}</label>
                            <x-tag-field :placeholder="__('mail.folders_placeholder')" />
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('mail.folders_hint') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mail.backfill_since') }}</label>
                            <input type="date" x-model="form.backfill_since" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-accent focus:ring-accent sm:text-sm">
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('mail.backfill_hint') }}</p>
                        </div>

                        <label class="flex items-center gap-2">
                            <input type="checkbox" x-model="form.enabled" class="rounded border-gray-300 dark:border-gray-700 text-accent focus:ring-accent">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('mail.enabled') }}</span>
                        </label>
                    </div>
                    </template>
                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 dark:border-gray-800 px-5 py-3">
                        <x-button variant="secondary" @click="closeModal()">{{ __('common.cancel') }}</x-button>
                        <x-button variant="primary" ::disabled="saving" @click="save()">{{ __('common.save') }}</x-button>
                    </div>
                </div>
            </div>
        </template>
    </div>
  </div>
</x-layouts.app>
