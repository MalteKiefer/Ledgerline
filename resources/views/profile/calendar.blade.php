<x-layouts.app :title="__('calendar.set_heading')">
    <div class="mx-auto w-full max-w-[1700px]">
        @include('profile._header', ['title' => __('calendar.set_heading')])

        @php
            $rows = [
                ['key' => 'cal_week_numbers', 'label' => __('calendar.set_week_numbers'), 'options' => ['0' => __('common.off'), '1' => __('common.on')]],
                ['key' => 'cal_week_start', 'label' => __('calendar.set_week_start'), 'options' => ['mon' => __('calendar.week_mon'), 'sun' => __('calendar.week_sun')]],
                ['key' => 'cal_default_view', 'label' => __('calendar.set_default_view'), 'options' => ['month' => __('calendar.view_month'), 'week' => __('calendar.view_week'), 'day' => __('calendar.view_day')]],
            ];
            $current = [
                'cal_week_numbers' => ($prefs['cal_week_numbers'] ?? false) ? '1' : '0',
                'cal_week_start' => $prefs['cal_week_start'] ?? 'mon',
                'cal_default_view' => $prefs['cal_default_view'] ?? 'month',
            ];
        @endphp

        <h2 class="mt-5 mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('calendar.set_heading') }}</h2>
        <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10">
            @foreach ($rows as $row)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</span>
                    <div class="flex gap-1 rounded-xl bg-black/5 dark:bg-white/5 p-1">
                        @foreach ($row['options'] as $value => $optLabel)
                            <form method="POST" action="{{ route('preferences.update') }}">
                                @csrf
                                <input type="hidden" name="{{ $row['key'] }}" value="{{ $value }}">
                                <button type="submit" @class([
                                    'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                                    'll-accent shadow-sm shadow-accent/30' => ($current[$row['key']] ?? '') === (string) $value,
                                    'text-gray-500 dark:text-gray-400 hover:text-accent' => ($current[$row['key']] ?? '') !== (string) $value,
                                ])>{{ $optLabel }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Working hours (shown in the week/day time grid) --}}
        <h2 class="mt-6 mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('calendar.set_hours') }}</h2>
        <div class="ll-card">
            <p class="mb-3 text-xs text-gray-400 dark:text-gray-500">{{ __('calendar.set_hours_hint') }}</p>
            <form method="POST" action="{{ route('preferences.update') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('calendar.hours_from') }}</label>
                    <select name="cal_day_start" class="mt-1 rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
                        @for ($h = 0; $h <= 23; $h++)
                            <option value="{{ $h }}" @selected((int) ($prefs['cal_day_start'] ?? 8) === $h)>{{ sprintf('%02d:00', $h) }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('calendar.hours_to') }}</label>
                    <select name="cal_day_end" class="mt-1 rounded-md border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
                        @for ($h = 1; $h <= 24; $h++)
                            <option value="{{ $h }}" @selected((int) ($prefs['cal_day_end'] ?? 17) === $h)>{{ sprintf('%02d:00', $h) }}</option>
                        @endfor
                    </select>
                </div>
                <x-button type="submit" variant="primary" size="sm">{{ __('common.save') }}</x-button>
            </form>
        </div>
    </div>
</x-layouts.app>
