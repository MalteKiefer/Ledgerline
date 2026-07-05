@props(['title' => 'Ledgerline'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="mail-sync-minutes" content="{{ (int) (\App\Models\AppSettings::current()->mail_sync_minutes ?? 5) }}">
    @php $llCal = auth()->check() ? \App\Models\UserSetting::for(auth()->id()) : new \App\Models\UserSetting; @endphp
    <meta name="calendar-week-start" content="{{ $llCal->calendar_week_start ?? 'monday' }}">
    <meta name="calendar-week-numbers" content="{{ $llCal->calendar_week_numbers ? '1' : '0' }}">
    <meta name="calendar-default-minutes" content="{{ (int) ($llCal->calendar_default_event_minutes ?? 60) }}">
    <meta name="calendar-timezone" content="{{ $llCal->calendar_timezone ?? '' }}">
    <meta name="gallery-columns" content="{{ (int) ($llCal->gallery_columns ?? 6) }}">
    <title>{{ $title }} — Ledgerline</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100 text-gray-900 antialiased" x-data>
    <div class="min-h-full">
        <header class="border-b border-gray-200 bg-white">
            {{-- Desktop persistent top bar --}}
            <x-nav />

            {{-- Mobile top strip: hamburger + brand + spotlight + bell. All
                 navigation lives in the hamburger drawer (x-mobile-nav). --}}
            <div class="mx-auto flex w-full max-w-[1700px] items-center justify-between px-4 py-3 sm:hidden">
                <div class="flex items-center gap-1">
                    @auth
                        <button type="button" @click="$store.nav.toggleNav()" aria-label="{{ __('pages.menu.toggle_menu') }}"
                            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-600 hover:bg-gray-50">
                            <x-icon name="bars-3" class="h-6 w-6" />
                        </button>
                    @endauth
                    <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-gray-900">Ledgerline</a>
                </div>
                @auth
                    <div class="flex items-center gap-1">
                        <x-spotlight-search />
                        <div class="relative" x-data="notificationBell({ now: @js(__('common.now')) })" @click.outside="open = false">
                            <button type="button" @click="toggle()" class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-600 hover:bg-gray-50" :aria-label="'{{ __('notifications.title') }}'" title="{{ __('notifications.title') }}">
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

        <main class="mx-auto w-full max-w-[1700px] overflow-x-hidden px-4 py-8 sm:w-[92%] sm:px-6">
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

        <footer class="mx-auto w-full max-w-[1700px] px-4 py-6 text-center text-xs text-gray-400 sm:w-[92%] sm:px-6">
            Ledgerline v{{ config('app.version') }}
        </footer>
    </div>

    {{-- Mobile navigation drawer (hamburger) --}}
    <x-mobile-nav />

    {{-- App-wide confirm modal (replaces window.confirm) --}}
    <div x-data x-show="$store.confirm.open" x-cloak class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto p-4"
        role="dialog" aria-modal="true" @keydown.escape.window="$store.confirm.no()">
        <div class="absolute inset-0 bg-gray-900/50" @click="$store.confirm.no()"></div>
        <div class="relative my-24 w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
            <h3 class="text-base font-semibold text-gray-900">{{ __('common.confirm_title') }}</h3>
            <p class="mt-2 text-sm text-gray-600" x-text="$store.confirm.message || '{{ __('common.confirm_message') }}'"></p>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" @click="$store.confirm.no()" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                <button type="button" @click="$store.confirm.yes()" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('common.confirm') }}</button>
            </div>
        </div>
    </div>

    @auth
        <div x-data="toastHub(@js(['link' => __('downloads.heading')]))" class="fixed bottom-4 right-4 z-50 space-y-2" x-cloak>
            <template x-for="t in items" :key="t.id">
                <div class="flex items-center gap-3 rounded-md bg-gray-900 px-4 py-3 text-sm text-white shadow-lg">
                    <span x-text="t.message"></span>
                    <template x-if="t.url"><a :href="t.url" class="font-medium underline" x-text="t.linkLabel"></a></template>
                    <button type="button" @click="dismiss(t.id)" class="text-gray-400 hover:text-white" aria-label="close"><x-icon name="x-mark" class="h-4 w-4" /></button>
                </div>
            </template>
        </div>
    @endauth
</body>
</html>
