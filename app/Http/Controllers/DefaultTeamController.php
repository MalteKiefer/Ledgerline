<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sets the user's persisted default team (chosen once at first login, then
 * changeable from the profile). It also activates it for the current session.
 */
class DefaultTeamController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_id' => ['required', 'integer'],
        ]);

        $teamId = (int) $validated['team_id'];
        $user = $request->user();

        abort_unless($user->belongsToTeam($teamId), 403);

        $user->forceFill(['default_team_id' => $teamId])->save();
        session(['active_team_id' => $teamId]);

        return redirect()->back()->with('status', 'Default team saved.');
    }
}
