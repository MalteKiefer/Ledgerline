<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mail settings: account management (client-side, in the encrypted vault) and
 * the background-sync interval.
 */
class MailController extends Controller
{
    public function edit(): View
    {
        return view('settings.mail.edit', ['settings' => AppSettings::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Never longer than the vault idle timeout (otherwise the vault locks
        // before the next background sync). Idle lives in security settings, so
        // compare against its stored value as a numeric max.
        $idle = (int) (AppSettings::current()->vault_idle_minutes ?? 10);

        $validated = $request->validate([
            'mail_sync_minutes' => ['required', 'integer', 'min:5', 'max:'.max(5, $idle)],
        ], [], ['mail_sync_minutes' => __('settings.mail_sync_minutes')]);

        AppSettings::current()->update($validated);

        return redirect()->route('settings.mail.edit')->with('status', __('flash.mail_settings_saved'));
    }
}
