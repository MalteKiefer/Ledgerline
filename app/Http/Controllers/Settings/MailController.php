<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mail settings: account management (client-side over JSON) and the personal
 * background-sync interval (one value per user).
 */
class MailController extends Controller
{
    use RedirectsToSettings;

    /** Upper bound for the background-sync interval, in minutes (24 hours). */
    private const MAX_SYNC_MINUTES = 1440;

    public function edit(Request $request): View
    {
        return view('settings.mail.edit', [
            'settings' => UserSetting::for($request->user()->id),
            'maxSyncMinutes' => self::MAX_SYNC_MINUTES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_sync_minutes' => ['required', 'integer', 'min:5', 'max:'.self::MAX_SYNC_MINUTES],
        ], [], ['mail_sync_minutes' => __('settings.mail_sync_minutes')]);

        UserSetting::for($request->user()->id)->update($validated);

        return $this->savedRedirect('settings.mail.edit', 'flash.mail_settings_saved');
    }
}
