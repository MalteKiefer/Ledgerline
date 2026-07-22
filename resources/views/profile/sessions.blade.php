<x-layouts.app :title="__('account.nav_sessions')">
    <div class="mx-auto w-full max-w-3xl">
        @include('profile._header', ['title' => __('account.nav_sessions'), 'subtitle' => __('account.sessions_hint')])

        <div class="mt-5 ll-card flex items-center gap-3.5">
            <span class="ll-chip h-8 w-8 shrink-0" style="--chip: #3fae9f"><x-icon name="clock" class="h-4 w-4" /></span>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('account.last_login') }}</span>
            <span class="ml-auto text-sm text-gray-900 dark:text-gray-100">{{ $user->last_login_at?->format('Y-m-d H:i') ?: __('account.never') }}</span>
        </div>

        <h2 class="mt-6 mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('account.sessions_heading') }}</h2>
        <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            @forelse ($sessions as $s)
                <div class="flex items-center gap-3.5 px-4 py-3">
                    <span class="ll-chip h-9 w-9 shrink-0" style="--chip: #9e70fa"><x-icon name="computer-desktop" class="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $s['agent'] ?: __('account.sessions_unknown') }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $s['ip'] ?: '—' }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($s['last_activity'])->diffForHumans() }}
                            @if ($s['current']) · <span class="font-medium text-accent">{{ __('account.sessions_current') }}</span>@endif
                        </p>
                    </div>
                    @unless ($s['current'])
                        <form method="POST" action="{{ route('account.sessions.revoke', $s['id']) }}" class="shrink-0">
                            @csrf @method('DELETE')
                            <button type="submit" :title="@js(__('account.sessions_revoke'))" aria-label="{{ __('account.sessions_revoke') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 dark:text-gray-500 transition hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-red-600 dark:hover:text-red-400">
                                <x-icon name="x-mark" class="h-5 w-5" />
                            </button>
                        </form>
                    @endunless
                </div>
            @empty
                <p class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ __('account.sessions_none') }}</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
