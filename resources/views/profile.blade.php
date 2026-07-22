<x-layouts.app :title="__('pages.profile.title')">
    @php
        $initials = collect(explode(' ', trim($user->name ?? '')))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
        $initials = $initials !== '' ? $initials : '•';
        // Human-readable byte size for the storage stat (binary units, 1 decimal).
        $hb = function (int $b): string {
            if ($b <= 0) return '0 B';
            $u = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = (int) floor(log($b, 1024));
            $i = min($i, count($u) - 1);
            $v = $b / (1024 ** $i);
            return ($i === 0 ? (string) $b : rtrim(rtrim(number_format($v, 1), '0'), '.')).' '.$u[$i];
        };
    @endphp

    <div class="mx-auto w-full max-w-3xl">

    {{-- iOS-style hero header: gradient avatar, name, email, host --}}
    <div class="flex flex-col items-center pt-2 text-center">
        @if ($user->avatar)
            <img src="{{ route('profile.avatar') }}" alt="" class="h-20 w-20 rounded-full object-cover shadow-lg shadow-accent/30">
        @else
            <span class="flex h-20 w-20 items-center justify-center rounded-full ll-accent text-2xl font-semibold shadow-lg shadow-accent/30">{{ $initials }}</span>
        @endif
        <p class="mt-3 text-xl font-bold text-gray-900 dark:text-gray-100">{{ $user->name ?: '—' }}</p>
        @if ($user->email)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
        @endif
        <p class="mt-1 inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500"><x-icon name="lock-closed" class="h-3.5 w-3.5" />{{ request()->getHost() }}</p>
        @if ($user->avatar_url)
            <form method="POST" action="{{ route('profile.avatar.refresh') }}" class="mt-3">
                @csrf
                <button type="submit" class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium text-accent transition hover:bg-accent/10"><x-icon name="arrow-path" class="h-3.5 w-3.5" />{{ __('pages.profile.refresh_avatar') }}</button>
            </form>
        @endif
    </div>

    {{-- Stats band: at-a-glance account figures as iOS stat tiles. Values are a
         page-load snapshot; the live device list below updates on its own. --}}
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @php
            $memberSince = $user->created_at?->translatedFormat('M Y') ?: '—';
            $storageValue = $hb($storageUsed);
            $storageSub = $storageQuota > 0 ? __('account.storage_of', ['quota' => $hb($storageQuota)]) : __('account.storage_unlimited');
            $tiles = [
                ['icon' => 'calendar', 'tint' => '#3fae9f', 'value' => $memberSince, 'label' => __('account.stat_member_since')],
                ['icon' => 'device-phone-mobile', 'tint' => '#3b9fd6', 'value' => (string) $deviceCount, 'label' => __('account.stat_devices'), 'sub' => __('account.stat_devices_of', ['n' => $deviceCount, 'max' => $deviceMax])],
                ['icon' => 'computer-desktop', 'tint' => '#9e70fa', 'value' => (string) count($sessions), 'label' => __('account.stat_sessions')],
                ['icon' => 'circle-stack', 'tint' => '#d9a441', 'value' => $storageValue, 'label' => __('account.stat_storage'), 'sub' => $storageSub],
            ];
        @endphp
        @foreach ($tiles as $t)
            <div class="ll-card flex flex-col gap-2 !p-4">
                <span class="ll-chip h-9 w-9" style="--chip: {{ $t['tint'] }}"><x-icon name="{{ $t['icon'] }}" class="h-5 w-5" /></span>
                <div class="min-w-0">
                    <p class="truncate text-lg font-bold leading-tight text-gray-900 dark:text-gray-100">{{ $t['value'] }}</p>
                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $t['label'] }}</p>
                    @isset($t['sub'])
                        <p class="truncate text-[11px] text-gray-400 dark:text-gray-500">{{ $t['sub'] }}</p>
                    @endisset
                </div>
            </div>
        @endforeach
    </div>

    {{-- Health module lives in its own ZK area; the profile only links to it. --}}
    <a href="{{ route('health.index') }}" class="group mt-6 ll-card flex items-center gap-3.5 !py-3.5 transition hover:border-accent">
        <span class="ll-chip" style="--chip: #ef4444"><x-icon name="heart" class="h-5 w-5" /></span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('pages.profile.health_title') }}</span>
            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ __('pages.profile.health_desc') }}</span>
        </span>
        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600 transition group-hover:text-accent" />
    </a>

    {{-- Account identity (read-only, owned by Pocket-ID) as an iOS grouped list. --}}
    <h2 class="mt-7 mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('pages.profile.title') }}</h2>
    <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
        @php
            $rows = [
                ['icon' => 'user', 'tint' => '#7066f5', 'label' => __('pages.profile.name'), 'value' => $user->name ?: '—'],
                ['icon' => 'envelope', 'tint' => '#3b9fd6', 'label' => __('pages.profile.email'), 'value' => $user->email ?: '—'],
                ['icon' => 'shield-check', 'tint' => '#59ad6b', 'label' => __('pages.profile.email_verified'), 'value' => $user->email_verified_at ? __('pages.profile.verified_yes', ['date' => $user->email_verified_at->format('Y-m-d')]) : __('pages.profile.verified_no')],
                ['icon' => 'finger-print', 'tint' => '#6b7280', 'label' => __('pages.profile.pocketid_subject'), 'value' => $user->oidc_sub ?: '—', 'mono' => true],
                ['icon' => 'clock', 'tint' => '#3fae9f', 'label' => __('pages.profile.account_created'), 'value' => $user->created_at?->format('Y-m-d H:i') ?: '—'],
            ];
        @endphp
        @foreach ($rows as $r)
            <div class="flex items-center gap-3.5 px-4 py-3">
                <span class="ll-chip h-8 w-8 shrink-0" style="--chip: {{ $r['tint'] }}"><x-icon name="{{ $r['icon'] }}" class="h-4 w-4" /></span>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $r['label'] }}</span>
                <span class="ml-auto min-w-0 truncate text-right text-sm {{ ($r['mono'] ?? false) ? 'font-mono text-xs' : '' }} text-gray-900 dark:text-gray-100">{{ $r['value'] }}</span>
            </div>
        @endforeach
    </div>

    {{-- Files encryption (zero-knowledge vault): change passphrase / reset. --}}
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

    {{-- Devices: mobile app (QR) + command-line client (copy/paste code). Both
         share the same approval flow + device cap. --}}
    <div class="mt-6 ll-card" x-data="devicePairing({ rateLimited: @js(__('account.pair_rate_limited')), startFailed: @js(__('account.pair_start_failed')) })">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.devices_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('account.devices_hint') }} {{ __('account.devices_limit_note', ['max' => $deviceMax]) }}</p>

        <div class="mt-4 border-t border-black/[0.06] dark:border-white/10 pt-4">
            {{-- Pick how to connect: mobile app (QR) or command-line (code) --}}
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
                    {{-- App (QR) --}}
                    <div x-show="method==='app' && (status==='pending_scan' || status==='pending_approval')" class="flex flex-col items-start gap-4 sm:flex-row">
                        <img :src="qr" alt="" class="h-40 w-40 shrink-0 rounded-xl border border-black/[0.08] dark:border-white/10 bg-white p-1">
                        <p x-show="status==='pending_scan'" class="text-gray-600 dark:text-gray-400">{{ __('account.devices_scan_hint') }}</p>
                    </div>
                    {{-- Command-line (copy/paste code) --}}
                    <div x-show="method==='cli' && (status==='pending_scan' || status==='pending_approval')">
                        <p class="text-gray-600 dark:text-gray-400">{{ __('account.cli_paste_hint') }}</p>
                        <div class="mt-2 flex items-center gap-2">
                            <code x-text="code" class="min-w-0 flex-1 truncate rounded-xl border border-black/[0.08] dark:border-white/10 bg-gray-50 dark:bg-gray-800 px-3 py-2 font-mono text-sm text-gray-800 dark:text-gray-200"></code>
                            <button type="button" x-on:click="copyCode()" class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-xl border border-black/[0.08] dark:border-white/10 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 transition hover:border-accent hover:text-accent">
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
                                <button type="button" x-on:click="approve()" class="min-h-11 rounded-xl ll-accent px-4 text-sm font-medium shadow-sm shadow-accent/30">{{ __('account.devices_allow') }}</button>
                                <button type="button" x-on:click="reject()" class="min-h-11 rounded-xl border border-black/[0.08] dark:border-white/10 px-4 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('account.devices_deny') }}</button>
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

        {{-- Paired devices as an iOS grouped list (app + CLI share one list). --}}
        <div class="mt-5">
            <h3 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('account.devices_list_heading') }}</h3>
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
    </div>

    {{-- Web sessions --}}
    <div class="mt-6 ll-card">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.security_heading') }}</h2>

        <div class="mt-4 flex items-center gap-3.5 border-t border-black/[0.06] dark:border-white/10 pt-4">
            <span class="ll-chip h-8 w-8 shrink-0" style="--chip: #3fae9f"><x-icon name="clock" class="h-4 w-4" /></span>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('account.last_login') }}</span>
            <span class="ml-auto text-sm text-gray-900 dark:text-gray-100">{{ $user->last_login_at?->format('Y-m-d H:i') ?: __('account.never') }}</span>
        </div>

        <div class="mt-5">
            <h3 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('account.sessions_heading') }}</h3>
            <p class="mb-2 px-1 text-xs text-gray-500 dark:text-gray-400">{{ __('account.sessions_hint') }}</p>
            <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
                @forelse ($sessions as $s)
                    <div class="flex items-center gap-3.5 px-4 py-3">
                        <span class="ll-chip h-9 w-9 shrink-0" style="--chip: #9e70fa"><x-icon name="computer-desktop" class="h-5 w-5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $s['agent'] ?: __('account.sessions_unknown') }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $s['ip'] ?: '—' }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($s['last_activity'])->diffForHumans() }}
                                @if ($s['current']) · <span class="font-medium text-accent">{{ __('account.sessions_current') }}</span>@endif
                            </p>
                        </div>
                        @unless ($s['current'])
                            <form method="POST" action="{{ route('account.sessions.revoke', $s['id']) }}" class="shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" :title="@js(__('account.sessions_revoke'))" aria-label="{{ __('account.sessions_revoke') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 dark:text-gray-500 transition hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-red-600 dark:hover:text-red-400">
                                    <x-icon name="x-mark" class="h-5 w-5" />
                                </button>
                            </form>
                        @endunless
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ __('account.sessions_none') }}</p>
                @endforelse
            </div>
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
            <div class="relative my-16 w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.delete_modal_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('account.delete_modal_warning') }}</p>
                <form method="POST" action="{{ route('account.destroy') }}" class="mt-4 space-y-3">
                    @csrf @method('DELETE')
                    <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('account.delete_confirm_label') }}
                        <input type="text" name="confirmation" required autocomplete="off"
                            class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-accent focus:ring-accent">
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

    </div>
</x-layouts.app>
