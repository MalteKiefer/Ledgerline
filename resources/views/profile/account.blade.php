<x-layouts.app :title="__('account.nav_account')">
    <div class="mx-auto w-full max-w-3xl">
        @include('profile._header', ['title' => __('account.nav_account')])

        <div class="mt-5 ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            @php
                $rows = [
                    ['icon' => 'user', 'tint' => '#7066f5', 'label' => __('pages.profile.name'), 'value' => $user->name ?: '—'],
                    ['icon' => 'envelope', 'tint' => '#3b9fd6', 'label' => __('pages.profile.email'), 'value' => $user->email ?: '—'],
                    ['icon' => 'shield-check', 'tint' => '#59ad6b', 'label' => __('pages.profile.email_verified'), 'value' => $user->email_verified_at ? __('pages.profile.verified_yes', ['date' => $user->email_verified_at->format('Y-m-d')]) : __('pages.profile.verified_no')],
                    ['icon' => 'finger-print', 'tint' => '#6b7280', 'label' => __('pages.profile.pocketid_subject'), 'value' => $user->oidc_sub ?: '—', 'mono' => true],
                    ['icon' => 'identification', 'tint' => '#3b9fd6', 'label' => __('pages.profile.avatar'), 'value' => $user->avatar ? __('pages.profile.avatar_provided') : __('pages.profile.avatar_none')],
                    ['icon' => 'clock', 'tint' => '#3fae9f', 'label' => __('pages.profile.account_created'), 'value' => $user->created_at?->format('Y-m-d H:i') ?: '—'],
                ];
            @endphp
            @foreach ($rows as $r)
                <div class="flex items-center gap-3.5 px-4 py-3">
                    <span class="ll-chip h-8 w-8 shrink-0" style="--chip: {{ $r['tint'] }}"><x-icon name="{{ $r['icon'] }}" class="h-4 w-4" /></span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $r['label'] }}</span>
                    <span class="ml-auto min-w-0 truncate text-right text-sm {{ ($r['mono'] ?? false) ? 'font-mono text-xs' : '' }} text-gray-900 dark:text-gray-100">{{ $r['value'] }}</span>
                </div>
            @endforeach
        </div>
        <p class="mt-3 px-1 text-xs text-gray-400 dark:text-gray-500">{{ __('pages.profile.subtitle') }}</p>
    </div>
</x-layouts.app>
