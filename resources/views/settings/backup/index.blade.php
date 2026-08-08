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
    <p class="text-sm text-md-on-surface-var dark:text-md-on-surface-var">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.backup_section') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('settings.backup_heading') }}</h1>

    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Destinations --}}
    <section class="mt-6 ll-card" x-data="{ adding: false, editing: null }">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-sm font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('settings.backup_destinations_heading') }}</h2>
            <x-button variant="secondary" size="sm" @click="adding = ! adding">{{ __('settings.backup_add_destination') }}</x-button>
        </div>

        <div x-show="adding" x-cloak class="mt-4 rounded-md border border-md-outline-variant dark:border-md-outline-variant bg-md-surface-2 dark:bg-md-surface-2 p-4">
            @include('settings.backup._destination_form', ['destination' => null, 'action' => route('settings.backup.destinations.store')])
        </div>

        @forelse ($destinations as $destination)
            <div class="mt-3 border-t border-md-outline-variant dark:border-md-outline-variant pt-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="text-sm font-medium text-md-on-surface dark:text-md-on-surface">{{ $destination->name }}</span>
                        <span class="ml-2 rounded bg-md-surface-2 dark:bg-md-surface-2 px-1.5 py-0.5 text-xs uppercase text-md-on-surface-var dark:text-md-on-surface-var">{{ $destination->driver }}</span>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <x-icon-button name="pencil" tone="gray" size="lg" @click="editing = (editing === {{ $destination->id }} ? null : {{ $destination->id }})" :aria-label="__('common.edit')" />
                        <form method="POST" action="{{ route('settings.backup.destinations.destroy', $destination) }}" data-confirm="{{ __('settings.backup_delete_confirm') }}">
                            @csrf @method('DELETE')
                            <x-icon-button name="trash" tone="red" size="lg" type="submit" :aria-label="__('common.delete')" />
                        </form>
                    </div>
                </div>
                <div x-show="editing === {{ $destination->id }}" x-cloak class="mt-3 rounded-md border border-md-outline-variant dark:border-md-outline-variant bg-md-surface-2 dark:bg-md-surface-2 p-4">
                    @include('settings.backup._destination_form', ['destination' => $destination, 'action' => route('settings.backup.destinations.update', $destination)])
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_no_destinations') }}</p>
        @endforelse
    </section>

    {{-- Jobs --}}
    <section class="mt-6 ll-card" x-data="{ adding: false, editing: null }">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-sm font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('settings.backup_jobs_heading') }}</h2>
            <x-button variant="secondary" size="sm" x-show="{{ $destinations->isNotEmpty() ? 'true' : 'false' }}" @click="adding = ! adding">{{ __('settings.backup_add_job') }}</x-button>
        </div>

        <div x-show="adding" x-cloak class="mt-4 rounded-md border border-md-outline-variant dark:border-md-outline-variant bg-md-surface-2 dark:bg-md-surface-2 p-4">
            @include('settings.backup._job_form', ['job' => null, 'action' => route('settings.backup.jobs.store')])
        </div>

        @forelse ($jobs as $job)
            {{-- Single source of truth for both the summary and the stats grid,
                 derived from the runs (avoids the summary and stats disagreeing). --}}
            @php $s = $job->statistics(); @endphp
            <div class="mt-3 border-t border-md-outline-variant dark:border-md-outline-variant pt-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="text-sm font-medium text-md-on-surface dark:text-md-on-surface">{{ $job->name }}</span>
                        @unless ($job->enabled)<span class="ml-2 inline-flex items-center rounded bg-md-surface-2 dark:bg-md-surface-2 px-1.5 py-0.5 text-xs text-md-on-surface-var dark:text-md-on-surface-var"><x-icon name="x-mark" class="h-3.5 w-3.5" /></span>@endunless
                        <p class="text-xs text-md-on-surface-var dark:text-md-on-surface-var">
                            {{ collect($job->effectiveSources())->map(fn ($s) => __('settings.backup_source_'.$s))->join(', ') }}@if (($job->mode ?? 'full') === 'incremental') <span class="text-md-on-surface-var">({{ __('settings.backup_mode_incremental') }})</span>@endif → {{ $job->destination?->name }} · <code>{{ $job->cron }}</code> ·
                            @if ($s['lastStatus'])
                                <span class="{{ $s['lastStatus'] === 'success' ? 'text-green-600' : 'text-red-600 dark:text-red-400' }}">{{ $s['lastStatus'] }}</span>
                                {{ $s['lastRun']?->diffForHumans() }}
                            @else
                                {{ __('settings.backup_never_run') }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2" x-data="{ queued: false }">
                        <x-button variant="secondary" size="sm" ::disabled="queued"
                            @click="queued = true;
                                fetch('{{ route('settings.backup.jobs.run', $job) }}', {
                                    method: 'POST',
                                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                }).then(() => window.dispatchEvent(new CustomEvent('backup-ran')))
                                  .finally(() => setTimeout(() => queued = false, 2000))">
                            <span x-show="! queued">{{ __('settings.backup_run_now') }}</span>
                            <span x-show="queued" x-cloak>{{ __('settings.backup_queued_short') }}</span>
                        </x-button>
                        <x-action-menu :aria-label="__('common.actions')">
                            <x-action-menu-item icon="pencil" @click="editing = (editing === {{ $job->id }} ? null : {{ $job->id }})">{{ __('common.edit') }}</x-action-menu-item>
                            <form method="POST" action="{{ route('settings.backup.jobs.destroy', $job) }}" data-confirm="{{ __('settings.backup_delete_confirm') }}">
                                @csrf @method('DELETE')
                                <x-action-menu-item icon="trash" danger type="submit">{{ __('common.delete') }}</x-action-menu-item>
                            </form>
                        </x-action-menu>
                    </div>
                </div>

                {{-- Per-job statistics --}}
                @if ($s['runs'] > 0)
                    <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-2 rounded-md bg-md-surface-2 dark:bg-md-surface-2 p-3 text-xs sm:grid-cols-2 md:grid-cols-4">
                        <div>
                            <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_stat_runs') }}</dt>
                            <dd class="font-medium text-md-on-surface dark:text-md-on-surface">{{ $s['runs'] }} <span class="inline-flex items-center gap-0.5 text-green-600">{{ $s['ok'] }}<x-icon name="check" class="h-3.5 w-3.5" /></span>@if ($s['failed']) <span class="inline-flex items-center gap-0.5 text-red-600 dark:text-red-400">{{ $s['failed'] }}<x-icon name="x-mark" class="h-3.5 w-3.5" /></span>@endif</dd>
                        </div>
                        <div>
                            <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_stat_success_rate') }}</dt>
                            <dd class="font-medium text-md-on-surface dark:text-md-on-surface">{{ $s['successRate'] !== null ? $s['successRate'].'%' : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_stat_last_duration') }}</dt>
                            <dd class="font-medium text-md-on-surface dark:text-md-on-surface">{{ $fmtDur($s['lastDuration']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_stat_avg_duration') }}</dt>
                            <dd class="font-medium text-md-on-surface dark:text-md-on-surface">{{ $fmtDur($s['avgDuration']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_stat_last_size') }}</dt>
                            <dd class="font-medium text-md-on-surface dark:text-md-on-surface">{{ $s['lastBytes'] ? \Illuminate\Support\Number::fileSize($s['lastBytes']) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_stat_total_size') }}</dt>
                            <dd class="font-medium text-md-on-surface dark:text-md-on-surface">{{ $s['totalBytes'] ? \Illuminate\Support\Number::fileSize($s['totalBytes']) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_stat_last_run') }}</dt>
                            <dd class="font-medium text-md-on-surface dark:text-md-on-surface" title="{{ $s['lastRun']?->toDateTimeString() }}">{{ $s['lastRun']?->diffForHumans() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_stat_next_run') }}</dt>
                            <dd class="font-medium text-md-on-surface dark:text-md-on-surface">
                                {{ $job->enabled ? ($s['nextRun']?->diffForHumans() ?? '—') : '—' }}
                                @if ($job->enabled && $s['nextRun'])
                                    <span class="block font-normal text-md-on-surface-var dark:text-md-on-surface-var">{{ $s['nextRun']->format('d.m.Y H:i') }} {{ config('app.timezone') }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                @endif

                <div x-show="editing === {{ $job->id }}" x-cloak class="mt-3 rounded-md border border-md-outline-variant dark:border-md-outline-variant bg-md-surface-2 dark:bg-md-surface-2 p-4">
                    @include('settings.backup._job_form', ['job' => $job, 'action' => route('settings.backup.jobs.update', $job)])
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_no_jobs') }}</p>
        @endforelse
    </section>

    {{-- Recent runs — live-updating (no page reload) --}}
    <section class="mt-6 ll-card"
        x-data="backupRuns({
            runsUrl: '{{ route('settings.backup.runs') }}',
            downloadBase: '{{ route('settings.backup.runs.download', ['run' => '__id__']) }}',
            decryptBase: '{{ route('settings.backup.runs.decrypt', ['run' => '__id__']) }}',
            verifyBase: '{{ route('settings.backup.runs.verify', ['run' => '__id__']) }}',
            restoreBase: '{{ route('settings.backup.runs.restore', ['run' => '__id__']) }}',
            cancelBase: '{{ route('settings.backup.runs.cancel', ['run' => '__id__']) }}',
            sourceLabels: {{ Illuminate\Support\Js::from(collect(\App\Models\BackupJob::SOURCES)->mapWithKeys(fn ($s) => [$s => __('settings.backup_source_'.$s)])) }},
            restoreConfirm: @js(__('settings.backup_restore_confirm')),
            restoreDone: @js(__('settings.backup_restore_done')),
            restoreFailed: @js(__('settings.backup_restore_failed')),
        })">
        <h2 class="text-sm font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('settings.backup_runs_heading') }}</h2>
        @error('passphrase')<p class="mt-2 rounded-md bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@enderror

        {{-- Decrypt an encrypted backup to a plaintext download (needs the passphrase) --}}
        <template x-teleport="body">
            <div x-show="decrypt.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="decrypt.open = false">
                <div class="absolute inset-0 bg-gray-900/40" @click="decrypt.open = false"></div>
                <form method="POST" :action="decryptAction" class="relative w-full max-w-md rounded-xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-md-surface p-6 shadow-xl">
                    @csrf
                    <input type="hidden" name="source" :value="decrypt.source">
                    <h3 class="text-base font-semibold text-md-on-surface dark:text-md-on-surface">{{ __('settings.backup_decrypt') }}</h3>
                    <p class="mt-1 text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_decrypt_hint') }}</p>
                    <input type="password" name="passphrase" required autocomplete="off" placeholder="{{ __('settings.backup_passphrase') }}"
                        class="mt-3 block w-full rounded-md border-md-outline-variant dark:border-md-outline-variant text-sm shadow-sm focus:border-accent focus:ring-accent">
                    <div class="mt-4 flex justify-end gap-2">
                        <x-button variant="secondary" @click="decrypt.open = false">{{ __('common.cancel') }}</x-button>
                        <x-button variant="primary" type="submit">{{ __('settings.backup_decrypt_download') }}</x-button>
                    </div>
                </form>
            </div>
        </template>

        <p x-show="runs.length === 0" class="mt-3 text-sm text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_no_runs') }}</p>
        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <table x-show="runs.length > 0" x-cloak class="mt-3 w-full text-left text-sm">
            <thead class="text-xs uppercase text-md-on-surface-var dark:text-md-on-surface-var">
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
                    <tr class="border-t border-md-outline-variant dark:border-md-outline-variant">
                        <td class="py-1.5 align-top">
                            <button type="button" @click="toggle(r.id)" aria-label="{{ __('common.details') }}" :aria-expanded="expanded[r.id] ? 'true' : 'false'" class="rounded p-0.5 text-md-on-surface-var dark:text-md-on-surface-var hover:bg-accent/5 hover:text-md-on-surface-var dark:hover:text-md-on-surface-var">
                                <x-icon name="chevron-down" class="h-4 w-4 transition-transform" ::class="expanded[r.id] ? 'rotate-180' : ''" />
                            </button>
                        </td>
                        <td class="py-1.5 pr-3 align-top text-md-on-surface-var dark:text-md-on-surface-var" x-text="r.job ?? '—'"></td>
                        <td class="py-1.5 pr-3 align-top">
                            <span :class="r.status === 'success' ? 'text-green-600' : (r.status === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-md-on-surface-var dark:text-md-on-surface-var')" x-text="r.status"></span>
                            <span x-show="r.status === 'failed' && r.message" class="block break-words text-xs text-md-on-surface-var" :title="r.message" x-text="r.message"></span>
                        </td>
                        <td class="py-1.5 pr-3 align-top text-md-on-surface-var dark:text-md-on-surface-var" x-text="r.startedHuman"></td>
                        <td class="py-1.5 pr-3 align-top text-md-on-surface-var dark:text-md-on-surface-var" x-text="r.size ?? '—'"></td>
                        <td class="py-1.5 pr-3 align-top">
                          <div class="flex items-center justify-end gap-1.5">
                            <span x-show="r.verifyStatus === 'ok'" title="{{ __('settings.backup_verify_ok') }}" class="inline-flex text-green-600 dark:text-green-400"><x-icon name="check-circle" class="h-4 w-4" /></span>
                            <span x-show="r.verifyStatus === 'failed'" :title="r.verifyMessage" class="inline-flex text-amber-500 dark:text-amber-400"><x-icon name="exclamation-triangle" class="h-4 w-4" /></span>
                            <button x-show="r.cancellable" type="button" @click="cancel(r.id)" title="{{ __('settings.backup_cancel') }}" :aria-label="'{{ __('settings.backup_cancel') }}'" class="inline-flex rounded p-1 text-md-on-surface-var hover:bg-red-50 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button>
                            <span x-show="r.cancelling" class="inline-flex items-center gap-1.5">
                                <span class="text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_cancelling') }}</span>
                                <button type="button" @click="cancel(r.id)" title="{{ __('settings.backup_force_stop') }}" :aria-label="'{{ __('settings.backup_force_stop') }}'" class="rounded p-1 text-md-on-surface-var hover:bg-red-50 hover:text-red-600"><x-icon name="stop" class="h-4 w-4" /></button>
                            </span>
                            {{-- Per-archive actions live in the expandable detail row --}}
                            <button x-show="hasArchives(r)" type="button" @click="toggle(r.id)" title="{{ __('common.actions') }}" :aria-label="'{{ __('common.actions') }}'" class="inline-flex rounded p-1 text-md-on-surface-var hover:bg-accent/5 hover:text-md-on-surface-var dark:hover:text-md-on-surface-var"><x-icon name="ellipsis" class="h-4 w-4" /></button>
                          </div>
                        </td>
                    </tr>
                    <tr x-show="expanded[r.id]" x-cloak>
                        <td></td>
                        <td colspan="5" class="pb-3 pr-3">
                            {{-- Per-archive actions: one row per backed-up source --}}
                            <div x-show="hasArchives(r)" class="mb-3 space-y-2">
                                <template x-for="a in r.archives" :key="a.source">
                                    <div class="rounded-md border border-md-outline-variant dark:border-md-outline-variant p-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium text-md-on-surface dark:text-md-on-surface" x-text="sourceLabel(a.source)"></span>
                                            <span x-show="a.encrypted" class="inline-flex items-center gap-1 text-xs text-md-on-surface-var"><x-icon name="lock-closed" class="h-3.5 w-3.5" />{{ __('settings.backup_encrypt') }}</span>
                                            <span class="flex-1"></span>
                                            {{-- Download (plaintext archives) --}}
                                            <a x-show="! a.encrypted" :href="downloadUrl(r.id, a.source)" class="inline-flex items-center gap-1.5 rounded-md bg-md-surface-2 dark:bg-md-surface-2 px-2.5 py-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var hover:bg-accent/5"><x-icon name="arrow-down-tray" class="h-3.5 w-3.5" />{{ __('settings.backup_download') }}</a>
                                            {{-- Decrypt → plaintext download (encrypted archives) --}}
                                            <button x-show="a.encrypted" type="button" @click="openDecrypt(r.id, a.source)" class="inline-flex items-center gap-1.5 rounded-md bg-md-surface-2 dark:bg-md-surface-2 px-2.5 py-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var hover:bg-accent/5"><x-icon name="lock-open" class="h-3.5 w-3.5" />{{ __('settings.backup_decrypt') }}</button>
                                            {{-- Restore blob sources (files/invoices) onto live data --}}
                                            <button x-show="a.restorable" type="button" @click="restore(r.id, a.source)" class="inline-flex items-center gap-1.5 rounded-md bg-md-surface-2 dark:bg-md-surface-2 px-2.5 py-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var hover:bg-accent/5"><x-icon name="arrow-uturn-left" class="h-3.5 w-3.5" />{{ __('settings.backup_restore_source') }}</button>
                                        </div>
                                        {{-- Verify (dry run) — needs the passphrase for an encrypted archive --}}
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <input x-show="a.encrypted" type="password" x-model="vstate(r.id, a.source).pass" autocomplete="off" placeholder="{{ __('settings.backup_passphrase') }}"
                                                class="rounded-md border-md-outline-variant dark:border-md-outline-variant text-xs shadow-sm focus:border-accent focus:ring-accent">
                                            <x-button variant="secondary" size="sm" icon="shield" @click="runVerify(r.id, a.source)" ::disabled="vstate(r.id, a.source).busy">
                                                <span x-text="vstate(r.id, a.source).busy ? '{{ __('settings.backup_verifying') }}' : '{{ __('settings.backup_verify') }}'"></span>
                                            </x-button>
                                            <span x-show="vstate(r.id, a.source).result" x-cloak class="rounded-md px-2 py-1 text-xs" :class="vstate(r.id, a.source).result && vstate(r.id, a.source).result.ok ? 'bg-green-50 dark:bg-green-950 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300'" x-text="vstate(r.id, a.source).result ? vstate(r.id, a.source).result.message : ''"></span>
                                        </div>
                                        <p x-show="a.source === 'database'" class="mt-1 text-[11px] text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_restore_db_hint') }}</p>
                                    </div>
                                </template>
                            </div>
                            <pre x-show="r.log" class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-md bg-gray-900 p-3 font-mono text-[11px] leading-relaxed text-gray-100" x-text="r.log"></pre>
                            <p x-show="! r.log" class="text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_no_log') }}</p>
                        </td>
                    </tr>
                </tbody>
            </template>
        </table>
        </div>
    </section>
</x-layouts.app>
