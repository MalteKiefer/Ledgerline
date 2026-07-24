<x-layouts.app :title="__('account.nav_devices')">
    <div class="mx-auto w-full max-w-3xl" x-data="devicePairing({ rateLimited: @js(__('account.pair_rate_limited')), startFailed: @js(__('account.pair_start_failed')) })">
        @include('profile._header', ['title' => __('account.nav_devices'), 'subtitle' => __('account.devices_hint').' '.__('account.devices_limit_note', ['max' => $deviceMax])])

        {{-- Connect: mobile app (QR) or command-line (code) --}}
        <div class="mt-5 ll-card">
            <template x-if="!active">
                <div class="flex flex-wrap gap-2">
                    <button type="button" x-on:click="start('app')" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-black/[0.08] dark:border-white/10 px-3.5 text-sm font-medium text-gray-700 dark:text-gray-300 transition hover:border-accent hover:text-accent">
                        <x-icon name="qr-code" class="h-4 w-4" />{{ __('account.devices_connect') }}
                    </button>
                    <button type="button" x-on:click="start('cli')" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-black/[0.08] dark:border-white/10 px-3.5 text-sm font-medium text-gray-700 dark:text-gray-300 transition hover:border-accent hover:text-accent">
                        <x-icon name="command-line" class="h-4 w-4" />{{ __('account.cli_connect') }}
                    </button>
                </div>
            </template>

            <template x-if="active">
                <div class="text-sm">
                    <div x-show="method==='app' && (status==='pending_scan' || status==='pending_approval')" class="flex flex-col items-start gap-4 sm:flex-row">
                        <img :src="qr" alt="" class="h-40 w-40 shrink-0 rounded-xl border border-black/[0.08] dark:border-white/10 bg-white p-1">
                        <p x-show="status==='pending_scan'" class="text-gray-600 dark:text-gray-400">{{ __('account.devices_scan_hint') }}</p>
                    </div>
                    <div x-show="method==='cli' && (status==='pending_scan' || status==='pending_approval')">
                        <p class="text-gray-600 dark:text-gray-400">{{ __('account.cli_paste_hint') }}</p>
                        <div class="mt-2 flex items-center gap-2">
                            <code x-text="code" class="min-w-0 flex-1 truncate rounded-xl border border-black/[0.08] dark:border-white/10 bg-gray-50 dark:bg-gray-800 px-3 py-2 font-mono text-sm text-gray-800 dark:text-gray-200"></code>
                            <button type="button" x-on:click="copyCode()" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-xl border border-black/[0.08] dark:border-white/10 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 transition hover:border-accent hover:text-accent">
                                <x-icon name="clipboard" class="h-4 w-4" /><span x-text="copied ? @js(__('account.cli_copied')) : @js(__('account.cli_copy'))"></span>
                            </button>
                        </div>
                    </div>
                    <div x-show="status==='pending_scan' || status==='pending_approval'">
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('account.devices_expires_in') }} <span x-text="remainingText" class="font-mono font-medium text-gray-700 dark:text-gray-300"></span></p>
                        <div x-show="status==='pending_approval'" class="mt-3">
                            <p class="text-gray-900 dark:text-gray-100">{{ __('account.devices_approve_q') }} „<span x-text="deviceName" class="font-medium"></span>"?</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" x-on:click="approve()" class="min-h-11 rounded-xl ll-accent px-4 text-sm font-medium shadow-sm shadow-accent/30">{{ __('account.devices_allow') }}</button>
                                <button type="button" x-on:click="reject()" class="min-h-11 rounded-xl border border-black/[0.08] dark:border-white/10 px-4 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('account.devices_deny') }}</button>
                            </div>
                        </div>
                        <button type="button" x-on:click="regenerate()" class="mt-3 inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="arrow-path" class="h-3.5 w-3.5" />{{ __('account.devices_regenerate') }}</button>
                    </div>
                    <p x-show="status==='approved' || status==='consumed'" class="font-medium text-gray-900 dark:text-gray-100">{{ __('account.devices_connected') }}</p>
                    <p x-show="status==='rejected'" class="text-gray-600 dark:text-gray-400">{{ __('account.devices_rejected') }}</p>
                    <p x-show="status==='expired'" class="text-gray-600 dark:text-gray-400">{{ __('account.devices_expired') }}</p>
                    <button type="button" x-on:click="reset()" x-show="['approved','consumed','rejected','expired'].includes(status)" class="mt-2 text-xs text-gray-500 underline">{{ __('account.devices_again') }}</button>
                </div>
            </template>
        </div>

        {{-- Paired devices (app + CLI share one list) --}}
        <h2 class="mt-6 mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('account.devices_list_heading') }}</h2>
        <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10" x-show="devices.length">
            <template x-for="d in devices" :key="d.id">
                <div class="flex items-center gap-3.5 px-4 py-3">
                    <span class="ll-chip h-9 w-9 shrink-0" style="--chip: #3b9fd6"><x-icon name="device-phone-mobile" class="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-1.5 truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                            <span class="truncate" x-text="d.name"></span>
                            <span x-show="d.syncing" class="inline-flex shrink-0 items-center gap-1 rounded-full bg-green-100 dark:bg-green-900/40 px-1.5 py-0.5 text-[11px] font-medium text-green-700 dark:text-green-300"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>{{ __('account.devices_syncing') }}</span>
                            <span x-show="d.wipeRequested" class="shrink-0 rounded-full bg-red-100 dark:bg-red-900/40 px-1.5 py-0.5 text-[11px] font-medium text-red-700 dark:text-red-300">{{ __('account.devices_wipe_pending') }}</span>
                        </p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="d.meta"></p>
                        <p x-show="d.version || d.installId" class="truncate text-xs text-gray-400 dark:text-gray-500"
                           x-text="[d.version, d.installId ? '#' + d.installId : null].filter(Boolean).join(' · ')"></p>
                        <p x-show="d.syncing && d.syncDetail" class="truncate text-xs text-green-700 dark:text-green-400" x-text="d.syncDetail"></p>
                        <p x-show="!d.syncing && d.syncSeen" class="truncate text-xs text-gray-400" x-text="'{{ __('account.devices_last_sync') }} ' + d.syncSeen"></p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" x-on:click="wipeDevice(d.id)" x-show="!d.wipeRequested" :title="@js(__('account.devices_wipe'))" aria-label="{{ __('account.devices_wipe') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-red-600 dark:text-red-400 transition hover:bg-red-50 dark:hover:bg-red-900/30">
                            <x-icon name="exclamation-triangle" class="h-5 w-5" />
                        </button>
                        <button type="button" x-on:click="revokeDevice(d.id)" :title="@js(__('account.devices_revoke'))" aria-label="{{ __('account.devices_revoke') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 dark:text-gray-500 transition hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-red-600 dark:hover:text-red-400">
                            <x-icon name="trash" class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </template>
        </div>
        <p x-show="!devices.length" class="px-1 py-2 text-sm text-gray-500 dark:text-gray-400">{{ __('account.devices_none') }}</p>
    </div>
</x-layouts.app>
