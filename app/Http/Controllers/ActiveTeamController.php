<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sets the session's active team for users who belong to several teams.
 *
 * The active team determines which team owns newly created records. A user may
 * only activate a team they actually belong to.
 */
class ActiveTeamController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_id' => ['required', 'integer'],
        ]);

        $teamId = (int) $validated['team_id'];

        abort_unless($request->user()->teamIds()->contains($teamId), 403);

        session(['active_team_id' => $teamId]);

        return redirect()->back()->with('status', 'Active team switched.');
    }
}
