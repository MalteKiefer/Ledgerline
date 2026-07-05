<x-layouts.app :title="__('pages.profile.title')">
    <h1 class="text-2xl font-semibold text-gray-900">{{ __('pages.profile.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600">
        {{ __('pages.profile.subtitle') }}
    </p>

    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-4">
            <x-user-avatar :user="$user" size="h-16 w-16" />
            <div>
                <p class="text-lg font-semibold text-gray-900">{{ $user->name }}</p>
                <p class="text-sm text-gray-600">{{ $user->email }}</p>
                @if ($user->avatar_url)
                    <form method="POST" action="{{ route('profile.avatar.refresh') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"><span class="inline-flex items-center gap-1.5"><x-icon name="arrow-path" class="h-3.5 w-3.5" />{{ __('pages.profile.refresh_avatar') }}</span></button>
                    </form>
                @endif
            </div>
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 border-t border-gray-100 pt-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('pages.profile.name') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $user->name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('pages.profile.email') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($user->email)
                        <a href="mailto:{{ $user->email }}" class="text-gray-900 hover:underline">{{ $user->email }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('pages.profile.email_verified') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $user->email_verified_at ? __('pages.profile.verified_yes', ['date' => $user->email_verified_at->format('Y-m-d')]) : __('pages.profile.verified_no') }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('pages.profile.pocketid_subject') }}</dt>
                <dd class="mt-1 break-all font-mono text-sm text-gray-900">{{ $user->oidc_sub ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('pages.profile.avatar') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ $user->avatar ? __('pages.profile.avatar_provided') : __('pages.profile.avatar_none') }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">{{ __('pages.profile.account_created') }}</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $user->created_at?->format('Y-m-d H:i') ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- CardDAV / CalDAV sync (one login serves contacts + calendars over /dav/) --}}
    <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('pages.profile.sync_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ __('pages.profile.sync_hint') }}</p>

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
                @if ($davQr)
                    <div>
                        <h3 class="text-sm font-medium text-gray-700">{{ __('contacts.qr_heading') }}</h3>
                        <div class="mt-2 inline-block rounded-md border border-gray-200 bg-white p-2">{!! $davQr !!}</div>
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
</x-layouts.app>
