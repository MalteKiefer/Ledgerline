<x-layouts.app :title="__('settings.system_section')">
    <x-page-heading :title="__('settings.system_section')" :subtitle="__('settings.system_desc')" />

    {{-- Operational status --}}
    <div class="mt-6 max-w-2xl rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.system_status_heading') }}</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.system_status_desc') }}</p>
        <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-md border border-gray-100 dark:border-gray-800 p-3">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('settings.system_queue_pending') }}</dt>
                <dd class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $status['queue']['pending'] }}</dd>
            </div>
            <div class="rounded-md border border-gray-100 dark:border-gray-800 p-3">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('settings.system_queue_failed') }}</dt>
                <dd class="mt-0.5 text-lg font-semibold {{ $status['queue']['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $status['queue']['failed'] }}</dd>
            </div>
            <div class="rounded-md border border-gray-100 dark:border-gray-800 p-3">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('settings.system_errors_unresolved') }}</dt>
                <dd class="mt-0.5 text-lg font-semibold {{ $status['errors']['unresolved'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $status['errors']['unresolved'] }}</dd>
            </div>
            <div class="rounded-md border border-gray-100 dark:border-gray-800 p-3">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('settings.system_last_backup') }}</dt>
                <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $status['backup']['lastSuccessAt'] ? \Illuminate\Support\Carbon::parse($status['backup']['lastSuccessAt'])->diffForHumans() : __('settings.system_never') }}</dd>
            </div>
            <div class="rounded-md border border-gray-100 dark:border-gray-800 p-3">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('settings.system_scheduler_last') }}</dt>
                <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $status['scheduler']['lastRunAt'] ? \Illuminate\Support\Carbon::parse($status['scheduler']['lastRunAt'])->diffForHumans() : __('settings.system_never') }}</dd>
            </div>
            <div class="rounded-md border border-gray-100 dark:border-gray-800 p-3">
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('settings.system_disk_free') }}</dt>
                <dd class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $status['disk']['free'] ? \Illuminate\Support\Number::fileSize($status['disk']['free']) : '—' }}</dd>
            </div>
        </dl>

        <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('settings.system_storage') }}</h3>
        <dl class="mt-2 space-y-1.5 text-sm">
            @foreach (['files' => __('settings.system_storage_files'), 'gallery' => __('settings.system_storage_gallery'), 'database' => __('settings.system_storage_database')] as $key => $label)
                <div class="flex items-center justify-between">
                    <dt class="text-gray-600 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="font-mono text-gray-800 dark:text-gray-200">{{ \Illuminate\Support\Number::fileSize($status['storage'][$key]) }}</dd>
                </div>
            @endforeach
            <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-1.5">
                <dt class="font-medium text-gray-700 dark:text-gray-300">{{ __('settings.system_storage_total') }}</dt>
                <dd class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ \Illuminate\Support\Number::fileSize($status['storage']['total']) }}</dd>
            </div>
        </dl>

        {{-- Growth trend (daily snapshots) --}}
        @php
            $pts = $trend['points'];
            $spark = '';
            if (count($pts) >= 2) {
                $vals = array_map(fn ($p) => $p['total'], $pts);
                $min = min($vals);
                $range = max(1, max($vals) - $min);
                $n = count($pts);
                $coords = [];
                foreach ($pts as $i => $p) {
                    $x = round(($i / ($n - 1)) * 100, 1);
                    $y = round(26 - (($p['total'] - $min) / $range) * 24, 1);
                    $coords[] = $x.','.$y;
                }
                $spark = implode(' ', $coords);
            }
        @endphp
        <div class="mt-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('settings.system_trend') }}</h3>
                @if ($trend['deltaDays'] > 0)
                    <span class="text-xs {{ $trend['deltaBytes'] >= 0 ? 'text-gray-500 dark:text-gray-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ $trend['deltaBytes'] >= 0 ? '+' : '−' }}{{ \Illuminate\Support\Number::fileSize(abs($trend['deltaBytes'])) }}
                        · {{ __('settings.system_trend_days', ['n' => $trend['deltaDays']]) }}
                    </span>
                @endif
            </div>
            @if ($spark !== '')
                <svg viewBox="0 0 100 28" preserveAspectRatio="none" class="mt-2 h-12 w-full text-gray-400 dark:text-gray-500" aria-hidden="true">
                    <polyline points="{{ $spark }}" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                </svg>
            @else
                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ __('settings.system_trend_collecting') }}</p>
            @endif
        </div>

        <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">{{ __('settings.system_metrics_hint') }}</p>
    </div>

    {{-- In-app error log --}}
    <div class="mt-6 max-w-2xl rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.system_errors_heading') }}</h2>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('settings.system_errors_desc') }}</p>
        @if ($errors->isEmpty())
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('settings.system_no_errors') }}</p>
        @else
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($errors as $e)
                    <li class="py-2.5 {{ $e->resolved_at ? 'opacity-50' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-200" title="{{ $e->exception }}">{{ class_basename($e->exception) }}</p>
                                <p class="mt-0.5 break-words text-xs text-gray-500 dark:text-gray-400">{{ $e->message }}</p>
                                <p class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                                    <span class="font-mono">{{ $e->file }}:{{ $e->line }}</span>
                                    · {{ __('settings.system_error_count', ['n' => $e->count]) }}
                                    · {{ \Illuminate\Support\Carbon::parse($e->last_seen_at)->diffForHumans() }}
                                </p>
                            </div>
                            @unless ($e->resolved_at)
                                <form method="POST" action="{{ route('settings.system.errors.resolve', $e) }}" class="shrink-0">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-gray-200 dark:border-gray-700 px-2 py-1 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800"><x-icon name="check" class="h-3.5 w-3.5" />{{ __('settings.system_error_resolve') }}</button>
                                </form>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

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
