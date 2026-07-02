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

    @php $vaultConfigured = \App\Models\Vault::current() !== null; @endphp
    @if ($vaultConfigured)
        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('vault.change_title') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('vault.change_hint') }}</p>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('vault-change'))"
                class="mt-4 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('vault.change') }}</button>
        </div>
    @endif
</x-layouts.app>
