<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shows the signed-in user's profile.
 *
 * Identity data is owned by Pocket-ID and refreshed on each login (read-only).
 */
class ProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        $currentId = $request->session()->getId();
        $sessions = config('session.driver') === 'database'
            ? DB::table('sessions')->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')->get()
                ->map(fn ($s): array => [
                    'id' => $s->id,
                    'ip' => $s->ip_address,
                    'agent' => $s->user_agent,
                    'last_activity' => $s->last_activity,
                    'current' => $s->id === $currentId,
                ])->all()
            : [];

        // Paired devices are loaded + kept live client-side (GET /devices).
        return view('profile', [
            'user' => $request->user(),
            'sessions' => $sessions,
            'deviceMax' => (int) config('devices.max', 3),
        ]);
    }
}
