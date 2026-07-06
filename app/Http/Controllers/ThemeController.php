<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Switches the user's colour scheme (light / dark / system). */
class ThemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'in:light,dark,system'],
        ]);

        UserSetting::for($request->user()->id)->update(['theme' => $validated['theme']]);

        return back();
    }
}
