{{--
    Blocking overlay shown after login when the user belongs to more than one
    team and has not yet chosen an active one. Picking a team sets it as the
    session's active team; it can be changed later from the header.
--}}
@auth
    @php
        $pickerUser = auth()->user();
        $pickerTeams = $pickerUser->teams->sortBy('display_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    @endphp

    @if ($pickerTeams->count() > 1 && ! session('active_team_id') && ! $pickerUser->hasChosenDefaultTeam())
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                <h2 class="text-lg font-semibold text-gray-900">Choose your team</h2>
                <p class="mt-1 text-sm text-gray-600">
                    You belong to several teams. Pick your default — you'll only be asked once, and can change it later under Profile.
                </p>

                <div class="mt-4 space-y-2">
                    @foreach ($pickerTeams as $team)
                        <form method="POST" action="{{ route('default-team.update') }}">
                            @csrf
                            <input type="hidden" name="team_id" value="{{ $team->id }}">
                            <button type="submit"
                                class="flex w-full items-center justify-between rounded-md border border-gray-200 px-4 py-3 text-left text-sm font-medium text-gray-900 hover:border-gray-800 hover:bg-gray-50">
                                <span>{{ $team->displayName }}</span>
                                <span aria-hidden="true" class="text-gray-400">&rarr;</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endauth
