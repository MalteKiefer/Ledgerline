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
                        'bg-gray-100 text-gray-900' => $item['active'],
                        'text-gray-700 hover:bg-gray-50' => ! $item['active'],
                    ])>
                    <x-icon :name="$item['icon']" class="h-5 w-5 text-gray-400" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="mt-4 space-y-1 border-t border-gray-100 pt-3">
            <a href="{{ route('profile') }}" @click="$store.nav.closeAll()" class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <x-icon name="contacts" class="h-5 w-5 text-gray-400" />{{ __('messages.menu.profile') }}
            </a>
            <a href="{{ route('settings') }}" @click="$store.nav.closeAll()" class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <x-icon name="ellipsis" class="h-5 w-5 text-gray-400" />{{ __('messages.menu.settings') }}
            </a>
        </div>

        <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3">
            <div class="flex gap-1">
                @foreach (config('locales.languages') as $code => $label)
                    <form method="POST" action="{{ route('locale.update') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $code }}">
                        <button type="submit"
                            @class([
                                'rounded px-3 py-2 text-xs font-medium',
                                'bg-gray-800 text-white' => app()->getLocale() === $code,
                                'text-gray-600 hover:bg-gray-100' => app()->getLocale() !== $code,
                            ])>{{ strtoupper($code) }}</button>
                    </form>
                @endforeach
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('messages.menu.logout') }}</button>
            </form>
        </div>
    </x-sheet>
@endauth
