<x-layouts.app :title="__('settings.mail_heading')">
    @php $input = 'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm'; @endphp

    <p class="text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.mail_section') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.mail_heading') }}</h1>

    {{-- Identity + signature management --}}
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('mail.identities.page') }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
            <x-icon name="user" class="h-4 w-4" />{{ __('mail.identities_heading') }}
        </a>
        <a href="{{ route('mail.signatures') }}" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
            <x-icon name="pencil" class="h-4 w-4" />{{ __('mail.signatures_heading') }}
        </a>
    </div>

    {{-- Background-sync interval --}}
    <form method="POST" action="{{ route('settings.mail.update') }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.mail_sync_heading') }}</h2>
        <div class="mt-3 sm:max-w-xs">
            <label for="mail_sync_minutes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('settings.mail_sync_minutes') }}</label>
            <input type="number" min="5" max="{{ $maxSyncMinutes }}" id="mail_sync_minutes" name="mail_sync_minutes"
                value="{{ old('mail_sync_minutes', $settings->mail_sync_minutes ?? 5) }}" class="{{ $input }}">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.mail_sync_minutes_hint') }}</p>
            @error('mail_sync_minutes')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div class="mt-4">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('settings.save') }}</button>
        </div>
    </form>

    {{-- Account management (plain rows; password encrypted at rest) --}}
    <div class="mt-6" x-data="vaultMail({
            stale: @js(__('mail.stale')),
            saveFailed: @js(__('mail.save_failed')),
            connectFailed: @js(__('mail.connect_failed')),
        })">
        <template x-if="state === 'ready'">
          <div>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.mail_accounts_heading') }}</h2>
                <div class="flex items-center gap-2">
                    <button type="button" x-show="manifest.accounts.length" @click="refreshAll()" :disabled="refreshingAll || busyId" title="{{ __('mail.refresh_all') }}" aria-label="{{ __('mail.refresh_all') }}"
                        class="rounded-md border border-gray-300 dark:border-gray-700 p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40"><x-icon name="arrow-path" class="h-5 w-5" ::class="refreshingAll ? 'animate-spin' : ''" /></button>
                    <a href="{{ route('mail.accounts.create') }}" title="{{ __('mail.add_account') }}" aria-label="{{ __('mail.add_account') }}"
                        class="inline-flex rounded-md bg-gray-800 p-2 text-white hover:bg-gray-700"><x-icon name="plus" class="h-5 w-5" /></a>
                </div>
            </div>

            <p x-show="error" x-cloak class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800" x-text="error"></p>

            <template x-if="manifest.accounts.length === 0">
                <p class="mt-4 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400 shadow-sm">{{ __('mail.empty') }}</p>
            </template>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <template x-for="a in sortedAccounts" :key="a.id">
                    <div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 shadow-sm" x-data="{ menu: false, open: false }">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold text-gray-900 dark:text-gray-100" x-text="a.name"></h3>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400"><span x-text="a.username"></span> · <span x-text="a.host"></span>:<span x-text="a.port"></span></p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" @click="refresh(a)" :disabled="busyId" title="{{ __('mail.refresh') }}" aria-label="{{ __('mail.refresh') }}"
                                    class="rounded p-1.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 disabled:opacity-40">
                                    <x-icon name="arrow-path" class="h-4 w-4" ::class="busyId === a.id ? 'animate-spin' : ''" />
                                </button>
                                <div class="relative" @click.outside="menu = false">
                                    <button type="button" @click="menu = ! menu" class="rounded p-1.5 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600"><x-icon name="ellipsis" /></button>
                                    <div x-show="menu" x-cloak class="absolute right-0 z-20 mt-1 w-40 rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-1 text-left text-sm shadow-lg">
                                        <a :href="'/mail/accounts/' + a.id + '/edit'" @click="menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="pencil" />{{ __('mail.edit') }}</a>
                                        <a :href="'/mail/archive/' + a.id + '/download'" @click="menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-gray-700 hover:bg-gray-50"><x-icon name="arrow-down-tray" />{{ __('mail.download_archive') }}</a>
                                        <a :href="'/mail/accounts/' + a.id + '/edit'" @click="menu = false" class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-red-600 dark:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="trash" />{{ __('mail.delete') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p x-show="errors[a.id]" x-cloak class="mt-3 rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-xs text-red-700 dark:text-red-300" x-text="errors[a.id]"></p>

                        <template x-if="accountStats(a)">
                            <div class="mt-4" x-data="{ get s() { return accountStats(a); } }">
                                <div class="grid grid-cols-3 gap-3 text-center">
                                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 p-3">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('mail.stat_total') }}</div>
                                        <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="s.total"></div>
                                    </div>
                                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 p-3">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('mail.stat_unseen') }}</div>
                                        <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="s.unseen"></div>
                                    </div>
                                    <div class="rounded-md bg-gray-50 dark:bg-gray-800 p-3">
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('mail.stat_folders') }}</div>
                                        <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="(s.folders ?? []).length"></div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>{{ __('mail.stat_quota') }}</span>
                                        <span x-show="s.quotaLimit" x-text="@js(__('mail.quota_used_of', ['used' => '%u', 'limit' => '%l'])).replace('%u', fmtBytes(s.quotaUsed)).replace('%l', fmtBytes(s.quotaLimit))"></span>
                                        <span x-show="! s.quotaLimit">{{ __('mail.quota_unavailable') }}</span>
                                    </div>
                                    <div x-show="s.quotaLimit" class="mt-1 h-2 overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                                        <div class="h-2 bg-gray-800" :style="`width: ${quotaPct(s)}%`"></div>
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-gray-400 dark:text-gray-500" x-text="@js(__('mail.fetched_at', ['time' => '%t'])).replace('%t', fmtDateTime(s.fetchedAt))"></p>
                            </div>
                        </template>
                        <template x-if="! accountStats(a)">
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('mail.never_fetched') }}</p>
                        </template>
                    </div>
                </template>
            </div>
          </div>
        </template>

    </div>
</x-layouts.app>
