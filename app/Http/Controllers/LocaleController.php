<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the interface language, remembering it in the session and (for a
 * signed-in user) on their profile.
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $supported = array_keys(config('locales.languages'));

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $supported)],
        ]);

        $request->session()->put('locale', $validated['locale']);
        $request->user()?->forceFill(['locale' => $validated['locale']])->save();

        return back();
    }
}
