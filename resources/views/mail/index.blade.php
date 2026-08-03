<x-layouts.app :title="__('mail.title')">
  <div x-data="mailAccounts({
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
   <div class="mx-auto w-full max-w-3xl">

    <x-page-heading :title="__('mail.title')" :subtitle="__('mail.subheading')">
        <x-slot:actions>
            <x-button variant="primary" icon="plus" @click="openCreate()">{{ __('mail.add_account') }}</x-button>
        </x-slot:actions>
    </x-page-heading>

    <x-alert variant="error" x-show="error" x-cloak class="mt-4" x-text="error" />

    <div class="mt-6">
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
    </div>
   </div>

   {{-- Archived-message list (zero-knowledge: decrypted + parsed client-side) --}}
   <div class="mx-auto mt-8 w-full max-w-6xl" x-data="mailArchive({
        messagesUrl: '{{ route('mail.messages.index') }}',
        accountsUrl: '{{ route('mail.accounts.index') }}',
        rawBase: '{{ route('mail.raw', ['blob' => '__id__']) }}',
        loadFailed: @js(__('mail.load_failed')),
        decryptFailed: @js(__('mail.decrypt_failed')),
        unknown: @js(__('mail.unknown')),
        noSubject: @js(__('mail.no_subject')),
     })">
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('mail.archive_heading') }}</h2>

        <div x-show="!unlocked" x-cloak>
            <x-alert variant="info">
                <span>{{ __('mail.locked_hint') }}</span>
                <button type="button" class="ml-1 font-medium text-accent underline" @click="unlock()">{{ __('mail.unlock') }}</button>
            </x-alert>
        </div>

        <template x-if="unlocked">
        <div class="ll-card !p-0 overflow-hidden">
            <div x-show="loading" class="flex items-center justify-center gap-2 p-8 text-sm text-gray-500">
                <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />{{ __('common.loading') }}
            </div>
            <x-alert variant="error" x-show="error" x-cloak x-text="error" class="m-4" />

            <template x-if="!loading && !error && rows.length === 0">
                <x-empty-state class="p-10">{{ __('mail.empty') }}</x-empty-state>
            </template>

            <div x-show="!loading && rows.length" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-black/[0.06] dark:border-white/10 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">{{ __('mail.col_folder') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('mail.col_mailbox') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('mail.col_from') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('mail.col_to') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('mail.col_subject') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('mail.col_date') }}</th>
                            <th class="px-4 py-2.5 text-center font-medium"><x-icon name="paper-clip" class="mx-auto h-4 w-4" /></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-black/[0.06] dark:divide-white/10">
                        <template x-for="r in rows" :key="r.id">
                            <tr class="hover:bg-accent/5" :class="{ 'opacity-50': !r.ok }">
                                <td class="px-4 py-2.5"><x-badge variant="gray" x-text="r.folder"></x-badge></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-gray-500" x-text="r.mailbox"></td>
                                <td class="max-w-[14rem] truncate px-4 py-2.5" x-text="r.from" :title="r.from"></td>
                                <td class="max-w-[14rem] truncate px-4 py-2.5 text-gray-500" x-text="r.to" :title="r.to"></td>
                                <td class="max-w-[24rem] truncate px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100" x-text="r.subject" :title="r.subject"></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-gray-500" x-text="r.dateLabel"></td>
                                <td class="px-4 py-2.5 text-center">
                                    <span x-show="r.hasAttachment"><x-icon name="paper-clip" class="mx-auto h-4 w-4 text-gray-400" /></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div x-show="!loading && lastPage > 1" class="flex items-center justify-between border-t border-black/[0.06] dark:border-white/10 px-4 py-3 text-sm">
                <span class="text-gray-500" x-text="`${total} · ${page}/${lastPage}`"></span>
                <div class="flex gap-2">
                    <x-button variant="secondary" size="sm" ::disabled="page <= 1" @click="goto(page - 1)">{{ __('common.previous') }}</x-button>
                    <x-button variant="secondary" size="sm" ::disabled="page >= lastPage" @click="goto(page + 1)">{{ __('common.next') }}</x-button>
                </div>
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
</x-layouts.app>
