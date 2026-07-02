<x-layouts.app :title="__('mail.title')">
  <div x-data="vaultMail({
        stale: @js(__('mail.stale')),
        saveFailed: @js(__('mail.save_failed')),
        connectFailed: @js(__('mail.connect_failed')),
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
                <button type="button" x-show="manifest.accounts.length" @click="refreshAll()" title="{{ __('mail.refresh_all') }}" aria-label="{{ __('mail.refresh_all') }}"
                    class="rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50"><x-icon name="arrow-path" class="h-5 w-5" /></button>
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

                    {{-- Stats --}}
                    <template x-if="a.stats">
                        <div class="mt-4">
                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div class="rounded-md bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('mail.stat_total') }}</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900" x-text="a.stats.total"></div>
                                </div>
                                <div class="rounded-md bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('mail.stat_unseen') }}</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900" x-text="a.stats.unseen"></div>
                                </div>
                                <div class="rounded-md bg-gray-50 p-3">
                                    <div class="text-xs text-gray-500">{{ __('mail.stat_folders') }}</div>
                                    <div class="mt-1 text-xl font-semibold text-gray-900" x-text="(a.stats.folders ?? []).length"></div>
                                </div>
                            </div>

                            {{-- Quota --}}
                            <div class="mt-3">
                                <div class="flex items-center justify-between text-xs text-gray-500">
                                    <span>{{ __('mail.stat_quota') }}</span>
                                    <span x-show="a.stats.quotaLimit" x-text="@js(__('mail.quota_used_of', ['used' => '%u', 'limit' => '%l'])).replace('%u', fmtBytes(a.stats.quotaUsed)).replace('%l', fmtBytes(a.stats.quotaLimit))"></span>
                                    <span x-show="! a.stats.quotaLimit">{{ __('mail.quota_unavailable') }}</span>
                                </div>
                                <div x-show="a.stats.quotaLimit" class="mt-1 h-2 overflow-hidden rounded bg-gray-100">
                                    <div class="h-2 bg-gray-800" :style="`width: ${quotaPct(a.stats)}%`"></div>
                                </div>
                            </div>

                            {{-- Folders --}}
                            <button type="button" @click="open = ! open" class="mt-3 text-xs text-gray-500 hover:text-gray-700" x-text="open ? '▾ {{ __('mail.stat_folders') }}' : '▸ {{ __('mail.stat_folders') }}'"></button>
                            <ul x-show="open" x-cloak class="mt-2 max-h-48 space-y-1 overflow-y-auto text-xs">
                                <template x-for="f in sortedFolders(a.stats.folders)" :key="f.name">
                                    <li class="flex items-center justify-between gap-2 border-b border-gray-50 py-1">
                                        <span class="min-w-0 truncate text-gray-700" x-text="f.name"></span>
                                        <span class="shrink-0 text-gray-400"><span x-text="f.total"></span> · <span x-text="f.unseen"></span></span>
                                    </li>
                                </template>
                            </ul>

                            <p class="mt-3 text-xs text-gray-400" x-text="@js(__('mail.fetched_at', ['time' => '%t'])).replace('%t', fmtDateTime(a.stats.fetchedAt))"></p>
                        </div>
                    </template>
                    <template x-if="! a.stats">
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
  </div>
</x-layouts.app>
