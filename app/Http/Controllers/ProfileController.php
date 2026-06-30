<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Shows the signed-in user's profile.
 *
 * The profile is read-only: all identity data is owned by Pocket-ID and is
 * refreshed on each login. Nothing here is editable in the application.
 */
class ProfileController extends Controller
{
    /**
     * Display the current user's Pocket-ID profile.
     */
    public function __invoke(Request $request): View
    {
        return view('profile', ['user' => $request->user()]);
    }
}
