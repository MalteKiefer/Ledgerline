<x-layouts.app :title="__('settings.users_section')">
    @php
        $hb = function (int $b): string {
            if ($b <= 0) return '0 B';
            $u = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = min((int) floor(log($b, 1024)), count($u) - 1);
            return rtrim(rtrim(number_format($b / (1024 ** $i), 1), '0'), '.').' '.$u[$i];
        };
    @endphp
    {{-- Mutually-exclusive panels: `open` is 'new', a user id, or null. Opening one closes any other. --}}
    <div class="mx-auto w-full max-w-3xl" x-data="{ open: null }">
        @include('profile._header', ['title' => __('settings.users_section'), 'subtitle' => __('settings.users_desc')])

        @if (session('status'))
            <div class="mt-4 rounded-md border border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950 px-3 py-2 text-sm text-green-700 dark:text-green-300" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300" role="alert">{{ $errors->first() }}</div>
        @endif

        @if (! $mailEnabled)
            <div class="mt-4 rounded-md border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950 px-3 py-2 text-xs text-amber-800 dark:text-amber-300">{{ __('settings.users_mail_off') }}</div>
        @endif

        @if (session('invite_url'))
            <div class="mt-4 ll-card border-accent/30" x-data="{ copied: false, url: @js(session('invite_url')) }">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('settings.users_invite_ready') }}</p>
                @if (session('invite_sent'))<p class="mt-0.5 text-xs text-green-600 dark:text-green-400">{{ __('settings.users_invite_emailed') }}</p>@endif
                <div class="mt-2 flex items-center gap-2">
                    <input type="text" readonly :value="url" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-[#1c1c1e] px-3 py-2 text-xs text-gray-700 dark:text-gray-300" onclick="this.select()">
                    <button type="button" @click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 1500)"
                        class="shrink-0 rounded-lg bg-accent/10 px-3 py-2 text-xs font-medium text-accent hover:bg-accent/20">
                        <span x-show="! copied">{{ __('settings.users_invite_copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('settings.users_invite_copied') }}</span>
                    </button>
                </div>
                <p class="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500">{{ __('settings.users_invite_hint') }}</p>
            </div>
        @endif

        {{-- Self-registration toggle --}}
        <div class="mt-5 ll-card">
            <form method="POST" action="{{ route('settings.registration') }}" class="flex items-center justify-between gap-3">
                @csrf
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('settings.users_registration') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('settings.users_registration_hint') }}</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="hidden" name="allow_registration" value="0">
                    <input type="checkbox" name="allow_registration" value="1" @checked($settings->allow_registration) onchange="this.form.submit()" class="rounded border-gray-300 dark:border-gray-600 text-accent focus:ring-accent">
                    <span class="text-gray-600 dark:text-gray-300">{{ $settings->allow_registration ? __('settings.users_registration_on') : __('settings.users_registration_off') }}</span>
                </label>
            </form>
        </div>

        {{-- Create a user --}}
        <div class="mt-5 ll-card">
            <button type="button" @click="open = (open === 'new' ? null : 'new')" class="flex w-full items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.users_create') }}</h2>
                <x-icon name="chevron-right" class="h-4 w-4 text-gray-400 transition" ::class="open === 'new' ? 'rotate-90' : ''" />
            </button>
            <form x-show="open === 'new'" x-cloak method="POST" action="{{ route('settings.users.store') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @csrf
                @include('settings.users._fields', ['user' => null])
                <div class="sm:col-span-2">
                    <x-button type="submit">{{ __('settings.users_create') }}</x-button>
                </div>
            </form>
        </div>

        {{-- User list --}}
        <div class="mt-5 space-y-3">
            @foreach ($users as $u)
                @php $use = $usage[$u->id] ?? ['used' => 0, 'quota' => null]; @endphp
                <div class="ll-card">
                    <div class="flex items-center gap-3">
                        @if ($u->avatar)
                            <img src="{{ route('settings.users.avatar', $u) }}" alt="" class="h-9 w-9 shrink-0 rounded-xl object-cover">
                        @else
                            <span class="ll-chip flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background:{{ $u->role === 'admin' ? '#7066f5' : '#6b7280' }}">
                                <x-icon name="user" class="h-4 w-4 text-white" />
                            </span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                <span class="truncate">{{ $u->name }}</span>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $u->role === 'admin' ? 'bg-accent/15 text-accent' : 'bg-gray-500/15 text-gray-500 dark:text-gray-400' }}">{{ $u->role === 'admin' ? __('settings.users_role_admin') : __('settings.users_role_user') }}</span>
                                @if ($u->two_factor_confirmed_at)<span class="shrink-0 text-[11px] text-green-600 dark:text-green-400">{{ __('settings.users_2fa') }}</span>@endif
                                @unless ($u->email_verified_at)<span class="shrink-0 text-[11px] text-amber-600 dark:text-amber-400">{{ __('settings.users_unverified') }}</span>@endunless
                            </div>
                            <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $u->email }}</div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                                <span class="inline-flex items-center gap-1"><x-icon name="circle-stack" class="h-3 w-3" />{{ $hb($use['used']) }}@if ($use['quota']) / {{ $hb($use['quota']) }}@else <span class="text-gray-400 dark:text-gray-500">· {{ __('account.storage_unlimited') }}</span>@endif</span>
                                <span class="inline-flex items-center gap-1"><x-icon name="clock" class="h-3 w-3" />{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : __('settings.users_never_logged_in') }}</span>
                            </div>
                        </div>
                        <button type="button" @click="open = (open === {{ $u->id }} ? null : {{ $u->id }})" class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-black/[0.06] hover:text-gray-700 dark:hover:bg-white/10"><x-icon name="pencil" class="h-4 w-4" /></button>
                    </div>

                    <div x-show="open === {{ $u->id }}" x-cloak class="mt-4 border-t border-black/[0.06] dark:border-white/10 pt-4">
                        <form method="POST" action="{{ route('settings.users.update', $u) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @csrf @method('PUT')
                            @include('settings.users._fields', ['user' => $u])
                            <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                                <x-button type="submit">{{ __('settings.save') }}</x-button>
                            </div>
                        </form>
                        {{-- Mail-independent invite / reset link: copy-paste or email, with a chosen validity. --}}
                        <form method="POST" action="{{ route('settings.users.invite', $u) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-black/[0.06] dark:border-white/10 pt-3">
                            @csrf
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1">{{ __('settings.users_invite_validity') }}</label>
                                <select name="ttl_hours" class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-2 py-1.5 text-xs text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                                    <option value="1">{{ __('settings.users_invite_ttl_1h') }}</option>
                                    <option value="24" selected>{{ __('settings.users_invite_ttl_24h') }}</option>
                                    <option value="168">{{ __('settings.users_invite_ttl_7d') }}</option>
                                    <option value="720">{{ __('settings.users_invite_ttl_30d') }}</option>
                                </select>
                            </div>
                            <button type="submit" class="rounded-lg bg-accent/10 px-3 py-1.5 text-xs font-medium text-accent hover:bg-accent/20">{{ __('settings.users_invite_create') }}</button>
                            @if ($mailEnabled)
                                <button type="submit" name="send" value="1" class="rounded-lg px-3 py-1.5 text-xs font-medium text-accent hover:underline">{{ __('settings.users_invite_send') }}</button>
                            @endif
                        </form>
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('settings.users.reset', $u) }}">
                                @csrf
                                <button type="submit" class="text-xs text-accent hover:underline">{{ __('settings.users_reset') }}</button>
                            </form>
                            @if ($u->two_factor_confirmed_at)
                                <form method="POST" action="{{ route('settings.users.reset2fa', $u) }}"
                                      x-on:submit="if (! confirm(@js(__('settings.users_2fa_reset_confirm')))) $event.preventDefault()">
                                    @csrf
                                    <button type="submit" class="text-xs text-accent hover:underline">{{ __('settings.users_2fa_reset') }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('settings.users.destroy', $u) }}"
                                  x-on:submit="if (! confirm(@js(__('settings.users_delete_confirm')))) $event.preventDefault()">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('settings.users_delete') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
