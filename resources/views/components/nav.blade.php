{{-- Desktop persistent top bar (hidden on phones; the bottom tab bar takes over
     < sm). Consumes config/navigation.php so it never drifts from x-mobile-nav. --}}
@php
    $resolve = fn ($items) => collect($items)->map(fn ($i) => [
        'label' => __($i['label']),
        'url' => route($i['route']),
        'active' => request()->routeIs($i['pattern']),
        'icon' => $i['icon'],
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
                            'bg-accent/10 text-accent' => $item['active'],
                            'text-gray-600 dark:text-gray-400 hover:bg-accent/5 hover:text-accent' => ! $item['active'],
                        ])>
                        <x-icon :name="$item['icon']" class="h-4 w-4" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" @click="open = ! open"
                        @class([
                            'flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium',
                            'bg-accent/10 text-accent' => $moreActive,
                            'text-gray-600 dark:text-gray-400 hover:bg-accent/5 hover:text-accent' => ! $moreActive,
                        ])>
                        <x-icon name="ellipsis" class="h-4 w-4" />
                        {{ __('messages.nav.more') }}
                        <x-icon name="chevron-down" class="h-3.5 w-3.5 transition" x-bind:class="open && 'rotate-180'" />
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute left-0 z-40 mt-2 w-52 overflow-hidden rounded-xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] py-1 shadow-lg">
                        @foreach ($more as $item)
                            <a href="{{ $item['url'] }}" @click="open = false"
                                @class([
                                    'flex items-center gap-2 px-3 py-2 text-sm font-medium',
                                    'bg-accent/10 text-accent' => $item['active'],
                                    'text-gray-700 dark:text-gray-300 hover:bg-accent/5' => ! $item['active'],
                                ])>
                                <x-icon :name="$item['icon']" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                {{ $item['label'] }}
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
                <button type="button" @click="toggle()" class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-600 dark:text-gray-400 hover:bg-accent/5" :aria-label="'{{ __('notifications.title') }}'" title="{{ __('notifications.title') }}">
                    <x-icon name="bell" class="h-5 w-5" />
                    <span x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread"
                        class="absolute right-1 top-1 min-w-[1.1rem] rounded-full bg-red-500 px-1 text-center text-[10px] font-semibold leading-4 text-white"></span>
                </button>
                <x-notification-panel />
            </div>

            {{-- Vault lock toggle (system-wide): unlocked = click to lock; locked = click to open vault panel --}}
            <button type="button"
                @click="$store.vault.unlocked ? $store.vault.lock() : $dispatch('vault-panel')"
                :title="$store.vault.unlocked ? @js(__('vault.unlocked')) : @js(__('vault.unlock'))"
                :aria-label="$store.vault.unlocked ? @js(__('vault.unlocked')) : @js(__('vault.unlock'))"
                class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-gray-600 dark:text-gray-400 hover:bg-accent/5">
                <span x-show="$store.vault.unlocked"><x-icon name="lock-open" class="h-5 w-5" /></span>
                <span x-show="! $store.vault.unlocked"><x-icon name="lock-closed" class="h-5 w-5" /></span>
            </button>

            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = ! open" @keydown.escape="open = false"
                    class="flex items-center gap-2 rounded-lg px-1.5 py-1 text-sm text-gray-700 dark:text-gray-300 transition hover:bg-accent/5">
                    <x-user-avatar :user="$currentUser" size="h-8 w-8" />
                    <span>{{ $currentUser->name }}</span>
                    <x-icon name="chevron-down" class="h-4 w-4 text-gray-400 transition dark:text-gray-500" x-bind:class="open && 'rotate-180'" />
                </button>
                <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false"
                    class="absolute right-0 z-40 mt-2 w-64 overflow-hidden rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl shadow-black/10">
                    {{-- Account header --}}
                    <div class="flex items-center gap-3 px-4 py-3">
                        <x-user-avatar :user="$currentUser" size="h-10 w-10" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $currentUser->name }}</p>
                            @if ($currentUser->email)<p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $currentUser->email }}</p>@endif
                        </div>
                    </div>
                    <div class="border-t border-black/[0.06] dark:border-white/10 py-1">
                        <a href="{{ route('profile') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 transition hover:bg-accent/5 hover:text-accent"><x-icon name="user" class="h-4 w-4" />{{ __('messages.menu.profile') }}</a>
                        @if (auth()->user()->managesGlobalSettings())
                            <a href="{{ route('settings') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 transition hover:bg-accent/5 hover:text-accent"><x-icon name="shield-check" class="h-4 w-4" />{{ __('messages.menu.settings') }}</a>
                        @endif
                    </div>
                    {{-- Theme + language live on the profile Appearance sub-page now. --}}
                    {{-- Drop the cached zero-knowledge vault key at logout time. --}}
                    <form method="POST" action="{{ route('logout') }}" @submit="window.Vault && window.Vault.lock()" class="border-t border-black/[0.06] dark:border-white/10 py-1">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-red-600 dark:text-red-400 transition hover:bg-red-500/10"><x-icon name="arrow-uturn-left" class="h-4 w-4" />{{ __('messages.menu.logout') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endauth
</nav>
