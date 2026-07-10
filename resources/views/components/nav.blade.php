{{-- Desktop persistent top bar (hidden on phones; the bottom tab bar takes over
     < sm). Consumes config/navigation.php so it never drifts from x-mobile-nav. --}}
@php
    // Ready-but-unseen exports surface as a badge on Downloads (and a dot on
    // "More" since Downloads lives inside the dropdown).
    $dlBadge = auth()->check() ? \App\Models\Export::unseenReadyCount((int) auth()->id()) : 0;
    $resolve = fn ($items) => collect($items)->map(fn ($i) => [
        'label' => __($i['label']),
        'url' => route($i['route']),
        'active' => request()->routeIs($i['pattern']),
        'icon' => $i['icon'],
        'badge' => $i['route'] === 'downloads.index' ? $dlBadge : 0,
    ]);
    $primary = $resolve(config('navigation.primary'));
    $more = $resolve(config('navigation.more'));
    $moreActive = $more->contains('active', true);
    $currentUser = auth()->user();
@endphp
<nav class="mx-auto hidden w-full max-w-[1700px] items-center justify-between px-4 py-3 sm:flex sm:w-[92%] sm:px-6">
    <div class="flex items-center gap-8">
        <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Ledgerline</a>
        @auth
            <div class="flex items-center gap-1">
                @foreach ($primary as $item)
                    <a href="{{ $item['url'] }}"
                        @class([
                            'flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium',
                            'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' => $item['active'],
                            'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' => ! $item['active'],
                        ])>
                        <x-icon :name="$item['icon']" class="h-4 w-4" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" @click="open = ! open"
                        @class([
                            'flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium',
                            'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' => $moreActive,
                            'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' => ! $moreActive,
                        ])>
                        <x-icon name="ellipsis" class="h-4 w-4" />
                        {{ __('messages.nav.more') }}
                        @if ($dlBadge > 0)
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-900" title="{{ __('downloads.new_ready') }}"></span>
                        @endif
                        <x-icon name="chevron-down" class="h-3.5 w-3.5 transition" x-bind:class="open && 'rotate-180'" />
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute left-0 z-40 mt-2 w-52 overflow-hidden rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-1 shadow-lg">
                        @foreach ($more as $item)
                            <a href="{{ $item['url'] }}" @click="open = false"
                                @class([
                                    'flex items-center gap-2 px-3 py-2 text-sm font-medium',
                                    'bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100' => $item['active'],
                                    'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' => ! $item['active'],
                                ])>
                                <x-icon :name="$item['icon']" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                {{ $item['label'] }}
                                @if ($item['badge'] > 0)
                                    <span class="ml-auto min-w-[1.1rem] rounded-full bg-gray-900 dark:bg-gray-100 px-1.5 text-center text-[10px] font-semibold leading-4 text-white dark:text-gray-900"
                                        title="{{ __('downloads.new_ready') }}">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endauth
    </div>

    @auth
        <div class="flex items-center gap-3">
            <div class="relative" x-data="notificationBell({ now: @js(__('common.now')) })" @click.outside="open = false">
                <button type="button" @click="toggle()" class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800" :aria-label="'{{ __('notifications.title') }}'" title="{{ __('notifications.title') }}">
                    <x-icon name="bell" class="h-5 w-5" />
                    <span x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread"
                        class="absolute right-1 top-1 min-w-[1.1rem] rounded-full bg-red-500 px-1 text-center text-[10px] font-semibold leading-4 text-white"></span>
                </button>
                <x-notification-panel />
            </div>

            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = ! open" @keydown.escape="open = false"
                    class="flex items-center gap-2 rounded-md px-1.5 py-1 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <x-user-avatar :user="$currentUser" size="h-8 w-8" />
                    <span>{{ $currentUser->name }}</span>
                    <x-icon name="chevron-down" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                </button>
                <div x-show="open" x-cloak @click.outside="open = false"
                    class="absolute right-0 z-40 mt-2 w-48 overflow-hidden rounded-md border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-1 shadow-lg">
                    <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('messages.menu.profile') }}</a>
                    <a href="{{ route('settings') }}" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('messages.menu.settings') }}</a>
                    @php $llTheme = \App\Models\UserSetting::for((int) auth()->id())->theme ?? 'system'; @endphp
                    <div class="flex gap-1 border-t border-gray-100 dark:border-gray-800 px-4 py-2">
                        @foreach (['light' => 'sun', 'dark' => 'moon', 'system' => 'computer-desktop'] as $mode => $icon)
                            <form method="POST" action="{{ route('theme.update') }}" class="flex-1">
                                @csrf
                                <input type="hidden" name="theme" value="{{ $mode }}">
                                <button type="submit" title="{{ __('messages.menu.theme_'.$mode) }}"
                                    @class([
                                        'flex w-full items-center justify-center rounded px-2 py-1.5',
                                        'bg-gray-800 text-white' => $llTheme === $mode,
                                        'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' => $llTheme !== $mode,
                                    ])>
                                    <x-icon :name="$icon" class="h-4 w-4" />
                                </button>
                            </form>
                        @endforeach
                    </div>
                    <div class="flex gap-1 border-t border-gray-100 dark:border-gray-800 px-4 py-2">
                        @foreach (config('locales.languages') as $code => $label)
                            <form method="POST" action="{{ route('locale.update') }}">
                                @csrf
                                <input type="hidden" name="locale" value="{{ $code }}">
                                <button type="submit"
                                    @class([
                                        'rounded px-2 py-1 text-xs font-medium',
                                        'bg-gray-800 text-white' => app()->getLocale() === $code,
                                        'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' => app()->getLocale() !== $code,
                                    ])>{{ strtoupper($code) }}</button>
                            </form>
                        @endforeach
                    </div>
                    {{-- Drop the cached zero-knowledge vault key at logout time. --}}
                    <form method="POST" action="{{ route('logout') }}" @submit="window.Vault && window.Vault.lock()">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('messages.menu.logout') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endauth
</nav>
