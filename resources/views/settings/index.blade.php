<x-layouts.app :title="__('settings.index_title')">
    <h1 class="text-2xl font-semibold text-gray-900">{{ __('settings.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('settings.subheading') }}</p>

    @php
        $global = auth()->user()->managesGlobalSettings();
        // Personal — apply to the signed-in user only.
        $personal = [
            ['url' => route('settings.calendar.edit'), 'title' => __('settings.calendar_section'), 'desc' => __('settings.calendar_desc')],
            ['url' => route('settings.contacts.edit'), 'title' => __('settings.contacts_section'), 'desc' => __('settings.contacts_desc')],
            ['url' => route('settings.reminders.edit'), 'title' => __('settings.reminders_section'), 'desc' => __('settings.reminders_desc')],
            ['url' => route('settings.files.edit'), 'title' => __('settings.files_section'), 'desc' => __('settings.files_desc')],
            ['url' => route('settings.paperless.edit'), 'title' => __('settings.paperless_section'), 'desc' => __('settings.paperless_desc')],
        ];
        // Administration — workspace-wide, admin group only.
        $admin = [
            ['url' => route('settings.gallery.edit'), 'title' => __('settings.gallery_section'), 'desc' => __('settings.gallery_desc')],
            ['url' => route('settings.mail.edit'), 'title' => __('settings.mail_section'), 'desc' => __('settings.mail_desc')],
            ['url' => route('settings.notifications.edit'), 'title' => __('settings.notifications_section'), 'desc' => __('settings.notifications_desc')],
            ['url' => route('settings.downloads.edit'), 'title' => __('settings.downloads_section'), 'desc' => __('settings.downloads_desc')],
            ['url' => route('settings.backup.index'), 'title' => __('settings.backup_section'), 'desc' => __('settings.backup_desc')],
        ];
    @endphp

    {{-- Personal settings --}}
    <section class="mt-8">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('settings.personal_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ __('settings.personal_subheading') }}</p>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($personal as $card)
                <a href="{{ $card['url'] }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-gray-300 sm:p-6">
                    <h3 class="text-base font-semibold text-gray-900">{{ $card['title'] }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $card['desc'] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Administration (workspace-wide) --}}
    @if ($global)
        <section class="mt-10">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('settings.admin_heading') }}</h2>
                <x-icon name="lock-closed" class="h-3.5 w-3.5 text-gray-400" />
            </div>
            <p class="mt-1 text-sm text-gray-500">{{ __('settings.admin_note') }}</p>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($admin as $card)
                    <a href="{{ $card['url'] }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-gray-300 sm:p-6">
                        <h3 class="text-base font-semibold text-gray-900">{{ $card['title'] }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $card['desc'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
