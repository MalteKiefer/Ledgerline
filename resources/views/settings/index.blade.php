<x-layouts.app :title="__('settings.index_title')">
    <h1 class="text-2xl font-semibold text-gray-900">{{ __('settings.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('settings.subheading') }}</p>

    @php
        // Personal settings everyone sees; the workspace-wide (infra) sections
        // only for members of the admin group.
        $global = auth()->user()->managesGlobalSettings();
        $cards = collect([
            ['url' => route('settings.calendar.edit'), 'title' => __('settings.calendar_section'), 'desc' => __('settings.calendar_desc'), 'global' => false],
            ['url' => route('settings.gallery.edit'), 'title' => __('settings.gallery_section'), 'desc' => __('settings.gallery_desc'), 'global' => true],
            ['url' => route('settings.notifications.edit'), 'title' => __('settings.notifications_section'), 'desc' => __('settings.notifications_desc'), 'global' => true],
            ['url' => route('settings.backup.index'), 'title' => __('settings.backup_section'), 'desc' => __('settings.backup_desc'), 'global' => true],
            ['url' => route('settings.mail.edit'), 'title' => __('settings.mail_section'), 'desc' => __('settings.mail_desc'), 'global' => true],
            ['url' => route('settings.paperless.edit'), 'title' => __('settings.paperless_section'), 'desc' => __('settings.paperless_desc'), 'global' => true],
        ])->reject(fn ($c) => $c['global'] && ! $global)->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)->values();
    @endphp

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($cards as $card)
            <a href="{{ $card['url'] }}"
                class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
                <h2 class="text-base font-semibold text-gray-900">{{ $card['title'] }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $card['desc'] }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.app>
