<x-layouts.app :title="__('pages.profile.title')">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('pages.profile.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ __('pages.profile.subtitle') }}
    </p>

    <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 sm:p-6 shadow-sm">
        <div class="flex items-center gap-4">
            <x-user-avatar :user="$user" size="h-16 w-16" />
            <div class="min-w-0">
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 truncate">{{ $user->email }}</p>
                @if ($user->avatar_url)
                    <form method="POST" action="{{ route('profile.avatar.refresh') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="min-h-11 rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><span class="inline-flex items-center gap-1.5"><x-icon name="arrow-path" class="h-3.5 w-3.5" />{{ __('pages.profile.refresh_avatar') }}</span></button>
                    </form>
                @endif
            </div>
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 border-t border-gray-100 dark:border-gray-800 pt-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.name') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $user->name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.email') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    @if ($user->email)
                        <a href="mailto:{{ $user->email }}" class="text-gray-900 dark:text-gray-100 hover:underline">{{ $user->email }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.email_verified') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ $user->email_verified_at ? __('pages.profile.verified_yes', ['date' => $user->email_verified_at->format('Y-m-d')]) : __('pages.profile.verified_no') }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.pocketid_subject') }}</dt>
                <dd class="mt-1 break-all font-mono text-sm text-gray-900 dark:text-gray-100">{{ $user->oidc_sub ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.avatar') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    {{ $user->avatar ? __('pages.profile.avatar_provided') : __('pages.profile.avatar_none') }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('pages.profile.account_created') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $user->created_at?->format('Y-m-d H:i') ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Security & data --}}
    <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6" x-data="{ del: false }">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.security_heading') }}</h2>

        <div class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-4">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('account.last_login') }}</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $user->last_login_at?->format('Y-m-d H:i') ?: __('account.never') }}</dd>
        </div>

        {{-- Active sessions --}}
        <div class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('account.sessions_heading') }}</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('account.sessions_hint') }}</p>
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($sessions as $s)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-gray-900 dark:text-gray-100">{{ $s['agent'] ?: __('account.sessions_unknown') }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $s['ip'] ?: '—' }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($s['last_activity'])->diffForHumans() }}
                                @if ($s['current']) · <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('account.sessions_current') }}</span>@endif
                            </p>
                        </div>
                        @unless ($s['current'])
                            <form method="POST" action="{{ route('account.sessions.revoke', $s['id']) }}" class="shrink-0">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-gray-300 dark:border-gray-700 px-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('account.sessions_revoke') }}</button>
                            </form>
                        @endunless
                    </li>
                @empty
                    <li class="py-2 text-sm text-gray-500 dark:text-gray-400">{{ __('account.sessions_none') }}</li>
                @endforelse
            </ul>
        </div>

        {{-- Export --}}
        <div class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('account.export_heading') }}</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('account.export_hint') }}</p>
            <x-button :href="route('account.export')" icon="arrow-down-tray" class="mt-3">{{ __('account.export_button') }}</x-button>
        </div>

        {{-- Delete account --}}
        <div class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4">
            <h3 class="text-sm font-medium text-red-700 dark:text-red-300">{{ __('account.delete_heading') }}</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('account.delete_hint') }}</p>
            <x-button variant="danger" icon="trash" class="mt-3" @click="del = true">{{ __('account.delete_button') }}</x-button>
        </div>

        {{-- Delete confirmation modal --}}
        <div x-show="del" x-cloak class="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto p-4"
            role="dialog" aria-modal="true" @keydown.escape.window="del = false">
            <div class="absolute inset-0 bg-gray-900/50" @click="del = false"></div>
            <div class="relative my-16 w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.delete_modal_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ __('account.delete_modal_warning') }}</p>
                <form method="POST" action="{{ route('account.destroy') }}" class="mt-4 space-y-3">
                    @csrf @method('DELETE')
                    <label class="block text-sm text-gray-700 dark:text-gray-300">{{ __('account.delete_confirm_label') }}
                        <input type="text" name="confirmation" required autocomplete="off"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    </label>
                    @error('confirmation')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <div class="flex justify-end gap-2">
                        <x-button variant="secondary" type="button" @click="del = false">{{ __('common.cancel') }}</x-button>
                        <x-button variant="danger" type="submit">{{ __('account.delete_button') }}</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
