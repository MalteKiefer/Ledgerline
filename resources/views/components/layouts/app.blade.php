@props(['title' => 'Ledgerline'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        // Per-login token the browser binds the cached vault key to. Seed it for
        // sessions that predate this so the key stays valid within one login.
        $vaultOwner = '';
        if (auth()->check()) {
            if (empty(session('vault_owner'))) {
                session(['vault_owner' => bin2hex(random_bytes(16))]);
            }
            $vaultOwner = (string) session('vault_owner');
        }
    @endphp
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vault-idle-minutes" content="{{ (int) (\App\Models\CompanyProfile::current()->vault_idle_minutes ?? 10) }}">
    <meta name="vault-owner" content="{{ $vaultOwner }}">
    <title>{{ $title }} — Ledgerline</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100 text-gray-900 antialiased">
    <div class="min-h-full">
        <header class="border-b border-gray-200 bg-white" x-data="{ mobileOpen: false }">
            <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
                @php
                    $navItems = [
                        ['label' => __('messages.nav.files'), 'url' => route('files.index'), 'active' => request()->routeIs('files.*'),
                            'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                        ['label' => __('messages.nav.gallery'), 'url' => route('gallery.index'), 'active' => request()->routeIs('gallery.*'),
                            'icon' => 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 12h.008v.008H18V12zM2.25 6h19.5A2.25 2.25 0 0124 8.25v10.5A2.25 2.25 0 0121.75 21H2.25A2.25 2.25 0 010 18.75V8.25A2.25 2.25 0 012.25 6z'],
                    ];
                @endphp
                <div class="flex items-center gap-8">
                    <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-gray-900">Ledgerline</a>
                    @auth
                        <div class="hidden items-center gap-1 sm:flex">
                            @foreach ($navItems as $item)
                                <a href="{{ $item['url'] }}"
                                    @class([
                                        'flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium',
                                        'bg-gray-100 text-gray-900' => $item['active'],
                                        'text-gray-600 hover:bg-gray-50 hover:text-gray-900' => ! $item['active'],
                                    ])>
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                                    </svg>
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endauth
                </div>

                @auth
                    @php
                        $currentUser = auth()->user();
                        $vaultConfigured = \App\Models\Vault::current() !== null;
                    @endphp
                    <div class="flex items-center gap-3">
                        <button type="button" @click="mobileOpen = ! mobileOpen" aria-label="{{ __('pages.menu.toggle_menu') }}"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-50 sm:hidden">
                            <svg x-show="! mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <x-spotlight-search />

                        {{-- Encryption vault: status trigger + setup/unlock modal (global) --}}
                        @include('vault._panel', ['serverConfigured' => $vaultConfigured])

                        {{-- User menu --}}
                        <div class="relative" x-data="{ open: false }">
                            <button type="button" @click="open = ! open" @keydown.escape="open = false"
                                class="flex items-center gap-2 rounded-md px-1.5 py-1 text-sm text-gray-700 hover:bg-gray-50">
                                <x-user-avatar :user="$currentUser" size="h-8 w-8" />
                                <span class="hidden sm:inline">{{ $currentUser->name }}</span>
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" x-cloak @click.outside="open = false"
                                class="absolute right-0 z-40 mt-2 w-48 overflow-hidden rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __("messages.menu.profile") }}</a>
                                <a href="{{ route('settings') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ __("messages.menu.settings") }}</a>
                                <div class="flex gap-1 border-t border-gray-100 px-4 py-2">
                                    @foreach (config('finance.languages') as $code => $label)
                                        <form method="POST" action="{{ route('locale.update') }}">
                                            @csrf
                                            <input type="hidden" name="locale" value="{{ $code }}">
                                            <button type="submit"
                                                @class([
                                                    'rounded px-2 py-1 text-xs font-medium',
                                                    'bg-gray-800 text-white' => app()->getLocale() === $code,
                                                    'text-gray-600 hover:bg-gray-100' => app()->getLocale() !== $code,
                                                ])>{{ strtoupper($code) }}</button>
                                        </form>
                                    @endforeach
                                </div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                                        {{ __('messages.menu.logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endauth
            </nav>

            @auth
                {{-- Mobile navigation panel --}}
                <div x-show="mobileOpen" x-cloak class="border-t border-gray-200 bg-white sm:hidden">
                    <div class="space-y-1 px-4 py-3">
                        @foreach ($navItems as $item)
                            <a href="{{ $item['url'] }}" @click="mobileOpen = false"
                                @class([
                                    'flex items-center gap-2 rounded-md px-3 py-2 text-base font-medium',
                                    'bg-gray-100 text-gray-900' => $item['active'],
                                    'text-gray-700 hover:bg-gray-50' => ! $item['active'],
                                ])>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                                </svg>
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endauth
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            @if (session('status'))
                <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                    role="status">
                    {{ session('status') }}
                </div>
            @endif

            @auth
                @if (! ($vaultConfigured ?? true))
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            {{ __('vault.not_set_up_notice') }}
                        </span>
                        <button type="button" @click="window.dispatchEvent(new CustomEvent('vault-panel'))"
                            class="rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">{{ __('vault.setup') }}</button>
                    </div>
                @endif
            @endauth

            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-5xl px-4 py-6 text-center text-xs text-gray-400">
            Ledgerline v{{ config('app.version') }}
        </footer>
    </div>
</body>
</html>
