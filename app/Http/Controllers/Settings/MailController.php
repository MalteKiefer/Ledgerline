<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mail settings: account management (client-side over JSON) and the
 * background-sync interval.
 */
class MailController extends Controller
{
    /** Upper bound for the background-sync interval, in minutes (24 hours). */
    private const MAX_SYNC_MINUTES = 1440;

    public function edit(): View
    {
        return view('settings.mail.edit', [
            'settings' => AppSettings::current(),
            'maxSyncMinutes' => self::MAX_SYNC_MINUTES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_sync_minutes' => ['required', 'integer', 'min:5', 'max:'.self::MAX_SYNC_MINUTES],
        ], [], ['mail_sync_minutes' => __('settings.mail_sync_minutes')]);

        AppSettings::current()->update($validated);

        return redirect()->route('settings.mail.edit')->with('status', __('flash.mail_settings_saved'));
    }
}
