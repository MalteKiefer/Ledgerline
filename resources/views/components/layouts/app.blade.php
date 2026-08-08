@props(['title' => 'Ledgerline'])
<!DOCTYPE html>
@php $llCal = auth()->check() ? \App\Models\UserSetting::for(auth()->id()) : new \App\Models\UserSetting; @endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-theme="{{ $llCal->theme ?? 'system' }}">
<head>
    <meta charset="utf-8">
    {{-- Apply the dark class before first paint; hash-allowed in the CSP. --}}
    <script>{!! \App\Support\ThemeBootstrap::SCRIPT !!}</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="ll-prefs" content="{{ json_encode($llCal->displayPrefs()) }}">
    <title>{{ $title }} — Ledgerline</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#7066f5">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Ledgerline">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
</head>
<body class="h-full bg-md-surface-2 text-md-on-surface antialiased dark:bg-gray-950 dark:text-md-on-surface" x-data>
    <div class="min-h-full">
        <header class="border-b border-md-outline-variant bg-white dark:border-md-outline-variant dark:bg-md-surface-2">
            {{-- Desktop persistent top bar --}}
            <x-nav />

            {{-- Mobile top strip: hamburger + brand + bell. All navigation
                 lives in the hamburger drawer (x-mobile-nav). --}}
            <div class="mx-auto flex w-full max-w-[1700px] items-center justify-between px-4 py-3 sm:hidden">
                <div class="flex items-center gap-1">
                    @auth
                        <button type="button" @click="$store.nav.toggleNav()" aria-label="{{ __('pages.menu.toggle_menu') }}"
                            class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-md-on-surface-var hover:bg-md-surface-2 dark:text-md-on-surface-var dark:hover:bg-gray-800">
                            <x-icon name="bars-3" class="h-6 w-6" />
                        </button>
                    @endauth
                    <a href="{{ route('finance.index') }}" class="text-lg font-semibold text-md-on-surface dark:text-md-on-surface">Ledgerline</a>
                </div>
                @auth
                    <div class="flex items-center gap-1">
                        <div class="relative" x-data="notificationBell({ now: @js(__('common.now')) })" @click.outside="open = false">
                            <button type="button" @click="toggle()" class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-md-on-surface-var hover:bg-md-surface-2 dark:text-md-on-surface-var dark:hover:bg-gray-800" :aria-label="'{{ __('notifications.title') }}'" title="{{ __('notifications.title') }}">
                                <x-icon name="bell" class="h-5 w-5" />
                                <span x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread"
                                    class="absolute right-1 top-1 min-w-[1.1rem] rounded-full bg-red-500 px-1 text-center text-[10px] font-semibold leading-4 text-white"></span>
                            </button>
                            <x-notification-panel />
                        </div>
                    </div>
                @endauth
            </div>
        </header>

        {{-- overflow-x-clip (not -hidden): `overflow-x:hidden` forces the browser to
             compute overflow-y:auto, turning <main> into a vertical scroll container
             that clips absolutely-positioned dropdowns (e.g. the payment "Add" menu)
             below the fold. `clip` prevents horizontal scroll without that side effect. --}}
        <main class="mx-auto w-full max-w-[1700px] overflow-x-clip px-4 py-8 sm:w-[92%] sm:px-6">
            @php
                // Fortify emits machine status SLUGS (e.g. "password-updated"); translate
                // the known ones to a localized message and NEVER dump a raw slug. A
                // human-readable flash (a controller message with spaces) shows as-is.
                $status = session('status');
                $fortifyStatus = [
                    'password-updated' => __('account.password_updated'),
                    'two-factor-authentication-enabled' => __('account.twofa_enabled'),
                    'two-factor-authentication-confirmed' => __('account.twofa_enabled'),
                    'two-factor-authentication-disabled' => __('account.twofa_disabled'),
                    'recovery-codes-generated' => __('account.twofa_recovery_regenerated'),
                    'two-factor-recovery-codes-generated' => __('account.twofa_recovery_regenerated'),
                    'verification-link-sent' => __('account.verify_sent'),
                    'profile-information-updated' => __('account.saved'),
                ];
                $statusMsg = $status === null ? null
                    : ($fortifyStatus[$status] ?? (preg_match('/^[a-z0-9-]+$/', (string) $status) ? null : $status));
            @endphp
            @if ($statusMsg)
                <x-alert variant="success" class="mb-6" role="status">{{ $statusMsg }}</x-alert>
            @endif

            @if (session('error'))
                <x-alert variant="error" class="mb-6" role="alert">{{ session('error') }}</x-alert>
            @endif

            {{ $slot }}
        </main>

        <footer class="mx-auto w-full max-w-[1700px] px-4 py-6 text-center text-xs text-md-on-surface-var dark:text-md-on-surface-var sm:w-[92%] sm:px-6">
            Ledgerline v{{ config('app.version') }}
        </footer>
    </div>

    {{-- Mobile navigation drawer (hamburger) --}}
    <x-mobile-nav />

    {{-- App-wide confirm/prompt modal (replaces window.confirm & window.prompt) --}}
    <div x-data x-show="$store.confirm.open" x-cloak class="fixed inset-0 z-[1700] flex items-center justify-center overflow-y-auto p-4"
        role="dialog" aria-modal="true" @keydown.escape.window="$store.confirm.no()">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="$store.confirm.no()"></div>
        <div class="relative w-full max-w-sm rounded-xl bg-white p-5 shadow-xl ring-1 ring-black/[0.06] dark:bg-md-surface dark:ring-white/10"
            x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            @keydown.enter.stop="$store.confirm.isPrompt && $store.confirm.input.trim() && $store.confirm.yes()">
            <h3 class="text-base font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('common.confirm_title') }}</h3>
            <p x-show="$store.confirm.message" class="mt-2 text-sm text-md-on-surface-var dark:text-md-on-surface-var" x-text="$store.confirm.message || '{{ __('common.confirm_message') }}'"></p>
            <template x-if="$store.confirm.isPrompt">
                <input type="text" x-model="$store.confirm.input" :placeholder="$store.confirm.placeholder"
                    x-effect="if ($store.confirm.open && $store.confirm.isPrompt) $nextTick(() => $el.focus())"
                    class="mt-3 w-full rounded-xl border-md-outline-variant bg-white py-2 px-3 text-sm shadow-sm focus:border-accent focus:ring-accent dark:border-md-outline-variant dark:bg-md-surface-2 dark:text-md-on-surface">
            </template>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="$store.confirm.no()" class="rounded-xl border border-md-outline-variant px-4 py-2 text-sm font-medium text-md-on-surface-var transition hover:border-accent hover:text-accent hover:bg-accent/5 dark:border-md-outline-variant dark:text-md-on-surface-var">{{ __('common.cancel') }}</button>
                <button type="button" @click="$store.confirm.yes()" :disabled="$store.confirm.isPrompt && ! $store.confirm.input.trim()"
                    :class="$store.confirm.isPrompt ? 'll-accent shadow-sm shadow-accent/30 hover:brightness-105' : 'bg-red-600 hover:bg-red-700'"
                    class="rounded-xl px-4 py-2 text-sm font-semibold text-white transition disabled:opacity-50">
                    <span x-text="$store.confirm.okLabel || '{{ __('common.confirm') }}'"></span>
                </button>
            </div>
        </div>
    </div>

    @auth
        <div x-data="toastHub({})" class="fixed bottom-4 right-4 z-[2000] space-y-2" x-cloak>
            <template x-for="t in items" :key="t.id">
                <div class="flex items-center gap-3 rounded-xl bg-gray-900 px-4 py-3 text-sm text-white shadow-lg">
                    <span x-text="t.message"></span>
                    <template x-if="t.url"><a :href="t.url" class="font-medium underline" x-text="t.linkLabel"></a></template>
                    <button type="button" @click="dismiss(t.id)" class="text-md-on-surface-var hover:text-white" aria-label="{{ __('common.close') }}"><x-icon name="x-mark" class="h-4 w-4" /></button>
                </div>
            </template>
        </div>

        {{-- Shared square-crop modal (avatar upload). window.llCrop(blob) resolves to a JPEG Uint8Array. --}}
        <div x-data="cropModal()" x-show="open" x-cloak class="fixed inset-0 z-[1120] flex items-center justify-center p-4" @keydown.escape.window="cancel()">
            <div class="absolute inset-0 bg-gray-900/60" @click="cancel()"></div>
            <div class="relative w-full max-w-sm rounded-xl bg-white dark:bg-md-surface-2 p-4 shadow-xl">
                <h3 class="text-base font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('pages.profile.crop_title') }}</h3>
                <p class="mt-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('pages.profile.crop_hint') }}</p>
                <div class="mx-auto mt-3 overflow-hidden rounded-full bg-md-surface-2 dark:bg-md-surface-2 select-none touch-none"
                     style="width:300px;height:300px;position:relative;cursor:grab"
                     @pointerdown="startDrag($event); $event.target.setPointerCapture?.($event.pointerId)" @pointermove="onDrag($event)" @pointerup="endDrag()" @pointercancel="endDrag()">
                    <img :src="url" :style="'position:absolute;left:0;top:0;max-width:none;'+imgStyle()" draggable="false" alt="">
                    <div class="pointer-events-none absolute inset-0 rounded-full ring-1 ring-black/10"></div>
                </div>
                <input type="range" min="1" max="8" step="0.01" :value="scale/minScale" @input="setScale(minScale * $event.target.value)" class="mt-3 w-full">
                <div class="mt-3 flex justify-end gap-2">
                    <x-button variant="secondary" type="button" @click="cancel()">{{ __('common.cancel') }}</x-button>
                    <x-button type="button" @click="confirm()">{{ __('common.save') }}</x-button>
                </div>
            </div>
        </div>
    @endauth
</body>
</html>
