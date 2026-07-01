<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\File;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Team management: view the user's teams (members and record counts), change
 * the default team, and move data between the user's own teams.
 */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $teams = $user->teams()->with('users')->get()
            ->sortBy('display_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (Team $team): array => [
                'model' => $team,
                'counts' => [
                    'customers' => Customer::withoutGlobalScopes()->where('team_id', $team->id)->count(),
                    'contacts' => Contact::withoutGlobalScopes()->where('team_id', $team->id)->count(),
                    'branches' => Branch::withoutGlobalScopes()->where('team_id', $team->id)->count(),
                    'projects' => Project::withoutGlobalScopes()->where('team_id', $team->id)->count(),
                    'files' => File::withoutGlobalScopes()->where('team_id', $team->id)->count(),
                ],
            ]);

        return view('settings.teams.index', [
            'teams' => $teams,
            'defaultTeamId' => $user->default_team_id,
        ]);
    }

    /**
     * Move all owned records from one of the user's teams to another.
     */
    public function reassign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_team_id' => ['required', 'integer', 'different:to_team_id'],
            'to_team_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $from = (int) $validated['from_team_id'];
        $to = (int) $validated['to_team_id'];

        abort_unless($user->belongsToTeam($from) && $user->belongsToTeam($to), 403);

        DB::transaction(function () use ($from, $to): void {
            foreach ([Customer::class, Contact::class, Branch::class, Project::class, File::class] as $model) {
                $model::withoutGlobalScopes()->where('team_id', $from)->update(['team_id' => $to]);
            }

            // Move tags too, skipping any whose slug already exists in the
            // target team (they stay attached via the pivot regardless).
            $targetSlugs = Tag::withoutGlobalScopes()->where('team_id', $to)->pluck('slug');
            Tag::withoutGlobalScopes()
                ->where('team_id', $from)
                ->whereNotIn('slug', $targetSlugs)
                ->update(['team_id' => $to]);
        });

        return redirect()->route('settings.teams.index')->with('status', 'Data moved to the selected team.');
    }
}
