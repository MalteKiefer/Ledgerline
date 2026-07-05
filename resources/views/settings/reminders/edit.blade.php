<x-layouts.app :title="__('settings.reminders_heading')">
    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.reminders_heading') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('settings.reminders_heading') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('settings.reminders_subheading') }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('settings.reminders.update') }}" class="mt-6 max-w-lg rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @php $labels = ['desktop' => __('todos.channel_browser'), 'ntfy' => __('todos.channel_ntfy'), 'mail' => __('todos.channel_mail'), 'webhook' => __('todos.channel_webhook')]; @endphp
        <div class="space-y-2">
            @foreach ($all as $ch)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="channels[]" value="{{ $ch }}" @checked(in_array($ch, $channels, true))
                        class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                    {{ $labels[$ch] ?? $ch }}
                </label>
            @endforeach
        </div>
        <div class="mt-5">
            <x-button variant="primary" type="submit">{{ __('settings.save') }}</x-button>
        </div>
    </form>
</x-layouts.app>
