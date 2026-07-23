{{-- Mobile navigation drawer (< sm): a left slide-over opened by the hamburger
     in the top strip. Holds every destination + account actions. Consumes the
     same config/navigation.php as the desktop x-nav. --}}
@auth
    @php
        $resolve = fn ($items) => collect($items)->map(fn ($i) => [
            'label' => __($i['label']),
            'url' => route($i['route']),
            'active' => request()->routeIs($i['pattern']),
            'icon' => $i['icon'],
        ]);
        $links = $resolve(config('navigation.primary'))->concat($resolve(config('navigation.more')));
    @endphp
    <x-sheet side="left" store="navOpen" title="Ledgerline">
        <nav class="space-y-1">
            @foreach ($links as $item)
                <a href="{{ $item['url'] }}" @click="$store.nav.closeAll()"
                    @class([
                        'flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium',
                        'bg-accent/10 text-accent' => $item['active'],
                        'text-gray-700 dark:text-gray-300 hover:bg-accent/5' => ! $item['active'],
                    ])>
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-white shadow-sm" style="background:#7066f5"><x-icon :name="$item['icon']" class="h-4 w-4" /></span>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="mt-4 space-y-1 border-t border-gray-100 dark:border-gray-800 pt-3">
            <a href="{{ route('profile') }}" @click="$store.nav.closeAll()" class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-accent/5">
                <x-icon name="contacts" class="h-5 w-5 text-gray-400 dark:text-gray-500" />{{ __('messages.menu.profile') }}
            </a>
            @if (auth()->user()->managesGlobalSettings())
                <a href="{{ route('settings') }}" @click="$store.nav.closeAll()" class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-accent/5">
                    <x-icon name="ellipsis" class="h-5 w-5 text-gray-400 dark:text-gray-500" />{{ __('messages.menu.settings') }}
                </a>
            @endif
            {{-- Vault lock toggle (system-wide; mobile parity for the desktop nav lock). --}}
            <button type="button"
                @click="$store.vault.unlocked ? $store.vault.lock() : $dispatch('vault-panel'); $store.nav.closeAll()"
                class="flex w-full min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-accent/5">
                <span x-show="$store.vault.unlocked"><x-icon name="lock-open" class="h-5 w-5 text-gray-400 dark:text-gray-500" /></span>
                <span x-show="! $store.vault.unlocked"><x-icon name="lock-closed" class="h-5 w-5 text-gray-400 dark:text-gray-500" /></span>
                <span x-text="$store.vault.unlocked ? @js(__('vault.unlocked')) : @js(__('vault.unlock'))"></span>
            </button>
        </div>

        {{-- Theme + language live on the profile Appearance sub-page now. --}}
        <div class="mt-3 flex items-center justify-end border-t border-gray-100 dark:border-gray-800 pt-3">
            <form method="POST" action="{{ route('logout') }}" @submit="window.Vault && window.Vault.lock()">
                @csrf
                <button type="submit" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-accent/5">{{ __('messages.menu.logout') }}</button>
            </form>
        </div>
    </x-sheet>
@endauth
