<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
        return view('profile', ['user' => $request->user()]);
    }
}
