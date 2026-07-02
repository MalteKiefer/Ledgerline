<x-layouts.app :title="__('settings.backup_heading')">
    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.backup_section') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('settings.backup_heading') }}</h1>

    @if ($errors->any())
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Destinations --}}
    <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm" x-data="{ adding: false, editing: null }">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.backup_destinations_heading') }}</h2>
            <button type="button" @click="adding = ! adding" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('settings.backup_add_destination') }}</button>
        </div>

        <div x-show="adding" x-cloak class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4">
            @include('settings.backup._destination_form', ['destination' => null, 'action' => route('settings.backup.destinations.store')])
        </div>

        @forelse ($destinations as $destination)
            <div class="mt-3 border-t border-gray-100 pt-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="text-sm font-medium text-gray-900">{{ $destination->name }}</span>
                        <span class="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs uppercase text-gray-500">{{ $destination->driver }}</span>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" @click="editing = (editing === {{ $destination->id }} ? null : {{ $destination->id }})" class="rounded p-1.5 text-gray-500 hover:bg-gray-100"><x-icon name="pencil" /></button>
                        <form method="POST" action="{{ route('settings.backup.destinations.destroy', $destination) }}" onsubmit="return confirm('{{ __('settings.backup_delete_confirm') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded p-1.5 text-red-600 hover:bg-red-50"><x-icon name="trash" /></button>
                        </form>
                    </div>
                </div>
                <div x-show="editing === {{ $destination->id }}" x-cloak class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-4">
                    @include('settings.backup._destination_form', ['destination' => $destination, 'action' => route('settings.backup.destinations.update', $destination)])
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-gray-500">{{ __('settings.backup_no_destinations') }}</p>
        @endforelse
    </section>

    {{-- Jobs --}}
    <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm" x-data="{ adding: false, editing: null }">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.backup_jobs_heading') }}</h2>
            <button type="button" x-show="{{ $destinations->isNotEmpty() ? 'true' : 'false' }}" @click="adding = ! adding" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('settings.backup_add_job') }}</button>
        </div>

        <div x-show="adding" x-cloak class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4">
            @include('settings.backup._job_form', ['job' => null, 'action' => route('settings.backup.jobs.store')])
        </div>

        @forelse ($jobs as $job)
            <div class="mt-3 border-t border-gray-100 pt-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="text-sm font-medium text-gray-900">{{ $job->name }}</span>
                        @unless ($job->enabled)<span class="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">✕</span>@endunless
                        <p class="text-xs text-gray-500">
                            {{ __('settings.backup_source_'.$job->source) }} → {{ $job->destination?->name }} · <code>{{ $job->cron }}</code> ·
                            @if ($job->last_status)
                                <span class="{{ $job->last_status === 'success' ? 'text-green-600' : 'text-red-600' }}">{{ $job->last_status }}</span>
                                {{ $job->last_run_at?->diffForHumans() }}
                            @else
                                {{ __('settings.backup_never_run') }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <form method="POST" action="{{ route('settings.backup.jobs.run', $job) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('settings.backup_run_now') }}</button>
                        </form>
                        <button type="button" @click="editing = (editing === {{ $job->id }} ? null : {{ $job->id }})" class="rounded p-1.5 text-gray-500 hover:bg-gray-100"><x-icon name="pencil" /></button>
                        <form method="POST" action="{{ route('settings.backup.jobs.destroy', $job) }}" onsubmit="return confirm('{{ __('settings.backup_delete_confirm') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded p-1.5 text-red-600 hover:bg-red-50"><x-icon name="trash" /></button>
                        </form>
                    </div>
                </div>
                <div x-show="editing === {{ $job->id }}" x-cloak class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-4">
                    @include('settings.backup._job_form', ['job' => $job, 'action' => route('settings.backup.jobs.update', $job)])
                </div>
            </div>
        @empty
            <p class="mt-3 text-sm text-gray-500">{{ __('settings.backup_no_jobs') }}</p>
        @endforelse
    </section>

    {{-- Recent runs --}}
    <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.backup_runs_heading') }}</h2>
        @if ($runs->isEmpty())
            <p class="mt-3 text-sm text-gray-500">{{ __('settings.backup_no_runs') }}</p>
        @else
            <table class="mt-3 w-full text-left text-sm">
                <thead class="text-xs uppercase text-gray-400">
                    <tr>
                        <th class="py-1 pr-3">{{ __('settings.backup_name') }}</th>
                        <th class="py-1 pr-3">{{ __('settings.backup_status') }}</th>
                        <th class="py-1 pr-3">{{ __('settings.backup_started') }}</th>
                        <th class="py-1 pr-3">{{ __('settings.backup_size') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        <tr class="border-t border-gray-100">
                            <td class="py-1.5 pr-3 text-gray-700">{{ $run->job?->name ?? '—' }}</td>
                            <td class="py-1.5 pr-3">
                                <span class="{{ $run->status === 'success' ? 'text-green-600' : ($run->status === 'failed' ? 'text-red-600' : 'text-gray-500') }}">{{ $run->status }}</span>
                                @if ($run->status === 'failed' && $run->message)
                                    <span class="block max-w-md truncate text-xs text-gray-400" title="{{ $run->message }}">{{ $run->message }}</span>
                                @endif
                            </td>
                            <td class="py-1.5 pr-3 text-gray-500">{{ $run->started_at?->diffForHumans() }}</td>
                            <td class="py-1.5 pr-3 text-gray-500">{{ $run->bytes ? \Illuminate\Support\Number::fileSize($run->bytes) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</x-layouts.app>
