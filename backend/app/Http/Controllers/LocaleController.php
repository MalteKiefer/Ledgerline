<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches the interface language, remembering it in the session and (for a
 * signed-in user) on their profile.
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $languages = config('locales.languages');
        $supported = array_keys(is_array($languages) ? $languages : []);

        $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $supported)],
        ]);

        $locale = $request->string('locale')->value();

        // Session is web-only; a token API request has none.
        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }
        $request->user()?->forceFill(['locale' => $locale])->save();

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'locale' => $locale])
            : back();
    }
}
