@props(['title' => 'Ledgerline'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Ledgerline</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100 text-gray-900 antialiased">
    <div class="min-h-full">
        <header class="border-b border-gray-200 bg-white" x-data="{ mobileOpen: false }">
            <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
                @php
                    $navItems = [
                        ['label' => 'Dashboard', 'url' => route('dashboard'), 'active' => request()->routeIs('dashboard'),
                            'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                        ['label' => 'Customers', 'url' => route('customers.index'), 'active' => request()->routeIs('customers.*'),
                            'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z'],
                        ['label' => 'Projects', 'url' => route('projects.overview'), 'active' => request()->routeIs('projects.*'),
                            'icon' => 'M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z'],
                        ['label' => 'Files', 'url' => route('files.index'), 'active' => request()->routeIs('files.*'),
                            'icon' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                        ['label' => 'Finance', 'url' => route('finance.expenses.index'), 'active' => request()->routeIs('finance.*'),
                            'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m-6-4h8m0 0l-3-3m3 3l-3 3'],
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
                    @endphp
                    <div class="flex items-center gap-3">
                        <button type="button" @click="mobileOpen = ! mobileOpen" aria-label="Toggle menu"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-50 sm:hidden">
                            <svg x-show="! mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <x-spotlight-search />

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
                                <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Profile</a>
                                <a href="{{ route('settings') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Settings</a>
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
                                        Log out
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

            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-5xl px-4 py-6 text-center text-xs text-gray-400">
            Ledgerline v{{ config('app.version') }}
        </footer>
    </div>
</body>
</html>
