{{-- Shared CardDAV sync block. Expects $davUrl, $username, $hasPassword, $qr (data URI). --}}
<section class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.sync_section') }}</h2>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('settings.sync_block_hint') }}</p>

    @unless ($hasPassword)
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-500/40 dark:bg-amber-500/10">
            <p class="font-medium text-amber-800 dark:text-amber-200">{{ __('contacts.no_password') }}</p>
            <p class="mt-1 text-amber-700 dark:text-amber-300">{{ __('contacts.no_password_hint') }}</p>
            <x-button variant="primary" :href="route('profile.account')" class="mt-3">{{ __('contacts.set_password') }}</x-button>
        </div>
    @endunless

    <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-[8rem_1fr]">
        <dt class="text-gray-500 dark:text-gray-400">{{ __('contacts.dav_url') }}</dt>
        <dd class="select-all break-all font-mono text-gray-900 dark:text-gray-100">{{ $davUrl }}</dd>
        <dt class="text-gray-500 dark:text-gray-400">{{ __('contacts.username') }}</dt>
        <dd class="select-all font-mono text-gray-900 dark:text-gray-100">{{ $username }}</dd>
    </dl>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.setup_hint') }}</p>

    <div class="mt-5 grid gap-6 sm:grid-cols-2">
        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('contacts.qr_heading') }}</h3>
            <div class="mt-2 inline-block rounded-md border border-gray-200 dark:border-gray-800 bg-white p-2">
                <img src="{{ $qr }}" alt="{{ __('contacts.qr_heading') }}" class="h-40 w-40" />
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.qr_hint') }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Apple (iOS / macOS)</h3>
            <x-button variant="primary" :href="route('settings.contacts.profile')" class="mt-2">{{ __('contacts.apple_profile') }}</x-button>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('contacts.apple_profile_hint') }}</p>
        </div>
    </div>
</section>
