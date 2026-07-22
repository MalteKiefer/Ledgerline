<x-layouts.app :title="__('settings.index_title')">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('settings.admin_note') }}</p>

    @php
        // Administration only — personal preferences moved to the profile hub.
        $admin = [
            ['url' => route('settings.files.limits'), 'title' => __('settings.files_limits_heading'), 'desc' => __('settings.files_limits_hint'), 'icon' => 'folder', 'tint' => '#3b9fd6'],
            ['url' => route('settings.company.edit'), 'title' => __('settings.company_section'), 'desc' => __('settings.company_desc'), 'icon' => 'identification', 'tint' => '#7066f5'],
            ['url' => route('settings.notifications.edit'), 'title' => __('settings.notifications_section'), 'desc' => __('settings.notifications_desc'), 'icon' => 'bell', 'tint' => '#d9a441'],
            ['url' => route('settings.backup.index'), 'title' => __('settings.backup_section'), 'desc' => __('settings.backup_desc'), 'icon' => 'archive-box', 'tint' => '#3fae9f'],
            ['url' => route('settings.security.edit'), 'title' => __('settings.security_section'), 'desc' => __('settings.security_desc'), 'icon' => 'shield-check', 'tint' => '#9e70fa'],
            ['url' => route('settings.system.edit'), 'title' => __('settings.system_section'), 'desc' => __('settings.system_desc'), 'icon' => 'server', 'tint' => '#6b7280'],
        ];
        usort($admin, fn ($a, $b) => strcasecmp($a['title'], $b['title']));
    @endphp

    <section class="mt-8">
        <div class="flex items-center gap-2 px-1">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('settings.admin_heading') }}</h2>
            <x-icon name="lock-closed" class="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
        </div>
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
</x-layouts.app>
