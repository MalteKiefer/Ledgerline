<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Switches the user's colour scheme (light / dark / system). */
class ThemeController extends Controller
{
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'in:light,dark,system'],
        ]);

        UserSetting::for($this->requireUser($request)->id)->update(['theme' => $validated['theme']]);

        // API clients (Sanctum) get JSON; the web form gets a redirect back.
        return $request->expectsJson()
            ? response()->json(['ok' => true, 'theme' => $validated['theme']])
            : back();
    }
}
