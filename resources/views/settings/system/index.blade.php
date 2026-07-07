<x-layouts.app :title="__('settings.system_section')">
    <x-page-heading :title="__('settings.system_section')" :subtitle="__('settings.system_desc')" />

    <div class="mt-6 max-w-2xl rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.system_cron_heading') }}</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.system_cron_hint') }}</p>
        <div class="-mx-4 mt-3 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800 text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        <th class="py-1.5 pr-3 font-medium">{{ __('settings.system_task') }}</th>
                        <th class="py-1.5 pr-3 font-medium">{{ __('settings.system_schedule') }}</th>
                        <th class="py-1.5 pr-3 font-medium">{{ __('settings.system_last_run') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $t)
                        <tr class="border-b border-gray-50 dark:border-gray-800/50">
                            <td class="py-1.5 pr-3 font-mono text-gray-800 dark:text-gray-200">{{ $t['name'] }}</td>
                            <td class="py-1.5 pr-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $t['expression'] }}</td>
                            <td class="py-1.5 pr-3">
                                @if ($t['lastAt'])
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="{{ $t['lastOk'] ? 'check' : 'x-mark' }}" class="h-4 w-4 {{ $t['lastOk'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                                        <span class="text-gray-600 dark:text-gray-400" title="{{ $t['lastAt'] }}">{{ \Illuminate\Support\Carbon::parse($t['lastAt'])->diffForHumans() }}</span>
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">{{ __('settings.system_never') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
