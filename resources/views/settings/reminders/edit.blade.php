<x-layouts.app :title="__('settings.reminders_heading')">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.reminders_heading') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.reminders_heading') }}</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('settings.reminders_subheading') }}</p>


    <form method="POST" action="{{ route('settings.reminders.update') }}" class="mt-6 max-w-lg rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
        @csrf
        @method('PUT')
        @php $labels = ['desktop' => __('todos.channel_browser'), 'ntfy' => __('todos.channel_ntfy'), 'mail' => __('todos.channel_mail'), 'webhook' => __('todos.channel_webhook')]; @endphp
        <div class="space-y-2">
            @foreach ($all as $ch)
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="channels[]" value="{{ $ch }}" @checked(in_array($ch, $channels, true))
                        class="rounded border-gray-300 dark:border-gray-700 text-gray-800 dark:text-gray-200 focus:ring-gray-500">
                    {{ $labels[$ch] ?? $ch }}
                </label>
            @endforeach
        </div>
        <div class="mt-5">
            <x-button variant="primary" type="submit">{{ __('settings.save') }}</x-button>
        </div>
    </form>
</x-layouts.app>
