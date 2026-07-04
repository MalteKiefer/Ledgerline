<x-layouts.app :title="__('settings.calendar_heading')">
    @php $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm'; @endphp

    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.calendar_section') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('settings.calendar_heading') }}</h1>

    <form method="POST" action="{{ route('settings.calendar.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        {{-- Display --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.calendar_display_heading') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('settings.calendar_display_hint') }}</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="calendar_week_start" class="block text-sm font-medium text-gray-700">{{ __('settings.calendar_week_start') }}</label>
                    <select id="calendar_week_start" name="calendar_week_start" class="{{ $input }}">
                        <option value="monday" @selected(old('calendar_week_start', $settings->calendar_week_start) === 'monday')>{{ __('settings.calendar_week_start_monday') }}</option>
                        <option value="sunday" @selected(old('calendar_week_start', $settings->calendar_week_start) === 'sunday')>{{ __('settings.calendar_week_start_sunday') }}</option>
                    </select>
                    @error('calendar_week_start')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="calendar_default_event_minutes" class="block text-sm font-medium text-gray-700">{{ __('settings.calendar_default_event_minutes') }}</label>
                    <input type="number" min="5" max="1440" id="calendar_default_event_minutes" name="calendar_default_event_minutes"
                        value="{{ old('calendar_default_event_minutes', $settings->calendar_default_event_minutes ?? 60) }}" class="{{ $input }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('settings.calendar_default_event_minutes_hint') }}</p>
                    @error('calendar_default_event_minutes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <label class="mt-4 flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="calendar_week_numbers" value="1" @checked(old('calendar_week_numbers', $settings->calendar_week_numbers)) class="rounded border-gray-300">
                {{ __('settings.calendar_week_numbers') }}
            </label>
        </div>

        {{-- Contact-derived calendars --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.calendar_contacts_heading') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('settings.calendar_contacts_hint') }}</p>
            <label class="mt-4 flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="calendar_birthdays_enabled" value="1" @checked(old('calendar_birthdays_enabled', $settings->calendar_birthdays_enabled)) class="rounded border-gray-300">
                {{ __('settings.calendar_birthdays_enabled') }}
            </label>
            <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="calendar_anniversaries_enabled" value="1" @checked(old('calendar_anniversaries_enabled', $settings->calendar_anniversaries_enabled)) class="rounded border-gray-300">
                {{ __('settings.calendar_anniversaries_enabled') }}
            </label>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('settings.save') }}</button>
        </div>
    </form>
</x-layouts.app>
