{{-- Desktop persistent top bar (hidden on phones; the bottom tab bar takes over
     < sm). Finance-only: the Finance SPA renders its own in-page section tabs,
     so this bar carries only brand + notifications + the account menu. --}}
@php
    $currentUser = auth()->user();
@endphp
<nav class="mx-auto hidden w-full max-w-[1700px] items-center justify-between px-4 py-3 sm:flex sm:w-[92%] sm:px-6">
    <div class="flex items-center gap-8">
        <a href="{{ route('finance.index') }}" class="text-lg font-semibold text-md-on-surface">Ledgerline</a>
        @auth
            @php
                $allowed = $currentUser?->allowedModules() ?? [];
                $navIcons = ['finance' => 'banknotes', 'files' => 'folder'];
            @endphp
            <div class="flex items-center gap-1">
                @foreach (config('modules.list', []) as $key => $mod)
                    @continue(! in_array($key, $allowed, true))
                    @php $active = request()->routeIs($mod['route']) || request()->routeIs(explode('.', $mod['route'])[0].'.*'); @endphp
                    <a href="{{ route($mod['route']) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition {{ $active ? 'bg-accent/10 text-accent' : 'text-md-on-surface-var hover:bg-accent/5 hover:text-accent' }}">
                        <x-icon name="{{ $navIcons[$key] ?? 'squares-2x2' }}" class="h-4 w-4" />{{ __($mod['label']) }}
                    </a>
                @endforeach
            </div>
        @endauth
    </div>

    @auth
        <div class="flex items-center gap-3">
            <div class="relative" x-data="notificationBell({ now: @js(__('common.now')) })" @click.outside="open = false">
                <button type="button" @click="toggle()" class="relative inline-flex min-h-11 min-w-11 items-center justify-center rounded-md text-md-on-surface-var hover:bg-accent/5" :aria-label="'{{ __('notifications.title') }}'" title="{{ __('notifications.title') }}">
                    <x-icon name="bell" class="h-5 w-5" />
                    <span x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread"
                        class="absolute right-1 top-1 min-w-[1.1rem] rounded-full bg-red-500 px-1 text-center text-[10px] font-semibold leading-4 text-white"></span>
                </button>
                <x-notification-panel />
            </div>

            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = ! open" @keydown.escape="open = false"
                    class="flex items-center gap-2 rounded-lg px-1.5 py-1 text-sm text-md-on-surface-var transition hover:bg-accent/5">
                    <x-user-avatar :user="$currentUser" size="h-8 w-8" />
                    <span>{{ $currentUser->name }}</span>
                    <x-icon name="chevron-down" class="h-4 w-4 text-md-on-surface-var transition" x-bind:class="open && 'rotate-180'" />
                </button>
                <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false"
                    class="absolute right-0 z-40 mt-2 w-64 overflow-hidden rounded-xl border border-md-outline-variant bg-md-surface shadow-xl shadow-black/10">
                    {{-- Account header --}}
                    <div class="flex items-center gap-3 px-4 py-3">
                        <x-user-avatar :user="$currentUser" size="h-10 w-10" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-md-on-surface">{{ $currentUser->name }}</p>
                            @if ($currentUser->email)<p class="truncate text-xs text-md-on-surface-var">{{ $currentUser->email }}</p>@endif
                        </div>
                    </div>
                    <div class="border-t border-md-outline-variant py-1">
                        <a href="{{ route('profile') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-md-on-surface-var transition hover:bg-accent/5 hover:text-accent"><x-icon name="user" class="h-4 w-4" />{{ __('messages.menu.profile') }}</a>
                        @if (auth()->user()->managesGlobalSettings())
                            <a href="{{ route('settings') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-md-on-surface-var transition hover:bg-accent/5 hover:text-accent"><x-icon name="shield-check" class="h-4 w-4" />{{ __('messages.menu.settings') }}</a>
                        @endif
                    </div>
                    {{-- Theme + language live on the profile Appearance sub-page now. --}}
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-md-outline-variant py-1">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-red-600 dark:text-red-400 transition hover:bg-red-500/10"><x-icon name="arrow-uturn-left" class="h-4 w-4" />{{ __('messages.menu.logout') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endauth
</nav>
