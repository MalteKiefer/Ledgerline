{{-- Shared CardDAV/CalDAV sync block. Expects $credential, $davUrl, $qr. --}}
<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
    <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.sync_section') }}</h2>
    <p class="mt-1 text-sm text-gray-500">{{ __('settings.sync_block_hint') }}</p>

    @if (session('dav_password'))
        <div class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm">
            <p class="font-medium text-amber-800">{{ __('contacts.password_once') }}</p>
            <dl class="mt-3 grid gap-2 sm:grid-cols-[8rem_1fr]">
                <dt class="text-gray-500">{{ __('contacts.username') }}</dt>
                <dd class="select-all font-mono text-gray-900">{{ session('dav_username') }}</dd>
                <dt class="text-gray-500">{{ __('contacts.password') }}</dt>
                <dd class="select-all break-all font-mono text-gray-900">{{ session('dav_password') }}</dd>
            </dl>
        </div>
    @endif

    @if ($credential === null)
        <form method="POST" action="{{ route('settings.contacts.generate') }}" class="mt-4">
            @csrf
            <x-button variant="primary">{{ __('contacts.enable') }}</x-button>
        </form>
    @else
        <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-[8rem_1fr]">
            <dt class="text-gray-500">{{ __('contacts.dav_url') }}</dt>
            <dd class="select-all break-all font-mono text-gray-900">{{ $davUrl }}</dd>
            <dt class="text-gray-500">{{ __('contacts.username') }}</dt>
            <dd class="select-all font-mono text-gray-900">{{ $credential->username }}</dd>
        </dl>
        <p class="mt-3 text-xs text-gray-500">{{ __('contacts.setup_hint') }}</p>

        <div class="mt-5 grid gap-6 sm:grid-cols-2">
            @if ($qr)
                <div>
                    <h3 class="text-sm font-medium text-gray-700">{{ __('contacts.qr_heading') }}</h3>
                    <div class="mt-2 inline-block rounded-md border border-gray-200 bg-white p-2">{!! $qr !!}</div>
                    <p class="mt-1 text-xs text-gray-500">{{ __('contacts.qr_hint') }}</p>
                </div>
            @endif
            <div>
                <h3 class="text-sm font-medium text-gray-700">Apple (iOS / macOS)</h3>
                <x-button variant="primary" :href="route('settings.contacts.profile')" class="mt-2">{{ __('contacts.apple_profile') }}</x-button>
                <p class="mt-1 text-xs text-gray-500">{{ __('contacts.apple_profile_hint') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.contacts.generate') }}" class="mt-5">
            @csrf
            <x-button variant="secondary">{{ __('contacts.regenerate') }}</x-button>
        </form>
    @endif
</section>
