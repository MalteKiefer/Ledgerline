<x-layouts.app :title="__('pages.profile.title')">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('pages.profile.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ __('pages.profile.subtitle') }}
    </p>

    <div class="mt-6 ll-card">
        <div class="flex items-center gap-4">
            <x-user-avatar :user="$user" size="h-16 w-16" />
            <div class="min-w-0">
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 truncate">{{ $user->email }}</p>
                @if ($user->avatar_url)
                    <form method="POST" action="{{ route('profile.avatar.refresh') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="min-h-11 rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:border-accent hover:text-accent"><span class="inline-flex items-center gap-1.5"><x-icon name="arrow-path" class="h-3.5 w-3.5" />{{ __('pages.profile.refresh_avatar') }}</span></button>
                    </form>
                @endif
            </div>
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 border-t border-gray-100 dark:border-gray-800 pt-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.name') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $user->name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.email') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    @if ($user->email)
                        <a href="mailto:{{ $user->email }}" class="text-gray-900 dark:text-gray-100 hover:underline">{{ $user->email }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.email_verified') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ $user->email_verified_at ? __('pages.profile.verified_yes', ['date' => $user->email_verified_at->format('Y-m-d')]) : __('pages.profile.verified_no') }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.pocketid_subject') }}</dt>
                <dd class="mt-1 break-all font-mono text-sm text-gray-900 dark:text-gray-100">{{ $user->oidc_sub ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.avatar') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ $user->avatar ? __('pages.profile.avatar_provided') : __('pages.profile.avatar_none') }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.account_created') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $user->created_at?->format('Y-m-d H:i') ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Files encryption (zero-knowledge vault): change passphrase / reset via
         recovery code. The panel (included below) drives the modals. --}}
    <div class="mt-6 ll-card">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('vault.settings_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('vault.settings_hint') }}</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <template x-if="$store.vault.configured">
                <div class="flex flex-wrap gap-3">
                    <x-button variant="secondary" type="button" x-on:click="$dispatch('vault-change')">{{ __('vault.change_action') }}</x-button>
                    <x-button variant="secondary" type="button" x-on:click="$dispatch('vault-recover')">{{ __('vault.reset_action') }}</x-button>
                </div>
            </template>
            <template x-if="! $store.vault.configured">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('vault-panel')">{{ __('vault.setup') }}</x-button>
            </template>
        </div>
    </div>
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    {{-- Devices: one card for both the mobile app (QR) and the command-line client
         (copy/paste code). Both share the same approval flow + device cap. --}}
    <div class="mt-6 ll-card" x-data="devicePairing({ rateLimited: @js(__('account.pair_rate_limited')), startFailed: @js(__('account.pair_start_failed')), wipeConfirm: @js(__('account.devices_wipe_confirm')) })">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.devices_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('account.devices_hint') }} {{ __('account.devices_limit_note', ['max' => $deviceMax]) }}</p>

        <div class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-4">
            {{-- Pick how to connect: mobile app (QR) or command-line (code) --}}
            <template x-if="!active">
                <div class="flex flex-wrap gap-2">
                    <button type="button" x-on:click="start('app')" class="inline-flex min-h-11 items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-accent hover:text-accent">
                        <x-icon name="qr-code" class="h-4 w-4" />{{ __('account.devices_connect') }}
                    </button>
                    <button type="button" x-on:click="start('cli')" class="inline-flex min-h-11 items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-accent hover:text-accent">
                        <x-icon name="command-line" class="h-4 w-4" />{{ __('account.cli_connect') }}
                    </button>
                </div>
            </template>

            <template x-if="active">
                <div class="text-sm">
                    {{-- App (QR) --}}
                    <div x-show="method==='app' && (status==='pending_scan' || status==='pending_approval')" class="flex flex-col items-start gap-4 sm:flex-row">
                        <img :src="qr" alt="" class="h-40 w-40 shrink-0 rounded-md border border-gray-200 dark:border-gray-700 bg-white p-1">
                        <p x-show="status==='pending_scan'" class="text-gray-600 dark:text-gray-400">{{ __('account.devices_scan_hint') }}</p>
                    </div>
                    {{-- Command-line (copy/paste code) --}}
                    <div x-show="method==='cli' && (status==='pending_scan' || status==='pending_approval')">
                        <p class="text-gray-600 dark:text-gray-400">{{ __('account.cli_paste_hint') }}</p>
                        <div class="mt-2 flex items-center gap-2">
                            <code x-text="code" class="min-w-0 flex-1 truncate rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 font-mono text-sm text-gray-800 dark:text-gray-200"></code>
                            <button type="button" x-on:click="copyCode()" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-accent hover:text-accent">
                                <x-icon name="clipboard" class="h-4 w-4" /><span x-text="copied ? @js(__('account.cli_copied')) : @js(__('account.cli_copy'))"></span>
                            </button>
                        </div>
                    </div>
                    {{-- Shared: countdown + approval + regenerate while pending --}}
                    <div x-show="status==='pending_scan' || status==='pending_approval'">
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('account.devices_expires_in') }} <span x-text="remainingText" class="font-mono font-medium text-gray-700 dark:text-gray-300"></span></p>
                        <div x-show="status==='pending_approval'" class="mt-3">
                            <p class="text-gray-900 dark:text-gray-100">{{ __('account.devices_approve_q') }} „<span x-text="deviceName" class="font-medium"></span>"?</p>
                            <div class="mt-2 flex gap-2">
                                <button type="button" x-on:click="approve()" class="min-h-11 rounded-md ll-accent px-3 text-sm font-medium shadow-sm shadow-accent/30">{{ __('account.devices_allow') }}</button>
                                <button type="button" x-on:click="reject()" class="min-h-11 rounded-md border border-gray-300 dark:border-gray-700 px-3 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('account.devices_deny') }}</button>
                            </div>
                        </div>
                        <button type="button" x-on:click="regenerate()" class="mt-3 inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"><x-icon name="arrow-path" class="h-3.5 w-3.5" />{{ __('account.devices_regenerate') }}</button>
                    </div>
                    {{-- Shared: terminal states --}}
                    <p x-show="status==='approved' || status==='consumed'" class="font-medium text-gray-900 dark:text-gray-100">{{ __('account.devices_connected') }}</p>
                    <p x-show="status==='rejected'" class="text-gray-600 dark:text-gray-400">{{ __('account.devices_rejected') }}</p>
                    <p x-show="status==='expired'" class="text-gray-600 dark:text-gray-400">{{ __('account.devices_expired') }}</p>
                    <button type="button" x-on:click="reset()" x-show="['approved','consumed','rejected','expired'].includes(status)" class="mt-2 text-xs text-gray-500 underline">{{ __('account.devices_again') }}</button>
                </div>
            </template>
        </div>

        {{-- Paired devices (app + CLI share one list + the device cap) --}}
        <div class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('account.devices_list_heading') }}</h3>
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800" x-show="devices.length">
                <template x-for="d in devices" :key="d.id">
                    <li class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-gray-900 dark:text-gray-100">
                                <span x-text="d.name"></span>
                                <span x-show="d.syncing" class="ml-1 inline-flex items-center gap-1 rounded bg-green-100 dark:bg-green-900/40 px-1.5 py-0.5 text-[11px] font-medium text-green-700 dark:text-green-300"><span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>{{ __('account.devices_syncing') }}</span>
                                <span x-show="d.wipeRequested" class="ml-1 rounded bg-red-100 dark:bg-red-900/40 px-1.5 py-0.5 text-[11px] font-medium text-red-700 dark:text-red-300">{{ __('account.devices_wipe_pending') }}</span>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="d.meta"></p>
                            <p x-show="d.syncing && d.syncDetail" class="text-xs text-green-700 dark:text-green-400" x-text="d.syncDetail"></p>
                            <p x-show="!d.syncing && d.syncSeen" class="text-xs text-gray-400" x-text="'{{ __('account.devices_last_sync') }} ' + d.syncSeen"></p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" x-on:click="wipeDevice(d.id)" x-show="!d.wipeRequested" class="inline-flex min-h-11 items-center rounded-md border border-red-300 dark:border-red-800 px-3 text-sm font-medium text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">{{ __('account.devices_wipe') }}</button>
                            <button type="button" x-on:click="revokeDevice(d.id)" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 dark:border-gray-700 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-accent hover:text-accent">{{ __('account.devices_revoke') }}</button>
                        </div>
                    </li>
                </template>
            </ul>
            <p x-show="!devices.length" class="py-2 text-sm text-gray-500 dark:text-gray-400">{{ __('account.devices_none') }}</p>
        </div>
    </div>

    {{-- Web sessions --}}
    <div class="mt-6 ll-card">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.security_heading') }}</h2>

        <div class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-4">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('account.last_login') }}</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $user->last_login_at?->format('Y-m-d H:i') ?: __('account.never') }}</dd>
        </div>

        <div class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('account.sessions_heading') }}</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('account.sessions_hint') }}</p>
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($sessions as $s)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-gray-900 dark:text-gray-100">{{ $s['agent'] ?: __('account.sessions_unknown') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $s['ip'] ?: '—' }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($s['last_activity'])->diffForHumans() }}
                                @if ($s['current']) · <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('account.sessions_current') }}</span>@endif
                            </p>
                        </div>
                        @unless ($s['current'])
                            <form method="POST" action="{{ route('account.sessions.revoke', $s['id']) }}" class="shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 dark:border-gray-700 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-accent hover:text-accent">{{ __('account.sessions_revoke') }}</button>
                            </form>
                        @endunless
                    </li>
                @empty
                    <li class="py-2 text-sm text-gray-500 dark:text-gray-400">{{ __('account.sessions_none') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Data export --}}
    <div class="mt-6 ll-card">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.export_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('account.export_hint') }}</p>
        <x-button :href="route('account.export')" icon="arrow-down-tray" class="mt-3">{{ __('account.export_button') }}</x-button>
    </div>

    {{-- Danger zone: delete account --}}
    <div class="mt-6 ll-card border-red-300 dark:border-red-800" x-data="{ del: false }">
        <h2 class="text-base font-semibold text-red-700 dark:text-red-300">{{ __('account.delete_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('account.delete_hint') }}</p>
        <x-button variant="danger" icon="trash" class="mt-3" @click="del = true">{{ __('account.delete_button') }}</x-button>

        {{-- Delete confirmation modal --}}
        <div x-show="del" x-cloak class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto p-4"
            role="dialog" aria-modal="true" @keydown.escape.window="del = false">
            <div class="absolute inset-0 bg-gray-900/50" @click="del = false"></div>
            <div class="relative my-16 w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.delete_modal_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('account.delete_modal_warning') }}</p>
                <form method="POST" action="{{ route('account.destroy') }}" class="mt-4 space-y-3">
                    @csrf @method('DELETE')
                    <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('account.delete_confirm_label') }}
                        <input type="text" name="confirmation" required autocomplete="off"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-accent focus:ring-accent">
                    </label>
                    @error('confirmation')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <div class="flex justify-end gap-2">
                        <x-button variant="secondary" type="button" @click="del = false">{{ __('common.cancel') }}</x-button>
                        <x-button variant="danger" type="submit">{{ __('account.delete_button') }}</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
