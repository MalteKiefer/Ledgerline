<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Settings landing page — workspace administration only. Personal preferences
 * live under the profile hub now, so a non-admin has nothing here and is sent
 * back to their profile.
 */
class SettingsController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (! $this->requireUser($request)->managesGlobalSettings()) {
            return redirect()->route('profile');
        }

        return view('settings.index');
    }
}
