<x-layouts.app :title="__('settings.index_title')">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('settings.subheading') }}</p>

    @php
        $global = auth()->user()->managesGlobalSettings();
        // Personal — apply to the signed-in user only.
        $personal = [
            ['url' => route('settings.files.edit'), 'title' => __('settings.files_section'), 'desc' => __('settings.files_desc'), 'icon' => 'folder', 'tint' => '#3b9fd6'],
            ['url' => route('settings.contacts.edit'), 'title' => __('settings.contacts_section'), 'desc' => __('settings.contacts_desc'), 'icon' => 'contacts', 'tint' => '#59ad6b'],
            ['url' => route('settings.paperless.edit'), 'title' => __('settings.paperless_section'), 'desc' => __('settings.paperless_desc'), 'icon' => 'inbox-arrow-down', 'tint' => '#e2915a'],
        ];
        // Administration — workspace-wide, admin group only.
        $admin = [
            ['url' => route('settings.company.edit'), 'title' => __('settings.company_section'), 'desc' => __('settings.company_desc'), 'icon' => 'identification', 'tint' => '#7066f5'],
            ['url' => route('settings.notifications.edit'), 'title' => __('settings.notifications_section'), 'desc' => __('settings.notifications_desc'), 'icon' => 'bell', 'tint' => '#d9a441'],
            ['url' => route('settings.backup.index'), 'title' => __('settings.backup_section'), 'desc' => __('settings.backup_desc'), 'icon' => 'archive-box', 'tint' => '#3fae9f'],
            ['url' => route('settings.security.edit'), 'title' => __('settings.security_section'), 'desc' => __('settings.security_desc'), 'icon' => 'shield-check', 'tint' => '#9e70fa'],
            ['url' => route('settings.system.edit'), 'title' => __('settings.system_section'), 'desc' => __('settings.system_desc'), 'icon' => 'server', 'tint' => '#6b7280'],
        ];
        $byTitle = fn ($a, $b) => strcasecmp($a['title'], $b['title']);
        usort($personal, $byTitle);
        usort($admin, $byTitle);
    @endphp

    {{-- Personal settings --}}
    <section class="mt-8">
        <h2 class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('settings.personal_heading') }}</h2>
        <p class="mt-1 px-1 text-sm text-gray-500 dark:text-gray-400">{{ __('settings.personal_subheading') }}</p>
        <div class="mt-3 ll-card overflow-hidden !p-0 divide-y divide-black/[0.06] dark:divide-white/10">
            @foreach ($personal as $card)
                <a href="{{ $card['url'] }}" class="group flex items-center gap-3.5 px-4 py-3.5 transition hover:bg-accent/5">
                    <span class="ll-chip" style="--chip: {{ $card['tint'] }}"><x-icon :name="$card['icon']" class="h-5 w-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $card['title'] }}</span>
                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $card['desc'] }}</span>
                    </span>
                    <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600 transition group-hover:text-accent" />
                </a>
            @endforeach
        </div>
        {{-- Danger zone: reset the password manager (client-side, opens in the manager) --}}
        <a href="{{ route('passwords.index') }}?reset=1" class="group mt-3 ll-card flex items-center gap-3.5 !py-3.5 transition hover:border-red-300 dark:hover:border-red-800">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white shadow-sm" style="background:#ef4444"><x-icon name="exclamation-triangle" class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-red-600 dark:text-red-400">{{ __('passwords.reset') }}</span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ __('settings.passwords_reset_desc') }}</span>
            </span>
            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600 transition group-hover:text-red-500" />
        </a>
    </section>

    {{-- Administration (workspace-wide) --}}
    @if ($global)
        <section class="mt-8">
            <div class="flex items-center gap-2 px-1">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('settings.admin_heading') }}</h2>
                <x-icon name="lock-closed" class="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
            </div>
            <p class="mt-1 px-1 text-sm text-gray-500 dark:text-gray-400">{{ __('settings.admin_note') }}</p>
            <div class="mt-3 ll-card overflow-hidden !p-0 divide-y divide-black/[0.06] dark:divide-white/10">
                @foreach ($admin as $card)
                    <a href="{{ $card['url'] }}" class="group flex items-center gap-3.5 px-4 py-3.5 transition hover:bg-accent/5">
                        <span class="ll-chip" style="--chip: {{ $card['tint'] }}"><x-icon :name="$card['icon']" class="h-5 w-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $card['title'] }}</span>
                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $card['desc'] }}</span>
                        </span>
                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600 transition group-hover:text-accent" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
