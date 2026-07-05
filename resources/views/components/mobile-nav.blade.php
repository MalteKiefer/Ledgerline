{{-- Mobile bottom tab bar (< sm): 5 primary destinations + a "More" tab that
     opens an off-canvas sheet with the secondary links and account actions.
     Consumes the same config/navigation.php as the desktop x-nav. --}}
@auth
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
    @endphp
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur sm:hidden">
        <div class="mx-auto grid max-w-lg grid-cols-6">
            @foreach ($primary as $item)
                <a href="{{ $item['url'] }}" @click="$store.nav.closeAll()"
                    @class([
                        'flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 text-[11px] font-medium',
                        'text-gray-900' => $item['active'],
                        'text-gray-500' => ! $item['active'],
                    ])>
                    <x-icon :name="$item['icon']" class="h-5 w-5" />
                    <span class="w-full truncate text-center">{{ $item['label'] }}</span>
                </a>
            @endforeach
            <button type="button" @click="$store.nav.toggleMore()"
                :class="$store.nav.moreOpen || {{ $moreActive ? 'true' : 'false' }} ? 'text-gray-900' : 'text-gray-500'"
                class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 text-[11px] font-medium">
                <x-icon name="bars-3" class="h-5 w-5" />
                <span>{{ __('messages.nav.more') }}</span>
            </button>
        </div>
    </nav>

    <x-sheet side="bottom" store="moreOpen" :title="__('messages.nav.more')">
        <div class="grid grid-cols-4 gap-2">
            @foreach ($more as $item)
                <a href="{{ $item['url'] }}" @click="$store.nav.closeAll()"
                    @class([
                        'flex flex-col items-center justify-center gap-1 rounded-lg border p-3 text-xs font-medium',
                        'border-gray-300 bg-gray-100 text-gray-900' => $item['active'],
                        'border-gray-200 text-gray-600 hover:bg-gray-50' => ! $item['active'],
                    ])>
                    <x-icon :name="$item['icon']" class="h-5 w-5" />
                    <span class="w-full truncate text-center">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>

        <div class="mt-4 space-y-1 border-t border-gray-100 pt-3">
            <a href="{{ route('profile') }}" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('messages.menu.profile') }}</a>
            <a href="{{ route('settings') }}" class="flex min-h-11 items-center rounded-md px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('messages.menu.settings') }}</a>
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
