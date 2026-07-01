<x-layouts.app title="Teams">
    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">Settings</a> <span aria-hidden="true">/</span> Teams
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">Teams</h1>
    <p class="mt-1 text-sm text-gray-600">Your teams come from your Pocket-ID groups.</p>

    @if ($teams->count() > 1)
        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Default team --}}
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Default team</h2>
                <p class="mt-1 text-sm text-gray-600">Activated when you sign in.</p>
                <form method="POST" action="{{ route('default-team.update') }}" class="mt-3 flex items-end gap-3">
                    @csrf
                    <select name="team_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        @foreach ($teams as $entry)
                            <option value="{{ $entry['model']->id }}" @selected($entry['model']->id === $defaultTeamId)>{{ $entry['model']->displayName }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Save</button>
                </form>
            </div>

            {{-- Move data between teams --}}
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Move data</h2>
                <p class="mt-1 text-sm text-gray-600">Move all customers, projects and files from one team to another.</p>
                <form method="POST" action="{{ route('settings.teams.reassign') }}" class="mt-3 space-y-3"
                    onsubmit="return confirm('Move all data from the source team to the target team?');">
                    @csrf
                    <div class="flex items-center gap-2 text-sm">
                        <select name="from_team_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            @foreach ($teams as $entry)
                                <option value="{{ $entry['model']->id }}">{{ $entry['model']->displayName }}</option>
                            @endforeach
                        </select>
                        <span class="text-gray-400">&rarr;</span>
                        <select name="to_team_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                            @foreach ($teams as $entry)
                                <option value="{{ $entry['model']->id }}">{{ $entry['model']->displayName }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('from_team_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Move data</button>
                </form>
            </div>
        </div>
    @endif

    <div class="mt-6 space-y-4">
        @foreach ($teams as $entry)
            @php $team = $entry['model']; $counts = $entry['counts']; @endphp
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">
                        {{ $team->displayName }}
                        @if ($team->id === $defaultTeamId)
                            <span class="ml-2 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Default</span>
                        @endif
                    </h2>
                    <span class="text-xs text-gray-400">{{ $team->key }}</span>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                    @foreach ($counts as $label => $count)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">{{ ucfirst($label) }}</dt>
                            <dd class="font-medium text-gray-900">{{ $count }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-3">
                    <p class="text-xs uppercase tracking-wide text-gray-400">Members</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $team->users->pluck('name')->join(', ') ?: '—' }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
