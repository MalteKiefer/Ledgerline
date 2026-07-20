<x-layouts.app :title="__('settings.contacts_section')">
    <x-page-heading :title="__('settings.contacts_section')" :subtitle="__('settings.contacts_desc')" />

    @php
        $labels = [
            'desktop' => __('settings.contacts_ch_desktop'),
            'ntfy' => __('settings.contacts_ch_ntfy'),
            'mail' => __('settings.contacts_ch_mail'),
            'webhook' => __('settings.contacts_ch_webhook'),
        ];
    @endphp

    <form method="POST" action="{{ route('settings.contacts.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <div class="ll-card">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.contacts_birthday') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.contacts_alert_hint') }}</p>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($channels as $ch)
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="birthday[]" value="{{ $ch }}" @checked(in_array($ch, $birthday, true)) class="rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0">
                        {{ $labels[$ch] }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="ll-card">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.contacts_anniversary') }}</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.contacts_alert_hint') }}</p>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($channels as $ch)
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="anniversary[]" value="{{ $ch }}" @checked(in_array($ch, $anniversary, true)) class="rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0">
                        {{ $labels[$ch] }}
                    </label>
                @endforeach
            </div>
        </div>

        <p class="text-xs text-gray-400 dark:text-gray-500">{{ __('settings.contacts_zk_note') }}</p>

        <div class="flex justify-end">
            <x-button variant="primary" type="submit">{{ __('common.save') }}</x-button>
        </div>
    </form>
</x-layouts.app>
