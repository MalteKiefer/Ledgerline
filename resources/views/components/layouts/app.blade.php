@props(['title' => 'Ledgerline'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="mail-sync-minutes" content="{{ (int) (\App\Models\AppSettings::current()->mail_sync_minutes ?? 5) }}">
    @php $llCal = \App\Models\AppSettings::current(); @endphp
    <meta name="calendar-week-start" content="{{ $llCal->calendar_week_start ?? 'monday' }}">
    <meta name="calendar-week-numbers" content="{{ $llCal->calendar_week_numbers ? '1' : '0' }}">
    <meta name="calendar-default-minutes" content="{{ (int) ($llCal->calendar_default_event_minutes ?? 60) }}">
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
                        ['label' => __('messages.nav.notes'), 'url' => route('notes.index'), 'active' => request()->routeIs('notes.*'),
                            'icon' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125'],
                        ['label' => __('messages.nav.bookmarks'), 'url' => route('bookmarks.index'), 'active' => request()->routeIs('bookmarks.*'),
                            'icon' => 'M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z'],
                        ['label' => __('messages.nav.todos'), 'url' => route('todos.index'), 'active' => request()->routeIs('todos.*'),
                            'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['label' => __('messages.nav.calendar'), 'url' => route('calendar.index'), 'active' => request()->routeIs('calendar.*'),
                            'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
                        ['label' => __('messages.nav.mail'), 'url' => route('mail.index'), 'active' => request()->routeIs('mail.*'),
                            'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75'],
                        ['label' => __('messages.nav.gallery'), 'url' => route('gallery.index'), 'active' => request()->routeIs('gallery.*'),
                            'icon' => 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M18 12h.008v.008H18V12zM2.25 6h19.5A2.25 2.25 0 0124 8.25v10.5A2.25 2.25 0 0121.75 21H2.25A2.25 2.25 0 010 18.75V8.25A2.25 2.25 0 012.25 6z'],
                        ['label' => __('messages.nav.downloads'), 'url' => route('downloads.index'), 'active' => request()->routeIs('downloads.*'),
                            'icon' => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3'],
                        ['label' => __('messages.nav.contacts'), 'url' => route('contacts.index'), 'active' => request()->routeIs('contacts.*'),
                            'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z'],
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

                        {{-- Local notifications bell --}}
                        <div class="relative" x-data="notificationBell({ now: @js(__('common.now')) })" @click.outside="open = false">
                            <button type="button" @click="toggle()" class="relative rounded-md p-2 text-gray-600 hover:bg-gray-50" :aria-label="'{{ __('notifications.title') }}'" title="{{ __('notifications.title') }}">
                                <x-icon name="bell" class="h-5 w-5" />
                                <span x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread"
                                    class="absolute -right-0.5 -top-0.5 min-w-[1.1rem] rounded-full bg-red-500 px-1 text-center text-[10px] font-semibold leading-4 text-white"></span>
                            </button>
                            <div x-show="open" x-cloak class="absolute right-0 z-40 mt-2 w-80 overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg">
                                <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2">
                                    <span class="text-sm font-semibold text-gray-900">{{ __('notifications.title') }}</span>
                                    <button type="button" x-show="unread > 0" @click="markAllRead()" class="text-xs text-gray-500 hover:text-gray-700">{{ __('notifications.mark_all_read') }}</button>
                                </div>
                                <div x-show="desktop !== 'granted' && desktop !== 'unsupported'" x-cloak class="border-b border-gray-100 px-3 py-2">
                                    <button type="button" @click="enableDesktop()" class="text-xs font-medium text-blue-600 hover:text-blue-700">{{ __('notifications.enable_desktop') }}</button>
                                </div>
                                <div class="max-h-96 overflow-y-auto">
                                    <template x-if="items.length === 0">
                                        <p class="px-3 py-6 text-center text-sm text-gray-400">{{ __('notifications.empty') }}</p>
                                    </template>
                                    <template x-for="n in items" :key="n.id">
                                        <button type="button" @click="activate(n)" class="flex w-full items-start gap-2 border-b border-gray-50 px-3 py-2 text-left hover:bg-gray-50" :class="[! n.read ? 'bg-blue-50/40' : '', hrefFor(n) ? 'cursor-pointer' : '']">
                                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="n.level === 'error' ? 'bg-red-500' : (n.level === 'success' ? 'bg-green-500' : 'bg-gray-300')"></span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-medium text-gray-900" x-text="n.title"></span>
                                                <span x-show="n.body" class="block truncate text-xs text-gray-500" x-text="n.body"></span>
                                                <span class="mt-0.5 block text-[10px] uppercase tracking-wide text-gray-400" x-text="fmt(n.at)"></span>
                                            </span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

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
                                    @foreach (config('locales.languages') as $code => $label)
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

            @if (session('error'))
                <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                    role="alert">
                    {{ session('error') }}
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-5xl px-4 py-6 text-center text-xs text-gray-400">
            Ledgerline v{{ config('app.version') }}
        </footer>
    </div>

    @auth
        <div x-data="toastHub(@js(['link' => __('downloads.heading')]))" class="fixed bottom-4 right-4 z-50 space-y-2" x-cloak>
            <template x-for="t in items" :key="t.id">
                <div class="flex items-center gap-3 rounded-md bg-gray-900 px-4 py-3 text-sm text-white shadow-lg">
                    <span x-text="t.message"></span>
                    <template x-if="t.url"><a :href="t.url" class="font-medium underline" x-text="t.linkLabel"></a></template>
                    <button type="button" @click="dismiss(t.id)" class="text-gray-400 hover:text-white" aria-label="close">✕</button>
                </div>
            </template>
        </div>
    @endauth
</body>
</html>
