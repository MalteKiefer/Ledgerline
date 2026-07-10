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
 * CardDAV/CalDAV sync lives under the personal calendar/contacts settings.
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

        // Paired mobile devices (Sanctum tokens), newest first.
        $devices = $request->user()->tokens()
            ->orderByDesc('created_at')->get()
            ->map(fn ($t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'last_used' => $t->last_used_at,
                'created' => $t->created_at,
            ])->all();

        return view('profile', [
            'user' => $request->user(),
            'sessions' => $sessions,
            'devices' => $devices,
            'deviceMax' => (int) config('devices.max', 3),
        ]);
    }
}
