<x-layouts.app :title="__('calendar.heading')">
    <p class="text-sm text-md-on-surface-var dark:text-md-on-surface-var">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('calendar.heading') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('calendar.heading') }}</h1>
    <p class="mt-1 text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('calendar.subheading') }}</p>

    <section class="mt-6 rounded-lg border border-md-outline-variant dark:border-md-outline-variant bg-white dark:bg-md-surface-2 p-4 shadow-sm sm:p-6">
        @unless ($hasPassword)
            <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-500/40 dark:bg-amber-500/10">
                <p class="font-medium text-amber-800 dark:text-amber-200">{{ __('contacts.no_password') }}</p>
                <p class="mt-1 text-amber-700 dark:text-amber-300">{{ __('contacts.no_password_hint') }}</p>
                <x-button variant="primary" :href="route('profile.account')" class="mt-3">{{ __('contacts.set_password') }}</x-button>
            </div>
        @endunless

        <dl class="grid gap-2 text-sm sm:grid-cols-[8rem_1fr]">
            <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('calendar.dav_url') }}</dt>
            <dd class="select-all break-all font-mono text-md-on-surface dark:text-md-on-surface">{{ $davUrl }}</dd>
            <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('calendar.username') }}</dt>
            <dd class="select-all font-mono text-md-on-surface dark:text-md-on-surface">{{ $username }}</dd>
        </dl>
        <p class="mt-3 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('calendar.setup_hint') }}</p>

        <div class="mt-5 grid gap-6 sm:grid-cols-2">
            <div>
                <h3 class="text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var">{{ __('contacts.qr_heading') }}</h3>
                <div class="mt-2 inline-block rounded-md border border-md-outline-variant dark:border-md-outline-variant bg-white p-2">
                    <img src="{{ $qr }}" alt="{{ __('calendar.dav_url') }}" class="h-40 w-40" />
                </div>
            </div>
            <div>
                <h3 class="text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var">Apple (iOS / macOS)</h3>
                <x-button variant="primary" :href="route('settings.calendar.profile')" class="mt-2">{{ __('calendar.apple_profile') }}</x-button>
                <p class="mt-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('calendar.apple_profile_hint') }}</p>
            </div>
        </div>
    </section>
</x-layouts.app>
