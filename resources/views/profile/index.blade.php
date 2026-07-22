<x-layouts.app :title="__('pages.profile.title')">
    @php
        $initials = collect(explode(' ', trim($user->name ?? '')))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
        $initials = $initials !== '' ? $initials : '•';
        $hb = function (int $b): string {
            if ($b <= 0) return '0 B';
            $u = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = min((int) floor(log($b, 1024)), count($u) - 1);
            $v = $b / (1024 ** $i);
            return ($i === 0 ? (string) $b : rtrim(rtrim(number_format($v, 1), '0'), '.')).' '.$u[$i];
        };
        $global = auth()->user()->managesGlobalSettings();
    @endphp

    <div class="mx-auto w-full max-w-3xl">

    {{-- Hero: gradient avatar, name, email, host --}}
    <div class="flex flex-col items-center pt-2 text-center">
        @if ($user->avatar)
            <img src="{{ route('profile.avatar') }}" alt="" class="h-20 w-20 rounded-full object-cover shadow-lg shadow-accent/30">
        @else
            <span class="flex h-20 w-20 items-center justify-center rounded-full ll-accent text-2xl font-semibold shadow-lg shadow-accent/30">{{ $initials }}</span>
        @endif
        <p class="mt-3 text-xl font-bold text-gray-900 dark:text-gray-100">{{ $user->name ?: '—' }}</p>
        @if ($user->email)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
        @endif
        <p class="mt-1 inline-flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500"><x-icon name="lock-closed" class="h-3.5 w-3.5" />{{ request()->getHost() }}</p>
        @if ($user->avatar_url)
            <form method="POST" action="{{ route('profile.avatar.refresh') }}" class="mt-3">
                @csrf
                <button type="submit" class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium text-accent transition hover:bg-accent/10"><x-icon name="arrow-path" class="h-3.5 w-3.5" />{{ __('pages.profile.refresh_avatar') }}</button>
            </form>
        @endif
    </div>

    {{-- Stats band --}}
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @php
            $tiles = [
                ['icon' => 'calendar', 'tint' => '#3fae9f', 'value' => $user->created_at?->translatedFormat('M Y') ?: '—', 'label' => __('account.stat_member_since')],
                ['icon' => 'device-phone-mobile', 'tint' => '#3b9fd6', 'value' => (string) $deviceCount, 'label' => __('account.stat_devices'), 'sub' => __('account.stat_devices_of', ['n' => $deviceCount, 'max' => $deviceMax])],
                ['icon' => 'computer-desktop', 'tint' => '#9e70fa', 'value' => (string) $sessionCount, 'label' => __('account.stat_sessions')],
                ['icon' => 'circle-stack', 'tint' => '#d9a441', 'value' => $hb($storageUsed), 'label' => __('account.stat_storage'), 'sub' => $storageQuota > 0 ? __('account.storage_of', ['quota' => $hb($storageQuota)]) : __('account.storage_unlimited')],
            ];
        @endphp
        @foreach ($tiles as $t)
            <div class="ll-card flex flex-col gap-2 !p-4">
                <span class="ll-chip h-9 w-9" style="--chip: {{ $t['tint'] }}"><x-icon name="{{ $t['icon'] }}" class="h-5 w-5" /></span>
                <div class="min-w-0">
                    <p class="truncate text-lg font-bold leading-tight text-gray-900 dark:text-gray-100">{{ $t['value'] }}</p>
                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $t['label'] }}</p>
                    @isset($t['sub'])<p class="truncate text-[11px] text-gray-400 dark:text-gray-500">{{ $t['sub'] }}</p>@endisset
                </div>
            </div>
        @endforeach
    </div>

    {{-- Account & security --}}
    @php
        $account = [
            ['url' => route('profile.account'), 'icon' => 'user', 'tint' => '#7066f5', 'title' => __('account.nav_account'), 'desc' => __('account.nav_account_desc')],
            ['url' => route('profile.devices'), 'icon' => 'device-phone-mobile', 'tint' => '#3b9fd6', 'title' => __('account.nav_devices'), 'desc' => __('account.nav_devices_desc'), 'badge' => __('account.stat_devices_of', ['n' => $deviceCount, 'max' => $deviceMax])],
            ['url' => route('profile.sessions'), 'icon' => 'computer-desktop', 'tint' => '#9e70fa', 'title' => __('account.nav_sessions'), 'desc' => __('account.nav_sessions_desc'), 'badge' => (string) $sessionCount],
            ['url' => route('profile.encryption'), 'icon' => 'lock-closed', 'tint' => '#59ad6b', 'title' => __('account.nav_encryption'), 'desc' => __('account.nav_encryption_desc')],
        ];
        $personal = [
            ['url' => route('profile.appearance'), 'icon' => 'sun', 'tint' => '#e2915a', 'title' => __('account.nav_appearance'), 'desc' => __('account.nav_appearance_desc')],
            ['url' => route('settings.files.edit'), 'icon' => 'folder', 'tint' => '#3b9fd6', 'title' => __('settings.files_section'), 'desc' => __('settings.files_desc')],
            ['url' => route('settings.contacts.edit'), 'icon' => 'contacts', 'tint' => '#59ad6b', 'title' => __('settings.contacts_section'), 'desc' => __('settings.contacts_desc')],
            ['url' => route('settings.paperless.edit'), 'icon' => 'inbox-arrow-down', 'tint' => '#d9a441', 'title' => __('settings.paperless_section'), 'desc' => __('settings.paperless_desc')],
            ['url' => route('health.index'), 'icon' => 'heart', 'tint' => '#ef4444', 'title' => __('pages.profile.health_title'), 'desc' => __('pages.profile.health_desc')],
        ];
        $data = [
            ['url' => route('profile.export'), 'icon' => 'arrow-down-tray', 'tint' => '#3fae9f', 'title' => __('account.export_heading'), 'desc' => __('account.export_hint')],
            ['url' => route('profile.danger'), 'icon' => 'exclamation-triangle', 'tint' => '#ef4444', 'title' => __('account.delete_heading'), 'desc' => __('account.delete_hint'), 'danger' => true],
        ];
    @endphp

    @foreach ([['account.hub_account_heading', $account], ['account.hub_personal_heading', $personal], ['account.hub_data_heading', $data]] as [$heading, $rows])
        <h2 class="mt-7 mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __($heading) }}</h2>
        <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            @foreach ($rows as $r)
                <a href="{{ $r['url'] }}" class="group flex items-center gap-3.5 px-4 py-3.5 transition hover:bg-accent/5">
                    <span class="ll-chip h-9 w-9 shrink-0" style="--chip: {{ $r['tint'] }}"><x-icon name="{{ $r['icon'] }}" class="h-5 w-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold {{ ($r['danger'] ?? false) ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $r['title'] }}</span>
                        <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">{{ $r['desc'] }}</span>
                    </span>
                    @isset($r['badge'])<span class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500">{{ $r['badge'] }}</span>@endisset
                    <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600 transition group-hover:text-accent" />
                </a>
            @endforeach
        </div>
    @endforeach

    {{-- Administration (workspace-wide, admin only) --}}
    @if ($global)
        <h2 class="mt-7 mb-2 flex items-center gap-1.5 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('account.hub_admin_heading') }}<x-icon name="lock-closed" class="h-3 w-3" /></h2>
        <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            <a href="{{ route('settings') }}" class="group flex items-center gap-3.5 px-4 py-3.5 transition hover:bg-accent/5">
                <span class="ll-chip h-9 w-9 shrink-0" style="--chip: #6b7280"><x-icon name="server" class="h-5 w-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('account.nav_admin') }}</span>
                    <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">{{ __('account.nav_admin_desc') }}</span>
                </span>
                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600 transition group-hover:text-accent" />
            </a>
        </div>
    @endif

    </div>
</x-layouts.app>
