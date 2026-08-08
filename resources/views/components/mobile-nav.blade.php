{{-- Mobile navigation drawer (< sm): a left slide-over opened by the hamburger
     in the top strip. Finance-only: the Finance SPA renders its own in-page
     section tabs, so this drawer holds only the account actions. --}}
@auth
    <x-sheet side="left" store="navOpen" title="Ledgerline">
        @php
            $allowed = auth()->user()?->allowedModules() ?? [];
            $navIcons = ['finance' => 'banknotes', 'files' => 'folder'];
        @endphp
        <div class="space-y-1">
            @foreach (config('modules.list', []) as $key => $mod)
                @continue(! in_array($key, $allowed, true))
                @php $active = request()->routeIs(explode('.', $mod['route'])[0].'.*'); @endphp
                <a href="{{ route($mod['route']) }}" @click="$store.nav.closeAll()" class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium {{ $active ? 'bg-accent/10 text-accent' : 'text-md-on-surface-var dark:text-md-on-surface-var hover:bg-accent/5' }}">
                    <x-icon name="{{ $navIcons[$key] ?? 'squares-2x2' }}" class="h-5 w-5" />{{ __($mod['label']) }}
                </a>
            @endforeach
            <div class="my-1 border-t border-md-outline-variant dark:border-md-outline-variant"></div>
            <a href="{{ route('profile') }}" @click="$store.nav.closeAll()" class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var hover:bg-accent/5">
                <x-icon name="contacts" class="h-5 w-5 text-md-on-surface-var dark:text-md-on-surface-var" />{{ __('messages.menu.profile') }}
            </a>
            @if (auth()->user()->managesGlobalSettings())
                <a href="{{ route('settings') }}" @click="$store.nav.closeAll()" class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var hover:bg-accent/5">
                    <x-icon name="ellipsis" class="h-5 w-5 text-md-on-surface-var dark:text-md-on-surface-var" />{{ __('messages.menu.settings') }}
                </a>
            @endif
        </div>

        {{-- Theme + language live on the profile Appearance sub-page now. --}}
        <div class="mt-3 flex items-center justify-end border-t border-md-outline-variant dark:border-md-outline-variant pt-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md px-3 py-2 text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var hover:bg-accent/5">{{ __('messages.menu.logout') }}</button>
            </form>
        </div>
    </x-sheet>
@endauth
