<x-layouts.app :title="__('account.nav_security')">
    <div class="mx-auto w-full max-w-3xl">
        @include('profile._header', ['title' => __('account.nav_security'), 'subtitle' => __('account.twofa_hint')])

        @if ($errors->any())
            <div class="mt-4 rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300" role="alert">{{ $errors->first() }}</div>
        @endif

        {{-- Password change (Fortify updatePasswords; errors in the "updatePassword" bag) --}}
        <div class="mt-5 ll-card space-y-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.password_title') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('account.password_desc') }}</p>
            </div>
            @if ($errors->updatePassword->any())
                <x-alert variant="error">{{ $errors->updatePassword->first() }}</x-alert>
            @endif
            <form method="POST" action="{{ route('user-password.update') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label for="current_password" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('account.password_current') }}</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                </div>
                <div>
                    <label for="password" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('account.password_new') }}</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required minlength="12"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('account.password_confirm') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="12"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                </div>
                <x-button type="submit">{{ __('account.password_save') }}</x-button>
            </form>
        </div>

        <div class="mt-5 ll-card space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('account.twofa_title') }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('account.twofa_desc') }}</p>
                </div>
                @if ($enabled)
                    <span class="rounded-full bg-green-500/15 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400">{{ __('account.twofa_on') }}</span>
                @else
                    <span class="rounded-full bg-gray-500/15 px-2.5 py-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('account.twofa_off') }}</span>
                @endif
            </div>

            {{-- Disabled → offer to enable --}}
            @if (! $enabled && ! $pending)
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <x-button type="submit">{{ __('account.twofa_enable') }}</x-button>
                </form>
            @endif

            {{-- Secret generated but not yet confirmed → show QR + confirm --}}
            @if ($pending)
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('account.twofa_scan') }}</p>
                @if ($qr)
                    <div class="inline-block rounded-lg bg-white p-3">{!! $qr !!}</div>
                @endif
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label for="code" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('account.twofa_code') }}</label>
                        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required
                            class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm tracking-widest text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                    </div>
                    <x-button type="submit">{{ __('account.twofa_confirm') }}</x-button>
                </form>
                <form method="POST" action="{{ route('two-factor.disable') }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-gray-500 hover:text-red-600">{{ __('account.twofa_cancel') }}</button>
                </form>
            @endif

            {{-- Recovery codes (pending or enabled) --}}
            @if (($pending || $enabled) && count($recovery))
                <div class="rounded-xl border border-black/[0.08] dark:border-white/10 p-3">
                    <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('account.twofa_recovery_codes') }}</p>
                    <div class="grid grid-cols-2 gap-1 font-mono text-xs text-gray-800 dark:text-gray-200">
                        @foreach ($recovery as $code)
                            <div>{{ $code }}</div>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mt-3">
                        @csrf
                        <x-button variant="secondary" size="sm" type="submit">{{ __('account.twofa_regenerate') }}</x-button>
                    </form>
                </div>
            @endif

            {{-- Enabled → disable --}}
            @if ($enabled)
                <form method="POST" action="{{ route('two-factor.disable') }}">
                    @csrf @method('DELETE')
                    <x-button variant="danger" type="submit">{{ __('account.twofa_disable') }}</x-button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
