<x-layouts.app :title="__('settings.system_section')">
    <x-page-heading :title="__('settings.system_section')" :subtitle="__('settings.system_desc')" />

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    {{-- Operational status --}}
    <div class="ll-card xl:col-span-2">
        <h2 class="text-sm font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('settings.system_status_heading') }}</h2>
        <p class="mt-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_status_desc') }}</p>
        <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="ll-card">
                <dt class="text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_queue_pending') }}</dt>
                <dd class="mt-0.5 text-lg font-semibold text-md-on-surface dark:text-md-on-surface">{{ $status['queue']['pending'] }}</dd>
            </div>
            <div class="ll-card">
                <dt class="text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_queue_failed') }}</dt>
                <dd class="mt-0.5 text-lg font-semibold {{ $status['queue']['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-md-on-surface dark:text-md-on-surface' }}">{{ $status['queue']['failed'] }}</dd>
            </div>
            <div class="ll-card">
                <dt class="text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_errors_unresolved') }}</dt>
                <dd class="mt-0.5 text-lg font-semibold {{ $status['errors']['unresolved'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-md-on-surface dark:text-md-on-surface' }}">{{ $status['errors']['unresolved'] }}</dd>
            </div>
            <div class="ll-card">
                <dt class="text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_last_backup') }}</dt>
                <dd class="mt-0.5 text-sm font-medium text-md-on-surface dark:text-md-on-surface">{{ $status['backup']['lastSuccessAt'] ? \Illuminate\Support\Carbon::parse($status['backup']['lastSuccessAt'])->diffForHumans() : __('settings.system_never') }}</dd>
            </div>
            <div class="ll-card">
                <dt class="text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_scheduler_last') }}</dt>
                <dd class="mt-0.5 text-sm font-medium text-md-on-surface dark:text-md-on-surface">{{ $status['scheduler']['lastRunAt'] ? \Illuminate\Support\Carbon::parse($status['scheduler']['lastRunAt'])->diffForHumans() : __('settings.system_never') }}</dd>
            </div>
            <div class="ll-card">
                <dt class="text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_disk_free') }}</dt>
                <dd class="mt-0.5 text-sm font-medium text-md-on-surface dark:text-md-on-surface">{{ $status['disk']['free'] ? \Illuminate\Support\Number::fileSize($status['disk']['free']) : '—' }}</dd>
            </div>
        </dl>

        <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_storage') }}</h3>
        <dl class="mt-2 space-y-1.5 text-sm">
            @foreach (['database' => __('settings.system_storage_database')] as $key => $label)
                <div class="flex items-center justify-between">
                    <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ $label }}</dt>
                    <dd class="font-mono text-md-on-surface dark:text-md-on-surface">{{ \Illuminate\Support\Number::fileSize($status['storage'][$key]) }}</dd>
                </div>
            @endforeach
            <div class="flex items-center justify-between border-t border-md-outline-variant dark:border-md-outline-variant pt-1.5">
                <dt class="font-medium text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_storage_total') }}</dt>
                <dd class="font-mono font-medium text-md-on-surface dark:text-md-on-surface">{{ \Illuminate\Support\Number::fileSize($status['storage']['total']) }}</dd>
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
                <h3 class="text-xs font-semibold uppercase tracking-wide text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_trend') }}</h3>
                @if ($trend['deltaDays'] > 0)
                    <span class="text-xs {{ $trend['deltaBytes'] >= 0 ? 'text-md-on-surface-var dark:text-md-on-surface-var' : 'text-green-600 dark:text-green-400' }}">
                        {{ $trend['deltaBytes'] >= 0 ? '+' : '−' }}{{ \Illuminate\Support\Number::fileSize(abs($trend['deltaBytes'])) }}
                        · {{ __('settings.system_trend_days', ['n' => $trend['deltaDays']]) }}
                    </span>
                @endif
            </div>
            @if ($spark !== '')
                <svg viewBox="0 0 100 28" preserveAspectRatio="none" class="mt-2 h-12 w-full text-md-on-surface-var dark:text-md-on-surface-var" aria-hidden="true">
                    <polyline points="{{ $spark }}" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                </svg>
            @else
                <p class="mt-2 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_trend_collecting') }}</p>
            @endif
        </div>

        <p class="mt-4 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_metrics_hint') }}</p>
    </div>

    {{-- In-app error log --}}
    <div class="ll-card">
        <h2 class="text-sm font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('settings.system_errors_heading') }}</h2>
        <p class="mt-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_errors_desc') }}</p>
        @if ($errors->isEmpty())
            <p class="mt-3 text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_no_errors') }}</p>
        @else
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($errors as $e)
                    <li class="py-2.5 {{ $e->resolved_at ? 'opacity-50' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-md-on-surface dark:text-md-on-surface" title="{{ $e->exception }}">{{ class_basename($e->exception) }}</p>
                                <p class="mt-0.5 break-words text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ $e->message }}</p>
                                <p class="mt-0.5 text-[11px] text-md-on-surface-var dark:text-md-on-surface-var">
                                    <span class="font-mono">{{ $e->file }}:{{ $e->line }}</span>
                                    · {{ __('settings.system_error_count', ['n' => $e->count]) }}
                                    · {{ \Illuminate\Support\Carbon::parse($e->last_seen_at)->diffForHumans() }}
                                </p>
                            </div>
                            @unless ($e->resolved_at)
                                <form method="POST" action="{{ route('settings.system.errors.resolve', $e) }}" class="shrink-0">
                                    @csrf
                                    <x-button variant="secondary" size="sm" type="submit" icon="check">{{ __('settings.system_error_resolve') }}</x-button>
                                </form>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Append-only security audit log --}}
    <div class="ll-card">
        <h2 class="text-sm font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('settings.system_audit_heading') }}</h2>
        <p class="mt-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_audit_desc') }}</p>
        @if ($audit->isEmpty())
            <p class="mt-3 text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_no_audit') }}</p>
        @else
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($audit as $a)
                    <li class="py-2.5">
                        <p class="truncate text-sm font-medium text-md-on-surface dark:text-md-on-surface">{{ $a->action }}</p>
                        <p class="mt-0.5 text-[11px] text-md-on-surface-var dark:text-md-on-surface-var">
                            <span>{{ $a->actor?->name ?? '—' }}</span>
                            @if ($a->ip)· <span class="font-mono">{{ $a->ip }}</span>@endif
                            · {{ \Illuminate\Support\Carbon::parse($a->created_at)->diffForHumans() }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="ll-card">
        <h2 class="text-sm font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('settings.system_cron_heading') }}</h2>
        <p class="mt-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_cron_hint') }}</p>
        <div class="-mx-4 mt-3 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-md-outline-variant dark:border-md-outline-variant text-xs uppercase tracking-wide text-md-on-surface-var dark:text-md-on-surface-var">
                        <th class="py-1.5 pr-3 font-medium">{{ __('settings.system_task') }}</th>
                        <th class="py-1.5 pr-3 font-medium">{{ __('settings.system_schedule') }}</th>
                        <th class="py-1.5 pr-3 font-medium">{{ __('settings.system_last_run') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tasks as $t)
                        <tr class="border-b border-md-outline-variant dark:border-md-outline-variant/50">
                            <td class="py-1.5 pr-3 font-mono text-md-on-surface dark:text-md-on-surface">{{ $t['name'] }}</td>
                            <td class="py-1.5 pr-3 font-mono text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ $t['expression'] }}</td>
                            <td class="py-1.5 pr-3">
                                @if ($t['lastAt'])
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-icon name="{{ $t['lastOk'] ? 'check' : 'x-mark' }}" class="h-4 w-4 {{ $t['lastOk'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                                        <span class="text-md-on-surface-var dark:text-md-on-surface-var" title="{{ $t['lastAt'] }}">{{ \Illuminate\Support\Carbon::parse($t['lastAt'])->diffForHumans() }}</span>
                                    </span>
                                @else
                                    <span class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.system_never') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    </div>
</x-layouts.app>
