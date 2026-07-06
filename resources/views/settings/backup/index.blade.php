<x-layouts.app :title="__('settings.backup_heading')">
    @php
        // Human-readable duration: "45s", "3m 12s", "1h 4m".
        $fmtDur = function (?int $s): string {
            if ($s === null) {
                return '—';
            }
            if ($s < 60) {
                return $s.'s';
            }
            if ($s < 3600) {
                return intdiv($s, 60).'m '.($s % 60).'s';
            }

            return intdiv($s, 3600).'h '.intdiv($s % 3600, 60).'m';
        };
    @endphp
    <p class="text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.backup_section') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.backup_heading') }}</h1>

    @if ($errors->any())
        <div class="mt-4 rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Destinations --}}
    <section class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6" x-data="{ adding: false, editing: null }">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.backup_destinations_heading') }}</h2>
            <button type="button" @click="adding = ! adding" class="rounded-md border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">{{ __('settings.backup_add_destination') }}</button>
        </div>

        <div x-show="adding" x-cloak class="mt-4 rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 p-4">
            @include('settings.backup._destination_form', ['destination' => null, 'action' => route('settings.backup.destinations.store')])
        </div>

        @forelse ($destinations as $destination)
            <div class="mt-3 border-t border-gray-100 dark:border-gray-800 pt-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $destination->name }}</span>
                        <span class="ml-2 rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs uppercase text-gray-500 dark:text-gray-400">{{ $destination->driver }}</span>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" @click="editing = (editing === {{ $destination->id }} ? null : {{ $destination->id }})" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded p-2.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="pencil" /></button>
                        <form method="POST" action="{{ route('settings.backup.destinations.destroy', $destination) }}" data-confirm="{{ __('settings.backup_delete_confirm') }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded p-2.5 text-red-600 dark:text-red-400 hover:bg-red-50"><x-icon name="trash" /></button>
                        </form>
                    </div>
                </div>
                <div x-show="editing === {{ $destination->id }}" x-cloak class="mt-3 rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 p-4">
                    @include('settings.backup._destination_form', ['destination' => $destination, 'action' => route('settings.backup.destinations.update', $destination)])
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('settings.backup_no_destinations') }}</p>
        @endforelse
    </section>

    {{-- Jobs --}}
    <section class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6" x-data="{ adding: false, editing: null }">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.backup_jobs_heading') }}</h2>
            <button type="button" x-show="{{ $destinations->isNotEmpty() ? 'true' : 'false' }}" @click="adding = ! adding" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('settings.backup_add_job') }}</button>
        </div>

        <div x-show="adding" x-cloak class="mt-4 rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 p-4">
            @include('settings.backup._job_form', ['job' => null, 'action' => route('settings.backup.jobs.store')])
        </div>

        @forelse ($jobs as $job)
            {{-- Single source of truth for both the summary and the stats grid,
                 derived from the runs (avoids the summary and stats disagreeing). --}}
            @php $s = $job->statistics(); @endphp
            <div class="mt-3 border-t border-gray-100 dark:border-gray-800 pt-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $job->name }}</span>
                        @unless ($job->enabled)<span class="ml-2 inline-flex items-center rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs text-gray-500 dark:text-gray-400"><x-icon name="x-mark" class="h-3.5 w-3.5" /></span>@endunless
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('settings.backup_source_'.$job->source) }} → {{ $job->destination?->name }} · <code>{{ $job->cron }}</code> ·
                            @if ($s['lastStatus'])
                                <span class="{{ $s['lastStatus'] === 'success' ? 'text-green-600' : 'text-red-600 dark:text-red-400' }}">{{ $s['lastStatus'] }}</span>
                                {{ $s['lastRun']?->diffForHumans() }}
                            @else
                                {{ __('settings.backup_never_run') }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2" x-data="{ queued: false }">
                        <button type="button" :disabled="queued"
                            @click="queued = true;
                                fetch('{{ route('settings.backup.jobs.run', $job) }}', {
                                    method: 'POST',
                                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                }).then(() => window.dispatchEvent(new CustomEvent('backup-ran')))
                                  .finally(() => setTimeout(() => queued = false, 2000))"
                            class="rounded-md border border-gray-300 dark:border-gray-700 px-2 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50">
                            <span x-show="! queued">{{ __('settings.backup_run_now') }}</span>
                            <span x-show="queued" x-cloak>{{ __('settings.backup_queued_short') }}</span>
                        </button>
                        <button type="button" @click="editing = (editing === {{ $job->id }} ? null : {{ $job->id }})" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded p-2.5 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="pencil" /></button>
                        <form method="POST" action="{{ route('settings.backup.jobs.destroy', $job) }}" data-confirm="{{ __('settings.backup_delete_confirm') }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded p-2.5 text-red-600 dark:text-red-400 hover:bg-red-50"><x-icon name="trash" /></button>
                        </form>
                    </div>
                </div>

                {{-- Per-job statistics --}}
                @if ($s['runs'] > 0)
                    <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-2 rounded-md bg-gray-50 dark:bg-gray-800 p-3 text-xs sm:grid-cols-2 md:grid-cols-4">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('settings.backup_stat_runs') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $s['runs'] }} <span class="inline-flex items-center gap-0.5 text-green-600">{{ $s['ok'] }}<x-icon name="check" class="h-3.5 w-3.5" /></span>@if ($s['failed']) <span class="inline-flex items-center gap-0.5 text-red-600 dark:text-red-400">{{ $s['failed'] }}<x-icon name="x-mark" class="h-3.5 w-3.5" /></span>@endif</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('settings.backup_stat_success_rate') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $s['successRate'] !== null ? $s['successRate'].'%' : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('settings.backup_stat_last_duration') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $fmtDur($s['lastDuration']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('settings.backup_stat_avg_duration') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $fmtDur($s['avgDuration']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('settings.backup_stat_last_size') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $s['lastBytes'] ? \Illuminate\Support\Number::fileSize($s['lastBytes']) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('settings.backup_stat_total_size') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $s['totalBytes'] ? \Illuminate\Support\Number::fileSize($s['totalBytes']) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('settings.backup_stat_last_run') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100" title="{{ $s['lastRun']?->toDateTimeString() }}">{{ $s['lastRun']?->diffForHumans() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('settings.backup_stat_next_run') }}</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $job->enabled ? ($s['nextRun']?->diffForHumans() ?? '—') : '—' }}
                                @if ($job->enabled && $s['nextRun'])
                                    <span class="block font-normal text-gray-400 dark:text-gray-500">{{ $s['nextRun']->format('d.m.Y H:i') }} {{ config('app.timezone') }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                @endif

                <div x-show="editing === {{ $job->id }}" x-cloak class="mt-3 rounded-md border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 p-4">
                    @include('settings.backup._job_form', ['job' => $job, 'action' => route('settings.backup.jobs.update', $job)])
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('settings.backup_no_jobs') }}</p>
        @endforelse
    </section>

    {{-- Recent runs — live-updating (no page reload) --}}
    <section class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 shadow-sm sm:p-6"
        x-data="backupRuns({ runsUrl: '{{ route('settings.backup.runs') }}', downloadBase: '{{ route('settings.backup.runs.download', ['run' => '__id__']) }}', cancelBase: '{{ route('settings.backup.runs.cancel', ['run' => '__id__']) }}' })">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.backup_runs_heading') }}</h2>
        <p x-show="runs.length === 0" class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('settings.backup_no_runs') }}</p>
        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <table x-show="runs.length > 0" x-cloak class="mt-3 w-full text-left text-sm">
            <thead class="text-xs uppercase text-gray-400 dark:text-gray-500">
                <tr>
                    <th class="w-6 py-1"></th>
                    <th class="py-1 pr-3">{{ __('settings.backup_name') }}</th>
                    <th class="py-1 pr-3">{{ __('settings.backup_status') }}</th>
                    <th class="py-1 pr-3">{{ __('settings.backup_started') }}</th>
                    <th class="py-1 pr-3">{{ __('settings.backup_size') }}</th>
                    <th class="py-1 pr-3"></th>
                </tr>
            </thead>
            {{-- One <tbody> per run (valid HTML) so x-for has a single root and
                 can hold both the row and its expandable log row. --}}
            <template x-for="r in runs" :key="r.id">
                <tbody>
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="py-1.5 align-top">
                            <button type="button" @click="toggle(r.id)" class="rounded p-0.5 text-gray-400 dark:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300">
                                <x-icon name="chevron-down" class="h-4 w-4 transition-transform" ::class="expanded[r.id] ? 'rotate-180' : ''" />
                            </button>
                        </td>
                        <td class="py-1.5 pr-3 align-top text-gray-700 dark:text-gray-300" x-text="r.job ?? '—'"></td>
                        <td class="py-1.5 pr-3 align-top">
                            <span :class="r.status === 'success' ? 'text-green-600' : (r.status === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400')" x-text="r.status"></span>
                            <span x-show="r.status === 'failed' && r.message" class="block break-words text-xs text-gray-400" :title="r.message" x-text="r.message"></span>
                        </td>
                        <td class="py-1.5 pr-3 align-top text-gray-500 dark:text-gray-400" x-text="r.startedHuman"></td>
                        <td class="py-1.5 pr-3 align-top text-gray-500 dark:text-gray-400" x-text="r.size ?? '—'"></td>
                        <td class="py-1.5 pr-3 align-top">
                            <a x-show="r.downloadable" :href="downloadUrl(r.id)" title="{{ __('settings.backup_download') }}" :aria-label="'{{ __('settings.backup_download') }}'" class="inline-flex rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700"><x-icon name="arrow-down-tray" class="h-4 w-4" /></a>
                            <button x-show="r.cancellable" type="button" @click="cancel(r.id)" title="{{ __('settings.backup_cancel') }}" :aria-label="'{{ __('settings.backup_cancel') }}'" class="inline-flex rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                            <span x-show="r.cancelling" class="inline-flex items-center gap-1.5">
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('settings.backup_cancelling') }}</span>
                                <button type="button" @click="cancel(r.id)" title="{{ __('settings.backup_force_stop') }}" :aria-label="'{{ __('settings.backup_force_stop') }}'" class="rounded p-1 text-gray-500 hover:bg-red-50 hover:text-red-600"><x-icon name="stop" class="h-4 w-4" /></button>
                            </span>
                        </td>
                    </tr>
                    <tr x-show="expanded[r.id]" x-cloak>
                        <td></td>
                        <td colspan="5" class="pb-3 pr-3">
                            <pre x-show="r.log" class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-md bg-gray-900 p-3 font-mono text-[11px] leading-relaxed text-gray-100" x-text="r.log"></pre>
                            <p x-show="! r.log" class="text-xs text-gray-400 dark:text-gray-500">{{ __('settings.backup_no_log') }}</p>
                        </td>
                    </tr>
                </tbody>
            </template>
        </table>
        </div>
    </section>
</x-layouts.app>
