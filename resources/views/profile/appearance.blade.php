<x-layouts.app :title="__('account.nav_appearance')">
    <div class="mx-auto w-full max-w-3xl">
        @include('profile._header', ['title' => __('account.appearance_heading')])

        {{-- Colour scheme --}}
        <h2 class="mt-5 mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('account.appearance_theme') }}</h2>
        <div class="ll-card">
            <div class="flex gap-1 rounded-xl bg-black/5 dark:bg-white/5 p-1">
                @foreach (['light' => 'sun', 'dark' => 'moon', 'system' => 'computer-desktop'] as $mode => $icon)
                    <form method="POST" action="{{ route('theme.update') }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="theme" value="{{ $mode }}">
                        <button type="submit" @class([
                            'flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                            'll-accent shadow-sm shadow-accent/30' => $theme === $mode,
                            'text-gray-500 dark:text-gray-400 hover:text-accent' => $theme !== $mode,
                        ])>
                            <x-icon :name="$icon" class="h-4 w-4" />{{ __('messages.menu.theme_'.$mode) }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        {{-- Interface language --}}
        <h2 class="mt-6 mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('account.appearance_language') }}</h2>
        <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            @foreach (config('locales.languages') as $code => $label)
                <form method="POST" action="{{ route('locale.update') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $code }}">
                    <button type="submit" class="group flex w-full items-center gap-3.5 px-4 py-3.5 text-left transition hover:bg-accent/5">
                        <span class="ll-chip h-8 w-8 shrink-0 text-xs font-bold" style="--chip: #7066f5">{{ strtoupper($code) }}</span>
                        <span class="flex-1 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $label }}</span>
                        @if (app()->getLocale() === $code)
                            <x-icon name="check" class="h-5 w-5 shrink-0 text-accent" />
                        @endif
                    </button>
                </form>
            @endforeach
        </div>
    </div>
</x-layouts.app>
