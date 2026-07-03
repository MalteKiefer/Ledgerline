<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Security settings: how long the encryption vault stays unlocked while idle.
 */
class SecurityController extends Controller
{
    public function edit(): View
    {
        return view('settings.security.edit', ['company' => AppSettings::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vault_idle_minutes' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        AppSettings::current()->update($validated);

        return redirect()->route('settings.security.edit')->with('status', __('flash.security_saved'));
    }
}
